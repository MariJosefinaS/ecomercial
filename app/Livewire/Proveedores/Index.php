<?php

namespace App\Livewire\Proveedores;

use App\Livewire\Concerns\AutorizaPermisos;
use App\Models\Compra;
use App\Models\ConceptoPrecio;
use App\Models\Devolucion;
use App\Models\MovimientoCaja;
use App\Models\PagoProveedor;
use App\Models\Proveedor;
use Illuminate\Support\Carbon;
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
        $pagos = PagoProveedor::with('compra:id,numero,factura_numero')->where('proveedor_id', $p->id)->orderByDesc('id')->get();

        // Movimientos de cuenta OBLIGACIÓN-BASED (R2): la deuda nace de la FACTURA (PagoProveedor),
        // NO del remito. Un remito recibido sin factura suma stock pero NO impacta la cta cte.
        $movimientos = collect();
        foreach ($pagos as $pg) {
            $comp = $pg->compra?->factura_numero ?: ($pg->compra?->numero ?? '');
            $movimientos->push(['fecha_raw' => $pg->created_at ?? $pg->fecha_vencimiento, 'fecha' => $f($pg->created_at ?? $pg->fecha_vencimiento), 'tipo' => 'haber', 'concepto' => 'Factura ' . $comp, 'monto' => (float) $pg->monto]);
            if ((float) $pg->monto_pagado > 0) {
                $fch = $pg->fecha_pago ?? $pg->fecha_vencimiento;
                $movimientos->push(['fecha_raw' => $fch, 'fecha' => $f($fch), 'tipo' => 'debe', 'concepto' => 'Pago factura ' . $comp, 'monto' => (float) $pg->monto_pagado]);
            }
        }
        $base['movimientos'] = $movimientos->sortBy('fecha_raw')->map(fn ($m) => collect($m)->except('fecha_raw')->all())->values()->all();

        // Obligaciones (facturas a pagar) con su saldo — para registrar pagos.
        $base['obligaciones'] = $pagos->map(fn (PagoProveedor $pg) => [
            'id' => $pg->id,
            'factura' => $pg->compra?->factura_numero ?: ($pg->compra?->numero ?? '—'),
            'vence' => $f($pg->fecha_vencimiento),
            'monto' => (float) $pg->monto,
            'pagado' => (float) $pg->monto_pagado,
            'saldo' => max(0, (float) $pg->monto - (float) $pg->monto_pagado),
        ])->values()->all();

        // Pedidos (= compras con su seguimiento de fechas/estado)
        $base['pedidos'] = $compras->map(fn ($c) => [
            'oc' => $c->numero,
            'fecha' => $f($c->fecha),
            'estado' => self::PEDIDO_ESTADO[$c->estado] ?? 'pendiente',
            'estimada' => $f($c->fecha_estimada),
            'llegada' => $f($c->fecha_llegada),
        ])->all();

        // Compras con artículos. Marca si tiene FACTURA cargada (si no, "P" = pendiente de factura).
        $conObligacion = $pagos->pluck('compra_id')->filter()->unique()->all();
        $base['compras'] = $compras->map(fn ($c) => [
            'id' => $c->id,
            'fac' => $c->factura_numero ?: $c->numero,
            'tiene_factura' => (bool) $c->factura_numero || in_array($c->id, $conObligacion, true),
            'recibida' => $c->estado === 'recibida',
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

    // ===== Cargar FACTURA sobre una compra recibida (remito→factura): genera la obligación/deuda =====
    public ?int $facturaCompraId = null;
    public string $facNumero = '';
    public string $facVencimiento = '';

    public function pedirCargarFactura(int $compraId): void
    {
        $this->autorizar('gestionar_proveedores');
        $this->facturaCompraId = $compraId;
        $this->facNumero = '';
        $this->facVencimiento = Carbon::today()->addDays(30)->toDateString();
        $this->resetValidation();
    }

    public function cerrarFactura(): void
    {
        $this->facturaCompraId = null;
        $this->facNumero = '';
    }

    public function cargarFactura(): void
    {
        $this->autorizar('gestionar_proveedores');
        $this->validate([
            'facNumero' => 'required|min:1',
            'facVencimiento' => 'required|date',
        ], attributes: ['facNumero' => 'número de factura', 'facVencimiento' => 'vencimiento']);

        $compra = Compra::find($this->facturaCompraId);
        if (! $compra) {
            return;
        }
        // Evitar duplicar la obligación de una misma compra.
        if (PagoProveedor::where('compra_id', $compra->id)->exists()) {
            $this->mensaje = 'Esa compra ya tiene una factura/obligación cargada.';
            $this->cerrarFactura();
            return;
        }

        $compra->update(['factura_numero' => $this->facNumero]);
        PagoProveedor::create([
            'proveedor_id' => $compra->proveedor_id,
            'compra_id' => $compra->id,
            'monto' => (float) $compra->total,
            'monto_pagado' => 0,
            'fecha_vencimiento' => $this->facVencimiento,
            'estado' => 'pendiente',
        ]);

        $this->mensaje = "Factura {$this->facNumero} cargada — la deuda del proveedor se generó por \${$compra->total}.";
        $this->cerrarFactura();
    }

    // ===== Registrar PAGO a proveedor (egreso en caja + baja la deuda) =====
    public ?int $pagoObligacionId = null;
    public string $pagoMonto = '';
    public string $pagoMedio = 'transferencia';

    public function pedirPagoProveedor(int $obligacionId): void
    {
        $this->autorizar('gestionar_proveedores');
        $ob = PagoProveedor::find($obligacionId);
        if (! $ob) {
            return;
        }
        $this->pagoObligacionId = $ob->id;
        $this->pagoMonto = (string) round(max(0, (float) $ob->monto - (float) $ob->monto_pagado), 2);
        $this->pagoMedio = 'transferencia';
        $this->resetValidation();
    }

    public function cerrarPagoProveedor(): void
    {
        $this->pagoObligacionId = null;
        $this->pagoMonto = '';
    }

    public function registrarPagoProveedor(): void
    {
        $this->autorizar('gestionar_proveedores');
        $ob = PagoProveedor::with('proveedor:id,nombre', 'compra:id,numero,factura_numero')->find($this->pagoObligacionId);
        if (! $ob) {
            return;
        }
        $saldo = round(max(0, (float) $ob->monto - (float) $ob->monto_pagado), 2);
        $this->validate([
            'pagoMonto' => 'required|numeric|min:0.01|max:' . $saldo,
            'pagoMedio' => 'required|in:transferencia,efectivo,cheque',
        ], messages: ['pagoMonto.max' => "No podés pagar más que el saldo (\${$saldo})."], attributes: ['pagoMonto' => 'importe']);

        $monto = round((float) $this->pagoMonto, 2);
        $ob->monto_pagado = round((float) $ob->monto_pagado + $monto, 2);
        $ob->fecha_pago = now();
        $ob->estado = $ob->monto_pagado >= (float) $ob->monto - 0.005 ? 'pagado' : 'parcial';
        $ob->save();

        $medioLbl = ['transferencia' => 'Transferencia', 'efectivo' => 'Efectivo', 'cheque' => 'Cheque'][$this->pagoMedio];
        $fac = $ob->compra?->factura_numero ?: ($ob->compra?->numero ?? '');
        MovimientoCaja::create([
            'tipo' => 'egreso', 'medio' => $medioLbl,
            'concepto' => 'Pago a proveedor ' . ($ob->proveedor?->nombre ?? '') . ($fac ? " · Factura {$fac}" : ''),
            'monto' => $monto, 'fecha' => now(), 'referencia' => 'PAGOPROV',
        ]);

        $this->mensaje = 'Pago registrado (egreso en caja) por $' . number_format($monto, 2, ',', '.') . '.';
        $this->cerrarPagoProveedor();
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
