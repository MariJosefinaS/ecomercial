<?php

namespace App\Livewire\Devoluciones;

use App\Livewire\Concerns\AutorizaPermisos;
use App\Models\Cliente;
use App\Models\ChequeCliente;
use App\Models\Devolucion;
use App\Models\Local;
use App\Models\MovimientoCliente;
use App\Models\Producto;
use App\Models\StockLocal;
use App\Models\UnidadTrazable;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Devoluciones — E.Comercial')]
class Index extends Component
{
    use AutorizaPermisos;

    public string $buscar = '';
    public string $estado = 'todos';   // todos | pendiente | aprobada | rechazada
    public ?string $mensaje = null;
    public array $efectos = [];        // efectos automáticos de la última aprobación

    // Modal nueva devolución
    public bool $modal = false;
    public string $fCodigo = '';       // código de trazabilidad de la caja (opcional pero recomendado)
    public string $fVenta = '';
    public string $fCliente = '';
    public string $fProducto = '';
    public string $fCant = '1';
    public string $fMonto = '';
    public string $fMotivo = '';
    public string $fMedio = 'cuenta_corriente';
    public string $fCondicion = 'en_condiciones';

    /** condición del producto → estado_producto (seguimiento) al aprobar. */
    private const SEGUIMIENTO = [
        'en_condiciones' => 'reingresado',
        'a_fabrica' => 'enviado_a_fabrica',
        'defectuoso' => 'defectuoso',
    ];

    /** Info de origen de la caja a partir del código (proveedor + fechas + venta). */
    public function getUnidadInfoProperty(): ?array
    {
        $cod = trim($this->fCodigo);
        if ($cod === '') {
            return null;
        }
        $u = UnidadTrazable::with(['proveedor:id,nombre', 'producto:id,nombre', 'venta:id,numero,fecha,cliente_nombre,local_id'])
            ->where('codigo', $cod)->first();
        if (! $u) {
            return ['error' => 'No existe ese código.'];
        }

        return [
            'producto' => $u->producto?->nombre ?? '—',
            'proveedor' => $u->proveedor?->nombre ?? '—',
            'fecha_ingreso' => optional($u->created_at)->format('d/m/Y') ?? '—',
            'venta' => $u->venta?->numero,
            'fecha_venta' => optional($u->venta?->fecha)->format('d/m/Y') ?? optional($u->entregado_at)->format('d/m/Y') ?? '—',
            'cliente' => $u->venta?->cliente_nombre,
            'estado' => $u->estado,
        ];
    }

    public function nuevaDevolucion(): void
    {
        $this->autorizar('crear_devolucion');
        $this->reset(['fCodigo', 'fVenta', 'fCliente', 'fProducto', 'fMonto', 'fMotivo']);
        $this->fCant = '1';
        $this->fMedio = 'cuenta_corriente';
        $this->fCondicion = 'en_condiciones';
        $this->resetValidation();
        $this->modal = true;
    }

    public function guardarDevolucion(): void
    {
        $this->autorizar('crear_devolucion');
        $this->validate([
            'fCant' => 'required|numeric|min:1', 'fMonto' => 'required|numeric|min:0', 'fMotivo' => 'required',
        ]);

        // Si cargan el código de la caja, de ahí salen producto, venta y cliente.
        $unidad = null;
        if (trim($this->fCodigo) !== '') {
            $unidad = UnidadTrazable::with(['venta.cliente', 'producto'])->where('codigo', trim($this->fCodigo))->first();
            if (! $unidad) {
                $this->addError('fCodigo', 'No existe ese código de trazabilidad.');
                return;
            }
        }

        $cliente = $unidad?->venta?->cliente
            ?? Cliente::where('nombre', 'like', "%{$this->fCliente}%")->first();
        if (! $cliente) {
            $this->addError('fCliente', 'Cargá el código de la caja o un cliente válido.');
            return;
        }

        $venta = $unidad?->venta ?? Venta::where('numero', $this->fVenta)->first();
        $productoId = $unidad?->producto_id ?? Producto::where('nombre', 'like', "%{$this->fProducto}%")->first()?->id;
        $productoNombre = $unidad?->producto?->nombre ?? $this->fProducto;

        Devolucion::create([
            'cliente_id' => $cliente->id,
            'venta_id' => $venta?->id,
            'unidad_id' => $unidad?->id,
            'producto_id' => $productoId,
            'producto' => $productoNombre,
            'cantidad' => (int) $this->fCant,
            'monto' => (float) $this->fMonto,
            'motivo' => $this->fMotivo,
            'medio_pago' => $this->fMedio,
            'condicion' => $this->fCondicion,
            'fecha' => now(),
            'estado' => 'pendiente',
        ]);

        $this->modal = false;
        $this->mensaje = "Devolución registrada para {$cliente->nombre}" . ($venta ? " ({$venta->numero})" : '') . '. Pendiente de aprobación.';
        $this->efectos = [];
    }

