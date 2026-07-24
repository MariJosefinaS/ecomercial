<?php

namespace App\Livewire\Compras;

use App\Livewire\Concerns\AutorizaPermisos;
use App\Models\Compra;
use App\Models\CompraItem;
use App\Models\Local;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\SolicitudCompra;
use App\Models\StockLocal;
use App\Support\Reposicion;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Compras — E.Comercial')]
class Index extends Component
{
    use AutorizaPermisos;

    #[Url(as: 'sub')]
    public string $tab = 'ordenes';    // ordenes | solicitudes

    public string $buscar = '';
    public string $estado = 'todos';   // todos | pendiente | aprobada | recibida | rechazada
    public string $local = 'todos';
    public ?string $mensaje = null;

    // ===== Solicitudes de reposición (el paso previo a la orden de compra) =====
    public string $solEstado = 'pendiente';   // pendiente | aprobada | convertida | rechazada | todos
    /** @var array<int,bool> */
    public array $solSel = [];
    public ?int $solRechazandoId = null;
    public string $solMotivo = '';

    #[Url]
    public ?string $highlight = null;

    // ===== Modal ver detalle de ítems =====
    public bool $verModal = false;
    public ?array $verData = null;

    // ===== Modal registrar compra =====
    public bool $modal = false;
    public ?int $cProvId = null;
    public ?int $cLocalId = null;
    public string $cFactura = '';
    public array $cItems = [];          // [producto_id, cod, desc, cant, precio]
    public ?int $buscandoEn = null;
    // Desglose de la factura (precios/IVA/flete).
    public string $cIva21 = '';
    public string $cIva105 = '';
    public string $cFlete = '';

    public function mount(): void
    {
        if (request()->boolean('nuevo') && $this->puede('crear_compra')) {
            $this->registrarCompra();
        }
    }

    private function localesActivos()
    {
        return Local::where('activo', true)->orderBy('id')->get(['id', 'nombre']);
    }

    // ===== Buscador de productos (renglones) =====
    public function getResultadosProperty(): array
    {
        if ($this->buscandoEn === null) {
            return [];
        }
        $q = trim($this->cItems[$this->buscandoEn]['desc'] ?? '');
        if (mb_strlen($q) < 2) {
            return [];
        }

        return Producto::query()
            ->with('proveedor:id,nombre')
            ->where(fn ($w) => $w
                ->where('nombre', 'like', "%{$q}%")
                ->orWhere('codigo', 'like', "%{$q}%")
                ->orWhereHas('proveedor', fn ($p) => $p->where('nombre', 'like', "%{$q}%")))
            ->limit(8)->get()
            ->map(fn (Producto $p) => [
                'id' => $p->id,
                'cod' => $p->codigo,
                'nom' => $p->nombre,
                'prov' => $p->proveedor?->nombre ?? '—',
                'costo' => (float) ($p->precio_neto ?? $p->precio_compra),
            ])->all();
    }

    public function updatedCItems($value, $key): void
    {
        [$i, $campo] = array_pad(explode('.', (string) $key), 2, null);
        if ($campo === 'desc') {
            $this->buscandoEn = (int) $i;
        }
    }

    public function elegirProducto(int $i, int $productoId): void
    {
        $p = Producto::find($productoId);
        if (! $p) {
            return;
        }
        $this->cItems[$i]['producto_id'] = $p->id;
        $this->cItems[$i]['cod'] = $p->codigo;
        $this->cItems[$i]['desc'] = $p->nombre;
        // El renglón de compra usa el NETO (la factura suma IVA/flete aparte).
        $neto = (float) ($p->precio_neto ?? $p->precio_compra);
        if ($neto > 0) {
            $this->cItems[$i]['precio'] = $neto;
        }
        $this->buscandoEn = null;
    }

    public function cerrarBusqueda(): void
    {
        $this->buscandoEn = null;
    }

    // ===== Alta de compra =====
    public function registrarCompra(): void
    {
        $this->autorizar('crear_compra');
        $this->reset(['cProvId', 'cFactura', 'cItems', 'buscandoEn', 'cIva21', 'cIva105', 'cFlete']);
        $this->cLocalId = $this->localesActivos()->first()?->id;
        $this->cItems = [['producto_id' => null, 'cod' => '', 'desc' => '', 'cant' => 1, 'precio' => '']];
        $this->resetValidation();
        $this->modal = true;
    }

