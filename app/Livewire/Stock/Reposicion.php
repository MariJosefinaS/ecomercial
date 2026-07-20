<?php

namespace App\Livewire\Stock;

use App\Livewire\Concerns\AutorizaPermisos;
use App\Models\Local;
use App\Models\Parametro;
use App\Models\Producto;
use App\Models\SolicitudCompra;
use App\Models\VentaItem;
use App\Support\Eoq;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Reposición (EOQ) — E.Comercial')]
class Reposicion extends Component
{
    use AutorizaPermisos;

    /** Estados de venta que cuentan como demanda real. */
    private const ESTADOS_DEMANDA = ['aprobada', 'entregada'];

    /** Parámetros del modelo (editables, se persisten en `parametros`). */
    public float $costoPedido = 5000;       // S — $ por orden de compra
    public float $tasaMantenimiento = 25;   // % anual del valor inmovilizado
    public float $nivelServicio = 95;       // % (define z del stock de seguridad)
    public string $meses = 'todo';          // ventana de histórico: 3 | 6 | 12 | todo

    public ?string $mensaje = null;
    public int $leadTimeDefault = 7;        // si el proveedor no tiene dias_entrega

    public function mount(): void
    {
        $this->autorizar('gestionar_stock');
        $this->costoPedido = Parametro::num('eoq_costo_pedido', 5000);
        $this->tasaMantenimiento = Parametro::num('eoq_tasa_mantenimiento', 25);
        $this->nivelServicio = Parametro::num('eoq_nivel_servicio', 95);
    }

    public function guardarParametros(): void
    {
        $this->autorizar('gestionar_stock');
        $this->costoPedido = max(0, (float) $this->costoPedido);
        $this->tasaMantenimiento = max(0, (float) $this->tasaMantenimiento);
        $this->nivelServicio = min(99.9, max(50, (float) $this->nivelServicio));

        Parametro::set('eoq_costo_pedido', $this->costoPedido);
        Parametro::set('eoq_tasa_mantenimiento', $this->tasaMantenimiento);
        Parametro::set('eoq_nivel_servicio', $this->nivelServicio);
        $this->mensaje = 'Parámetros guardados.';
    }

    /** Genera una solicitud de reposición con el lote sugerido por el EOQ. */
    public function solicitar(int $productoId, int $cantidad): void
    {
        $this->autorizar('gestionar_stock');
        $p = Producto::find($productoId);
        if (! $p || $cantidad < 1) {
            return;
        }
        $localId = auth()->user()?->local_id ?? Local::where('activo', true)->orderBy('id')->value('id');
        $maxNum = (int) (SolicitudCompra::selectRaw("MAX(CAST(REGEXP_REPLACE(numero, '[^0-9]', '') AS UNSIGNED)) as n")->value('n') ?? 76);

        SolicitudCompra::create([
            'numero' => 'SOL-' . ($maxNum + 1),
            'producto_id' => $p->id,
            'local_id' => $localId,
            'solicitante_id' => auth()->id(),
            'cantidad' => $cantidad,
            'estado' => 'pendiente',
            'nota' => "Reposición sugerida por EOQ ({$cantidad} u.)",
        ]);
        $this->mensaje = "Solicitud de reposición creada para «{$p->nombre}» ({$cantidad} u.).";
    }

    /** Fecha desde la que se considera el histórico de ventas. */
    private function desde(): ?Carbon
    {
        return match ($this->meses) {
            '3' => now()->subMonths(3)->startOfDay(),
            '6' => now()->subMonths(6)->startOfDay(),
            '12' => now()->subMonths(12)->startOfDay(),
            default => null, // todo el histórico
        };
    }