    /** Aprobar = anula la venta y dispara los efectos automáticos persistidos. */
    public function aprobar(int $id): void
    {
        $this->autorizar('aprobar_devoluciones');
        $dev = Devolucion::with(['cliente', 'venta'])->find($id);
        if (! $dev || $dev->estado !== 'pendiente') {
            return;
        }

        $efectos = [];
        DB::transaction(function () use ($dev, &$efectos) {
            $monto = '$' . number_format((float) $dev->monto, 2, ',', '.');

            // 1) Nota de crédito en la cuenta del cliente (haber)
            MovimientoCliente::create([
                'cliente_id' => $dev->cliente_id,
                'tipo' => 'haber',
                'concepto' => 'Nota de crédito por devolución' . ($dev->venta ? " ({$dev->venta->numero})" : ''),
                'monto' => $dev->monto,
                'fecha' => now(),
                'referencia' => $dev->venta?->numero,
            ]);
            $efectos[] = "Nota de crédito {$monto} en la cuenta de {$dev->cliente?->nombre} (haber).";

            // 2) Seguimiento + stock según condición
            $seg = self::SEGUIMIENTO[$dev->condicion] ?? 'reingresado';
            if ($dev->condicion === 'en_condiciones') {
                if ($dev->producto_id) {
                    $localId = $dev->venta?->local_id ?? Local::orderBy('id')->value('id');
                    $sl = StockLocal::firstOrCreate(
                        ['producto_id' => $dev->producto_id, 'local_id' => $localId],
                        ['cantidad' => 0, 'stock_minimo' => 0, 'precio_venta' => 0]
                    );
                    $sl->increment('cantidad', (int) $dev->cantidad);
                }
                $efectos[] = "Reingreso a stock: {$dev->producto} x{$dev->cantidad} (producto en condiciones).";
            } elseif ($dev->condicion === 'a_fabrica') {
                $efectos[] = 'Producto enviado a fábrica: NO reingresa a stock hasta su reparación.';
            } else {
                $efectos[] = 'Producto dado de baja: defectuoso, no retornable (no reingresa a stock).';
            }

            // 3) Reversión del cobro según el medio de pago
            if ($dev->medio_pago === 'cheque' && $dev->cliente_id) {
                $anulados = ChequeCliente::where('cliente_id', $dev->cliente_id)
                    ->where('estado', 'pendiente')
                    ->whereDate('fecha_vencimiento', '>=', now()->toDateString())
                    ->update(['estado' => 'rechazado', 'motivo_rechazo' => 'Anulado por devolución']);
                $efectos[] = $anulados
                    ? "Cheques futuros del cliente ANULADOS ({$anulados}): no se depositarán."
                    : 'Pago con cheque: no había cheques futuros pendientes para anular.';
            } else {
                $efectos[] = match ($dev->medio_pago) {
                    'diario' => 'Cobro DIARIO revertido para esta venta.',
                    'semanal' => 'Cobro SEMANAL revertido para esta venta.',
                    'cuenta_corriente' => 'Cargo en cuenta corriente revertido (nota de crédito).',
                    default => "Pago ({$dev->medio_pago}) reintegrado al cliente.",
                };
            }

            // Trazabilidad: la caja devuelta cambia de estado según su condición.
            if ($dev->unidad_id) {
                $u = UnidadTrazable::find($dev->unidad_id);
                if ($u) {
                    $nuevo = match ($dev->condicion) {
                        'en_condiciones' => UnidadTrazable::EN_STOCK,   // reingresa, vendible de nuevo
                        'a_fabrica' => UnidadTrazable::EN_REPARACION,
                        default => UnidadTrazable::BAJA,                // defectuoso
                    };
                    $u->update(['estado' => $nuevo, 'devuelto_at' => now()]);
                    $u->registrar('devolucion', $dev->venta?->local_id ?? $u->local_id, $dev->venta?->numero, 'Condición: ' . $dev->condicion);
                }
            }

            $dev->update(['estado' => 'aprobada', 'estado_producto' => $seg]);
        });

        $this->efectos = $efectos;
        $this->mensaje = null;
    }