    public function agregarItem(): void
    {
        $this->cItems[] = ['producto_id' => null, 'cod' => '', 'desc' => '', 'cant' => 1, 'precio' => ''];
    }

    public function quitarItem(int $i): void
    {
        unset($this->cItems[$i]);
        $this->cItems = array_values($this->cItems);
        if (empty($this->cItems)) {
            $this->cItems = [['producto_id' => null, 'cod' => '', 'desc' => '', 'cant' => 1, 'precio' => '']];
        }
        $this->buscandoEn = null;
    }

    public function getTotalCompraProperty(): float
    {
        return array_sum(array_map(
            fn ($it) => (float) ($it['cant'] ?: 0) * (float) ($it['precio'] ?: 0),
            $this->cItems
        ));
    }

    public function guardarCompra(): void
    {
        $this->autorizar('crear_compra');
        $this->validate([
            'cProvId' => 'required',
            'cLocalId' => 'required',
            'cFactura' => 'required|string|max:60',
            'cIva21' => 'nullable|numeric|min:0',
            'cIva105' => 'nullable|numeric|min:0',
            'cFlete' => 'nullable|numeric|min:0',
            'cItems' => 'required|array|min:1',
            'cItems.*.producto_id' => 'required',
            'cItems.*.cant' => 'required|numeric|min:1',
            'cItems.*.precio' => 'required|numeric|min:0',
        ], messages: [
            'cItems.*.producto_id.required' => 'Elegí un producto del buscador en cada renglón.',
        ], attributes: [
            'cProvId' => 'proveedor', 'cLocalId' => 'local', 'cFactura' => 'N° de factura',
            'cIva21' => 'IVA 21%', 'cIva105' => 'IVA 10,5%', 'cFlete' => 'flete/otros',
            'cItems.*.cant' => 'cantidad', 'cItems.*.precio' => 'costo',
        ]);

        $maxNum = (int) (Compra::selectRaw("MAX(CAST(REGEXP_REPLACE(numero, '[^0-9]', '') AS UNSIGNED)) as n")->value('n') ?? 317);
        $num = 'OC-' . ($maxNum + 1);

        $desglose = [
            'subtotal' => round($this->totalCompra, 2),
            'iva21' => (float) ($this->cIva21 ?: 0),
            'iva105' => (float) ($this->cIva105 ?: 0),
            'flete' => (float) ($this->cFlete ?: 0),
        ];
        $desglose['total'] = round($desglose['subtotal'] + $desglose['iva21'] + $desglose['iva105'] + $desglose['flete'], 2);

        DB::transaction(function () use ($num, $desglose) {
            $compra = Compra::create([
                'numero' => $num,
                'proveedor_id' => $this->cProvId,
                'local_id' => $this->cLocalId,
                'usuario_id' => auth()->id(),
                'factura_numero' => $this->cFactura,
                'fecha' => now(),
                'total' => $this->totalCompra,
                'desglose' => $desglose,
                'estado' => 'pendiente',
            ]);
            foreach ($this->cItems as $it) {
                CompraItem::create([
                    'compra_id' => $compra->id,
                    'producto_id' => $it['producto_id'],
                    'cantidad' => (int) $it['cant'],
                    'costo_unitario' => (float) $it['precio'],
                ]);
            }
        });

        $this->modal = false;
        $this->mensaje = "Compra {$num} registrada (pendiente de aprobación).";
    }

    public function setTab(string $t): void
    {
        $this->tab = $t;
        $this->solSel = [];
    }

    // ===================================================================
    //  Solicitudes de reposición → orden de compra
    // ===================================================================
    public function aprobarSolicitud(int $id): void
    {
        $this->autorizar('aprobar_compras');
        $s = SolicitudCompra::find($id);
        if ($s && Reposicion::aprobar($s, auth()->id())) {
            $this->mensaje = "Solicitud {$s->numero} aprobada. Ya se puede convertir en orden de compra.";
        }
    }

