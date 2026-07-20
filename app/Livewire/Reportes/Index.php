<?php

namespace App\Livewire\Reportes;

use App\Models\Local;
use App\Models\Producto;
use App\Models\Venta;
use App\Models\VentaItem;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Reportes — E.Comercial')]
class Index extends Component
{
    public string $periodo = 'mes';   // mes | trimestre | anio

    #[Url(as: 'sub')]
    public string $sub = 'ranking';   // ranking | locales | tendencia | productos

    private const MESES = [1 => 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    /** Rango de fechas según el período elegido. */
    private function rango(): array
    {
        $hoy = Carbon::today();

        return match ($this->periodo) {
            'trimestre' => [$hoy->copy()->startOfMonth()->subMonthsNoOverflow(2), $hoy->copy()->endOfMonth()],
            'anio' => [$hoy->copy()->startOfYear(), $hoy->copy()->endOfYear()],
            default => [$hoy->copy()->startOfMonth(), $hoy->copy()->endOfMonth()],
        };
    }

    public function render()
    {
        [$desde, $hasta] = $this->rango();
        $aprobadas = fn () => Venta::where('estado', 'aprobada')->whereBetween('fecha', [$desde, $hasta]);

        // ===== Ranking de vendedores (monto + unidades) =====
        $montos = (clone $aprobadas())
            ->selectRaw('vendedor_id, SUM(total) as monto, COUNT(*) as ops')
            ->groupBy('vendedor_id')->orderByDesc('monto')->get();

        $unidades = VentaItem::query()
            ->join('ventas', 'ventas.id', '=', 'venta_items.venta_id')
            ->where('ventas.estado', 'aprobada')->whereBetween('ventas.fecha', [$desde, $hasta])
            ->groupBy('ventas.vendedor_id')
            ->selectRaw('ventas.vendedor_id, SUM(venta_items.cantidad) as uni')
            ->pluck('uni', 'vendedor_id');

        $nombres = \App\Models\User::whereIn('id', $montos->pluck('vendedor_id'))->pluck('name', 'id');

        $ranking = $montos->take(6)->values()->map(function ($r, $i) use ($nombres, $unidades) {
            $nom = $nombres[$r->vendedor_id] ?? '—';
            $ini = collect(explode(' ', $nom))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: 'NN';

            return [
                'ini' => $ini,
                'vv' => $i === 0 ? 'brand' : ($i % 2 ? 'gray' : 'blue'),
                'nom' => $nom,
                'total' => (float) $r->monto,
                'uni' => (int) ($unidades[$r->vendedor_id] ?? 0),
            ];
        })->all();
        $maxRank = max(1, ...array_map(fn ($r) => $r['total'], $ranking ?: [['total' => 1]]));

        // ===== Ventas por local (primeros 2 locales → A/B) =====
        $porLocalRaw = (clone $aprobadas())->selectRaw('local_id, SUM(total) as t')->groupBy('local_id')->pluck('t', 'local_id');
        $locales = Local::orderBy('id')->take(2)->get();
        $porLocal = [
            'A' => (float) ($porLocalRaw[$locales[0]->id ?? 0] ?? 0),
            'B' => (float) ($porLocalRaw[$locales[1]->id ?? 0] ?? 0),
        ];

        // ===== Tendencia: últimos 6 meses (independiente del período) =====
        $tendencia = [];
        $hoy = Carbon::today();
        for ($m = 5; $m >= 0; $m--) {
            $ini = $hoy->copy()->startOfMonth()->subMonthsNoOverflow($m);
            $fin = $ini->copy()->endOfMonth();
            $v = (float) Venta::where('estado', 'aprobada')->whereBetween('fecha', [$ini, $fin])->sum('total');
            $tendencia[] = ['m' => self::MESES[$ini->month], 'v' => $v];
        }
        $maxTend = max(1, ...array_column($tendencia, 'v'));

        // ===== Top productos (unidades + importe) =====
        $topProductos = VentaItem::query()
            ->join('ventas', 'ventas.id', '=', 'venta_items.venta_id')
            ->where('ventas.estado', 'aprobada')->whereBetween('ventas.fecha', [$desde, $hasta])
            ->groupBy('venta_items.producto_id')
            ->selectRaw('venta_items.producto_id, SUM(venta_items.cantidad) as uni, SUM(venta_items.cantidad * venta_items.precio_unitario) as total')
            ->orderByDesc('total')->limit(5)->get();

        $prodInfo = Producto::with('categoria:id,icono')->whereIn('id', $topProductos->pluck('producto_id'))->get()->keyBy('id');
        $topProductos = $topProductos->map(fn ($t) => [
            'nom' => $prodInfo[$t->producto_id]?->nombre ?? '—',
            'icon' => $prodInfo[$t->producto_id]?->categoria?->icono ?? 'inventory_2',
            'uni' => (int) $t->uni,
            'total' => (float) $t->total,
        ])->all();

        // ===== Stats =====
        $monto = (float) (clone $aprobadas())->sum('total');
        $operaciones = (int) (clone $aprobadas())->count();
        $unidadesTot = (int) VentaItem::query()
            ->join('ventas', 'ventas.id', '=', 'venta_items.venta_id')
            ->where('ventas.estado', 'aprobada')->whereBetween('ventas.fecha', [$desde, $hasta])
            ->sum('venta_items.cantidad');

        return view('livewire.reportes.index', [
            'ranking' => $ranking,
            'maxRank' => $maxRank,
            'tendencia' => $tendencia,
            'maxTend' => $maxTend,
            'topProductos' => $topProductos,
            'porLocal' => $porLocal,
            'stats' => [
                'monto' => $monto,
                'unidades' => $unidadesTot,
                'operaciones' => $operaciones,
                'ticket' => $operaciones ? (int) round($monto / $operaciones) : 0,
            ],
        ]);
    }
}