    public function rechazar(int $id): void
    {
        $this->autorizar('aprobar_devoluciones');
        Devolucion::where('id', $id)->where('estado', 'pendiente')->update(['estado' => 'rechazada']);
        $this->efectos = [];
        $this->mensaje = 'Devolución rechazada.';
    }

    public function marcarReparado(int $id): void
    {
        $this->autorizar('aprobar_devoluciones');
        $dev = Devolucion::find($id);
        if (! $dev) {
            return;
        }
        DB::transaction(function () use ($dev) {
            if ($dev->producto_id) {
                $localId = $dev->venta?->local_id ?? Local::orderBy('id')->value('id');
                $sl = StockLocal::firstOrCreate(
                    ['producto_id' => $dev->producto_id, 'local_id' => $localId],
                    ['cantidad' => 0, 'stock_minimo' => 0, 'precio_venta' => 0]
                );
                $sl->increment('cantidad', (int) $dev->cantidad);
            }
            $dev->update(['estado_producto' => 'reingresado']);
        });
        $this->efectos = [];
        $this->mensaje = 'Producto reparado y reingresado a stock.';
    }

    public function marcarDefectuoso(int $id): void
    {
        $this->autorizar('aprobar_devoluciones');
        Devolucion::where('id', $id)->update(['estado_producto' => 'defectuoso']);
        $this->efectos = [];
        $this->mensaje = 'Producto marcado como defectuoso (baja).';
    }

    public function render()
    {
        // El vendedor solo ve las devoluciones de SUS ventas; admins ven todas.
        $soloPropias = auth()->user()?->esRol('vendedor');
        $deVendedor = fn ($q) => $q->whereHas('venta', fn ($v) => $v->where('vendedor_id', auth()->id()));

        $filas = Devolucion::with(['cliente:id,nombre', 'venta:id,numero'])
            ->when($soloPropias, $deVendedor)
            ->when($this->buscar !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('producto', 'like', "%{$this->buscar}%")
                ->orWhereHas('cliente', fn ($c) => $c->where('nombre', 'like', "%{$this->buscar}%"))
                ->orWhereHas('venta', fn ($v) => $v->where('numero', 'like', "%{$this->buscar}%"))))
            ->when($this->estado !== 'todos', fn ($q) => $q->where('estado', $this->estado))
            ->orderByDesc('id')
            ->get()
            ->map(fn (Devolucion $d) => [
                'id' => $d->id,
                'fecha' => $d->fecha?->format('d/m/Y'),
                'cliente' => $d->cliente?->nombre ?? '—',
                'venta' => $d->venta?->numero ?? '—',
                'producto' => $d->producto ?? '—',
                'cant' => (int) $d->cantidad,
                'monto' => (float) $d->monto,
                'medio' => $d->medio_pago ?? '—',
                'condicion' => $d->condicion,
                'motivo' => $d->motivo,
                'estado' => $d->estado,
                'seguimiento' => $d->estado_producto,
            ]);

        $scoped = fn () => Devolucion::when($soloPropias, $deVendedor);

        return view('livewire.devoluciones.index', [
            'filas' => $filas,
            'stats' => [
                'pendientes' => $scoped()->where('estado', 'pendiente')->count(),
                'aprobadas' => $scoped()->where('estado', 'aprobada')->count(),
                'a_fabrica' => $scoped()->whereIn('estado_producto', ['enviado_a_fabrica', 'en_reparacion'])->count(),
                'monto' => (float) $scoped()->where('estado', 'aprobada')->sum('monto'),
            ],
        ]);
    }
}
