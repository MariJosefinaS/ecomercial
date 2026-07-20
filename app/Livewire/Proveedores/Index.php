<?php

namespace App\Livewire\Proveedores;

use App\Livewire\Concerns\AutorizaPermisos;
use App\Models\ConceptoPrecio;
use App\Models\Devolucion;
use App\Models\PagoProveedor;
use App\Models\Proveedor;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Proveedores — E.Comercial')]
class Index extends Component
{
    use AutorizaPermisos;

    public string $buscar = '';
    public string $rubro = 'todos';
    public ?int $sel = null;          // proveedor abierto (ficha)
    public string $tab = 'cuenta';    // cuenta | pedidos | compras | pagos | devoluciones | conceptos
    public ?string $mensaje = null;

    /** Conceptos a cobrar del proveedor abierto: [id, nombre, ambito, aplica, porcentaje] (DB pivot). */
    public array $conceptosProv = [];

    // ===== Modal alta / edición de proveedor (DB) =====
    public bool $modal = false;
    public ?int $editId = null;
    public string $fNombre = '';
    public string $fRubro = '';
    public string $fCuit = '';
    public string $fTel = '';
    public string $fEmail = '';
    public string $fDir = '';
    public string $fDias = '0';
    // Costeo del proveedor (ver App\Support\Costeo). El remarque/ganancia es ahora un concepto
    // de ámbito 'venta' que se ajusta en la solapa "Conceptos a cobrar".
    public bool $fCosteaIva = false;     // ¿el IVA entra al costo? (RI con crédito fiscal = false)
    public string $fIvaPct = '21';       // alícuota de IVA si costea con IVA

    /** Compra estado → estado de "pedido" (para el badge de la ficha). */
    private const PEDIDO_ESTADO = ['pendiente' => 'pendiente', 'aprobada' => 'en_camino', 'recibida' => 'recibido', 'rechazada' => 'pendiente'];

    /** Deuda = saldo de cuentas por pagar (monto - monto_pagado). */
    private function deudaDe(int $provId): float
    {
        return (float) PagoProveedor::where('proveedor_id', $provId)
            ->get()->sum(fn (PagoProveedor $p) => max(0, (float) $p->monto - (float) $p->monto_pagado));
    }

    /** Datos básicos del proveedor para la lista (sin la ficha pesada). */
    private function base(Proveedor $p): array
    {
        return [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'rubro' => $p->rubro ?: 'Sin rubro',
            'cuit' => $p->cuit ?: '—',
            'tel' => $p->telefono ?: '—',
            'email' => $p->email ?: '—',
            'dir' => $p->direccion ?: '—',
            'dias' => $p->dias_entrega ?? 0,
            'deuda' => $this->deudaDe($p->id),
        ];
    }