    /**
     * Demanda por producto en la ventana: total de unidades + serie mensual
     * (para el desvío) + cantidad de meses del período.
     * @return array{0: array<int,float>, 1: array<int,array<string,float>>, 2: float}
     */
    private function demandaPorProducto(?Carbon $desde): array
    {
        $items = VentaItem::query()
            ->select('venta_items.producto_id', 'venta_items.cantidad', 'ventas.fecha')
            ->join('ventas', 'ventas.id', '=', 'venta_items.venta_id')
            ->whereIn('ventas.estado', self::ESTADOS_DEMANDA)
            ->when($desde, fn ($q) => $q->where('ventas.fecha', '>=', $desde))
            ->get();

        $total = [];     // producto_id => unidades
        $mensual = [];    // producto_id => [ 'YYYY-MM' => unidades ]
        $minFecha = null;

        foreach ($items as $it) {
            $pid = (int) $it->producto_id;
            $cant = (float) $it->cantidad;
            $mes = Carbon::parse($it->fecha)->format('Y-m');
            $total[$pid] = ($total[$pid] ?? 0) + $cant;
            $mensual[$pid][$mes] = ($mensual[$pid][$mes] ?? 0) + $cant;
            $f = Carbon::parse($it->fecha);
            if (! $minFecha || $f->lt($minFecha)) {
                $minFecha = $f;
            }
        }

        $inicio = $desde ?? $minFecha ?? now();
        $spanMeses = max(1, round($inicio->copy()->startOfMonth()->diffInMonths(now()) + 1));

        return [$total, $mensual, (float) $spanMeses];
    }

    /** Desvío estándar de la demanda DIARIA, derivado de la serie mensual. */
    private function sigmaDiaria(array $serieMensual, float $spanMeses): float
    {
        // Completar con ceros los meses sin ventas dentro del período.
        $n = max(1, (int) $spanMeses);
        $valores = array_values($serieMensual);
        $valores = array_pad($valores, $n, 0.0);

        $media = array_sum($valores) / count($valores);
        $var = 0.0;
        foreach ($valores as $v) {
            $var += ($v - $media) ** 2;
        }
        // Desvío muestral (n-1) si hay más de un mes.
        $div = count($valores) > 1 ? count($valores) - 1 : 1;
        $sigmaMensual = sqrt($var / $div);

        // σ_diaria = σ_mensual / √(días por mes), asumiendo demanda independiente.
        return $sigmaMensual / sqrt(self::DIAS_POR_MES);
    }

    private const DIAS_POR_MES = 30.42;

    public function render()
    {
        $desde = $this->desde();
        [$total, $mensual, $spanMeses] = $this->demandaPorProducto($desde);

        $productos = Producto::with(['proveedor:id,nombre,dias_entrega', 'stock'])
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $filas = $productos->map(function (Producto $p) use ($total, $mensual, $spanMeses) {
            $unidades = $total[$p->id] ?? 0;
            $demandaAnual = $unidades / $spanMeses * 12;
            $sigmaDiaria = $this->sigmaDiaria($mensual[$p->id] ?? [], $spanMeses);
            $stockActual = (int) $p->stock->sum('cantidad');
            $lead = (int) ($p->proveedor?->dias_entrega ?: $this->leadTimeDefault);
            $costoUnit = (float) $p->precio_compra;

            $r = Eoq::calcular(
                demandaAnual: $demandaAnual,
                costoPedido: $this->costoPedido,
                costoUnit: $costoUnit,
                tasaMantPct: $this->tasaMantenimiento,
                leadTimeDias: $lead,
                sigmaDiaria: $sigmaDiaria,
                nivelServicio: $this->nivelServicio,
                stockActual: $stockActual,
            );

            return array_merge($r, [
                'id' => $p->id,
                'cod' => $p->codigo,
                'nom' => $p->nombre,
                'prov' => $p->proveedor?->nombre ?? '—',
                'lead' => $lead,
                'costo_unit' => $costoUnit,
                'stock_actual' => $stockActual,
                'unidades_periodo' => $unidades,
                'sin_demanda' => $unidades <= 0,
            ]);
        });

        return view('livewire.stock.reposicion', [
            'filas' => $filas,
            'spanMeses' => (int) round($spanMeses),
            'stats' => [
                'a_reponer' => $filas->where('reponer_ahora', true)->count(),
                'unidades_sugeridas' => $filas->sum('sugerido_pedir'),
                'costo_total' => round($filas->sum('costo_total_anual'), 2),
            ],
        ]);
    }
}