    public function pedirRechazoSolicitud(int $id): void
    {
        $this->autorizar('aprobar_compras');
        $this->solRechazandoId = $id;
        $this->solMotivo = '';
    }

    public function rechazarSolicitud(): void
    {
        $this->autorizar('aprobar_compras');
        $s = SolicitudCompra::find($this->solRechazandoId);
        if ($s && Reposicion::rechazar($s, auth()->id(), $this->solMotivo)) {
            $this->mensaje = "Solicitud {$s->numero} rechazada.";
        }
        $this->reset(['solRechazandoId', 'solMotivo']);
    }

    public function reabrirSolicitud(int $id): void
    {
        $this->autorizar('aprobar_compras');
        $s = SolicitudCompra::find($id);
        if ($s && Reposicion::reabrir($s)) {
            $this->mensaje = "Solicitud {$s->numero} vuelta a pendiente.";
        }
    }

    /** Convierte las solicitudes tildadas en órdenes de compra (una por proveedor + sucursal). */
    public function convertirSeleccionadas(): void
    {
        $this->autorizar('aprobar_compras');
        $ids = array_keys(array_filter($this->solSel));
        if (empty($ids)) {
            $this->mensaje = 'Tildá al menos una solicitud aprobada para convertir.';

            return;
        }
        $r = Reposicion::convertirEnCompras($ids, auth()->id());
        $this->solSel = [];
        $this->mensaje = $r['mensaje'];
        if ($r['convertidas'] > 0) {
            $this->tab = 'ordenes';
            $this->estado = 'pendiente';
        }
    }

    /** Tilda de una todas las solicitudes aprobadas que se están mostrando. */
    public function seleccionarTodas(): void
    {
        $this->solSel = SolicitudCompra::where('estado', 'aprobada')->whereNull('compra_id')
            ->pluck('id')->mapWithKeys(fn ($id) => [$id => true])->all();
    }

    public function aprobar(int $id): void
    {
        $this->autorizar('aprobar_compras');
        Compra::where('id', $id)->update(['estado' => 'aprobada']);
        $this->mensaje = 'Compra aprobada. Lista para recibir.';
    }

    public function rechazar(int $id): void
    {
        $this->autorizar('aprobar_compras');
        Compra::where('id', $id)->update(['estado' => 'rechazada']);
        $this->mensaje = 'Compra rechazada.';
    }

    /** Recibir la mercadería: suma al stock del local de la compra. */
    public function recibir(int $id): void
    {
        $this->autorizar('aprobar_compras');

        $compra = Compra::with('items')->find($id);
        if (! $compra || $compra->estado === 'recibida') {
            return;
        }

        DB::transaction(function () use ($compra) {
            foreach ($compra->items as $it) {
                $sl = StockLocal::firstOrCreate(
                    ['producto_id' => $it->producto_id, 'local_id' => $compra->local_id],
                    ['cantidad' => 0, 'stock_minimo' => 0, 'precio_venta' => 0]
                );
                $sl->increment('cantidad', $it->cantidad);
            }
            $compra->update(['estado' => 'recibida']);
        });

        $this->mensaje = "Compra {$compra->numero} recibida — stock actualizado en {$compra->local->nombre}.";
    }

    /** Modal de detalle: qué llegará (aprobada) o qué se recibió (recibida). */
    public function verItems(int $id): void
    {
        $compra = Compra::with(['items.producto:id,nombre,codigo', 'proveedor:id,nombre', 'local:id,nombre'])->find($id);
        if (! $compra) {
            return;
        }

        $this->verData = [
            'numero' => $compra->numero,
            'estado' => $compra->estado,
            'prov' => $compra->proveedor?->nombre ?? '—',
            'local' => $compra->local?->nombre ?? '—',
            'factura' => $compra->factura_numero ?: null,
            'recibida' => $compra->estado === 'recibida',
            'recibido_at' => $compra->recibido_at?->format('d/m/Y H:i'),
            'items' => $compra->items->map(fn (CompraItem $it) => [
                'desc' => $it->producto?->nombre ?? '—',
                'cod' => $it->producto?->codigo ?? '—',
                'pedida' => (int) $it->cantidad,
                'recibida' => (int) $it->cantidad_recibida,
                'defectuosa' => (int) $it->cantidad_defectuosa,
                'faltante' => (int) $it->cantidad_faltante,
                'estado' => $it->estado_item,
                'nota' => $it->nota_recepcion,
            ])->all(),
        ];
        $this->verModal = true;
    }