    /** Ficha completa con datos ricos REALES desde la DB. */
    private function ficha(Proveedor $p): array
    {
        $base = $this->base($p);
        $f = fn ($d) => $d?->format('d/m/Y');

        $compras = $p->compras()->with('items.producto:id,nombre')->orderByDesc('fecha')->orderByDesc('id')->get();
        $pagos = PagoProveedor::with('compra:id,numero')->where('proveedor_id', $p->id)->orderByDesc('fecha_pago')->orderByDesc('id')->get();

        // Movimientos de cuenta: compras (haber) + pagos (debe), ordenados por fecha.
        $movimientos = collect();
        foreach ($compras as $c) {
            $movimientos->push(['fecha_raw' => $c->fecha, 'fecha' => $f($c->fecha), 'tipo' => 'haber', 'concepto' => 'Compra ' . $c->numero, 'monto' => (float) $c->total]);
        }
        foreach ($pagos as $pg) {
            if ((float) $pg->monto_pagado > 0) {
                $fch = $pg->fecha_pago ?? $pg->fecha_vencimiento;
                $movimientos->push(['fecha_raw' => $fch, 'fecha' => $f($fch), 'tipo' => 'debe', 'concepto' => 'Pago ' . ($pg->compra?->numero ?? ''), 'monto' => (float) $pg->monto_pagado]);
            }
        }
        $base['movimientos'] = $movimientos->sortBy('fecha_raw')->map(fn ($m) => collect($m)->except('fecha_raw')->all())->values()->all();

        // Pedidos (= compras con su seguimiento de fechas/estado)
        $base['pedidos'] = $compras->map(fn ($c) => [
            'oc' => $c->numero,
            'fecha' => $f($c->fecha),
            'estado' => self::PEDIDO_ESTADO[$c->estado] ?? 'pendiente',
            'estimada' => $f($c->fecha_estimada),
            'llegada' => $f($c->fecha_llegada),
        ])->all();

        // Compras con artículos de cada factura
        $base['compras'] = $compras->map(fn ($c) => [
            'fac' => $c->factura_numero ?: $c->numero,
            'fecha' => $f($c->fecha),
            'monto' => (float) $c->total,
            'items' => $c->items->map(fn ($it) => [
                'prod' => $it->producto?->nombre ?? '—',
                'cant' => (int) $it->cantidad,
                'costo' => (float) $it->costo_unitario,
            ])->all(),
        ])->all();

        // Pagos realizados
        $base['pagos'] = $pagos->filter(fn ($pg) => (float) $pg->monto_pagado > 0)->map(fn ($pg) => [
            'fecha' => $f($pg->fecha_pago ?? $pg->fecha_vencimiento),
            'medio' => ucfirst($pg->estado),
            'comp' => $pg->compra?->numero ?? '—',
            'monto' => (float) $pg->monto_pagado,
        ])->values()->all();

        // Devoluciones de productos de este proveedor con seguimiento a fábrica/reparación
        $base['devoluciones'] = Devolucion::whereNotNull('estado_producto')
            ->whereHas('productoRel', fn ($q) => $q->where('proveedor_id', $p->id))
            ->orderByDesc('id')->get()
            ->map(fn (Devolucion $d) => [
                'prod' => $d->producto ?? '—',
                'cant' => (int) $d->cantidad,
                'motivo' => $d->motivo,
                'estado' => $d->estado_producto,
            ])->all();

        return $base;
    }

    public function abrir(int $id): void
    {
        $this->autorizar('ver_ficha_proveedor');
        $this->sel = $id;
        $this->tab = 'cuenta';
        $this->mensaje = null;
        $this->cargarConceptos($id);
    }

    public function volver(): void { $this->sel = null; }
    public function setTab(string $t): void { $this->tab = $t; }

    // ===== CRUD de proveedor (DB) =====
    public function nuevoProveedor(): void
    {
        $this->autorizar('gestionar_proveedores');
        $this->editId = null;
        $this->reset(['fNombre', 'fRubro', 'fCuit', 'fTel', 'fEmail', 'fDir']);
        $this->fDias = '0';
        $this->fCosteaIva = false;
        $this->fIvaPct = '21';
        $this->resetValidation();
        $this->modal = true;
    }

    public function editarProveedor(int $id): void
    {
        $this->autorizar('gestionar_proveedores');
        $p = Proveedor::find($id);
        if (! $p) {
            return;
        }
        $this->editId = $p->id;
        $this->fNombre = $p->nombre;
        $this->fRubro = $p->rubro ?? '';
        $this->fCuit = $p->cuit ?? '';
        $this->fTel = $p->telefono ?? '';
        $this->fEmail = $p->email ?? '';
        $this->fDir = $p->direccion ?? '';
        $this->fDias = (string) ($p->dias_entrega ?? 0);
        $this->fCosteaIva = (bool) $p->costea_con_iva;
        $this->fIvaPct = (string) (float) ($p->iva_pct ?? 21);
        $this->resetValidation();
        $this->modal = true;
    }