    public function limpiar(): void
    {
        $this->reset(['buscar', 'estado', 'local', 'mensaje']);
        $this->estado = 'todos';
        $this->local = 'todos';
    }

    public function render()
    {
        $filas = Compra::with(['proveedor:id,nombre', 'local:id,nombre'])
            ->withCount('items')
            ->when($this->buscar !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('numero', 'like', "%{$this->buscar}%")
                ->orWhere('factura_numero', 'like', "%{$this->buscar}%")
                ->orWhereHas('proveedor', fn ($p) => $p->where('nombre', 'like', "%{$this->buscar}%"))))
            ->when($this->estado !== 'todos', fn ($q) => $q->where('estado', $this->estado))
            ->when($this->local !== 'todos', fn ($q) => $q->whereHas('local', fn ($l) => $l->where('nombre', $this->local)))
            ->orderByDesc('id')
            ->get()
            ->map(fn (Compra $c) => [
                'id' => $c->id,
                'num' => $c->numero,
                'fecha' => $c->fecha?->format('d/m/Y'),
                'prov' => $c->proveedor?->nombre ?? '—',
                'local' => $c->local?->nombre ?? '—',
                'factura' => $c->factura_numero ?: '—',
                'items' => $c->items_count,
                'total' => (float) $c->total,
                'estado' => $c->estado,
            ]);

        return view('livewire.compras.index', [
            'filas' => $filas,
            'locales' => $this->localesActivos()->pluck('nombre')->all(),
            'localesActivos' => $this->localesActivos(),
            'proveedores' => Proveedor::orderBy('nombre')->get(['id', 'nombre']),
            'stats' => [
                'pendientes' => Compra::where('estado', 'pendiente')->count(),
                'por_recibir' => Compra::where('estado', 'aprobada')->count(),
                'total_recibido' => (float) Compra::where('estado', 'recibida')->sum('total'),
                'solicitudes_pendientes' => SolicitudCompra::where('estado', 'pendiente')->count(),
                'solicitudes_a_convertir' => SolicitudCompra::where('estado', 'aprobada')->whereNull('compra_id')->count(),
            ],
            'solicitudes' => $this->solicitudes(),
        ]);
    }

    /** Solicitudes de reposición para el tab (con su proveedor sugerido y el estado del circuito). */
    private function solicitudes(): array
    {
        if ($this->tab !== 'solicitudes') {
            return [];
        }

        return SolicitudCompra::with(['producto:id,nombre,codigo,proveedor_id,precio_compra', 'producto.proveedor:id,nombre',
            'proveedor:id,nombre', 'local:id,nombre', 'solicitante:id,name', 'compra:id,numero,estado'])
            ->when($this->solEstado !== 'todos', fn ($q) => $q->where('estado', $this->solEstado))
            ->orderByDesc('id')->limit(200)->get()
            ->map(function (SolicitudCompra $s) {
                $prov = $s->proveedorEfectivo();
                $costo = (float) ($s->producto?->precio_compra ?? 0);

                return [
                    'id' => $s->id,
                    'numero' => $s->numero,
                    'producto' => $s->producto?->nombre ?? '—',
                    'codigo' => $s->producto?->codigo ?? '',
                    'proveedor' => $prov?->nombre,
                    'local' => $s->local?->nombre ?? '—',
                    'solicitante' => $s->solicitante?->name ?? '—',
                    'cantidad' => $s->cantidad,
                    'costo_estimado' => round($s->cantidad * $costo, 2),
                    'nota' => $s->nota,
                    'estado' => $s->estado,
                    'estado_label' => $s->estadoLabel(),
                    'motivo' => $s->motivo_rechazo,
                    'compra' => $s->compra?->numero,
                    'compra_estado' => $s->compra?->estado,
                    'fecha' => $s->created_at?->format('d/m/Y'),
                    'convertible' => $s->estado === 'aprobada' && ! $s->compra_id && $prov !== null,
                    'sin_proveedor' => $prov === null,
                ];
            })->all();
    }
}