    public function guardarProveedor(): void
    {
        $this->autorizar('gestionar_proveedores');
        $this->validate([
            'fNombre' => 'required|min:2',
            'fEmail' => 'nullable|email',
            'fDias' => 'numeric|min:0',
            'fIvaPct' => 'numeric|min:0',
        ], attributes: ['fNombre' => 'nombre', 'fEmail' => 'email', 'fDias' => 'días de entrega', 'fIvaPct' => 'IVA']);

        $attrs = [
            'nombre' => $this->fNombre,
            'rubro' => $this->fRubro ?: null,
            'cuit' => $this->fCuit ?: null,
            'telefono' => $this->fTel ?: null,
            'email' => $this->fEmail ?: null,
            'direccion' => $this->fDir ?: null,
            'dias_entrega' => (int) $this->fDias,
            'costea_con_iva' => $this->fCosteaIva,
            'iva_pct' => (float) ($this->fIvaPct ?: 0),
        ];

        if ($this->editId) {
            Proveedor::where('id', $this->editId)->update($attrs);
            $msg = "Proveedor «{$this->fNombre}» actualizado.";
        } else {
            $prov = Proveedor::create($attrs + ['activo' => true]);
            // Arranca con los conceptos activos por defecto (igual que los proveedores sembrados).
            $defaults = ConceptoPrecio::where('activo', true)->get()
                ->mapWithKeys(fn ($c) => [$c->id => ['porcentaje' => $c->porcentaje]])->all();
            if ($defaults) {
                $prov->conceptos()->sync($defaults);
            }
            $msg = "Proveedor «{$this->fNombre}» creado. Revisá sus conceptos a cobrar abajo.";

            // Lo abrimos directo en su ficha → solapa Conceptos (donde se ajustan).
            $this->sel = $prov->id;
            $this->tab = 'conceptos';
            $this->cargarConceptos($prov->id);
        }

        $this->modal = false;
        $this->editId = null;
        $this->mensaje = $msg;
    }

    /** Carga la matriz de conceptos del proveedor desde la DB (tildados los que cobra, con su %). */
    private function cargarConceptos(int $provId): void
    {
        $prov = Proveedor::with('conceptos')->find($provId);
        $asignados = $prov ? $prov->conceptos->keyBy('id') : collect();

        $this->conceptosProv = ConceptoPrecio::where('activo', true)->orderBy('orden')->get()->map(function ($c) use ($asignados) {
            $piv = $asignados->get($c->id);
            $pct = (float) ($piv?->pivot->porcentaje ?? $c->porcentaje);

            return [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'ambito' => $c->ambito ?? 'costo',
                'aplica' => (bool) $piv,
                'porcentaje' => rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.') ?: '0',
            ];
        })->all();
    }

    public function guardarConceptos(): void
    {
        $this->autorizar('gestionar_proveedores');

        $prov = Proveedor::find($this->sel);
        if (! $prov) {
            return;
        }

        $sync = [];
        foreach ($this->conceptosProv as $c) {
            if (! empty($c['aplica'])) {
                $sync[$c['id']] = ['porcentaje' => (float) ($c['porcentaje'] ?: 0)];
            }
        }
        $prov->conceptos()->sync($sync);
        $this->mensaje = 'Conceptos a cobrar del proveedor guardados.';
    }

    public function render()
    {
        $base = Proveedor::orderBy('nombre')->get()->map(fn (Proveedor $p) => $this->base($p));
        $sel = $this->sel ? Proveedor::find($this->sel) : null;

        $filas = $base->filter(function (array $p) {
            if ($this->buscar !== '') {
                $q = mb_strtolower($this->buscar);
                if (! str_contains(mb_strtolower($p['nombre']), $q) && ! str_contains(mb_strtolower($p['cuit']), $q)) {
                    return false;
                }
            }
            if ($this->rubro !== 'todos' && $p['rubro'] !== $this->rubro) {
                return false;
            }
            return true;
        })->values()->all();

        return view('livewire.proveedores.index', [
            'filas' => $filas,
            'proveedor' => $sel ? $this->ficha($sel) : null,
            'rubros' => $base->pluck('rubro')->unique()->reject(fn ($r) => $r === 'Sin rubro')->values()->all(),
            'stats' => [
                'total' => $base->count(),
                'con_deuda' => $base->filter(fn ($p) => $p['deuda'] > 0)->count(),
                'deuda_total' => $base->sum('deuda'),
            ],
        ]);
    }
}
