<?php

namespace App\Livewire\Tesoreria;

use App\Livewire\Concerns\AutorizaPermisos;
use App\Models\Cheque;
use App\Models\ChequeCliente;
use App\Models\Cliente;
use App\Models\MovimientoCaja;
use App\Models\PagoProveedor;
use App\Models\Proveedor;
use App\Support\Cartera;
use App\Support\Pagos;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Cartera de cheques (pedido del cliente: cheques propios y de terceros + "cheques para mañana").
 *  - Terceros: los que recibimos. Se depositan (ingreso de caja), se endosan a un proveedor o rebotan.
 *  - Propios: los que emitimos a proveedores. Se debitan al vencimiento (egreso de caja).
 *  - Calendario: qué entra y qué sale, día por día, con HOY y MAÑANA destacados.
 */
#[Layout('components.layouts.app')]
#[Title('Cheques — E.Comercial')]
class Cheques extends Component
{
    use AutorizaPermisos;

    #[Url(as: 'sub')]
    public string $tab = 'cartera';   // cartera | propios | calendario

    public string $buscar = '';
    public string $filtroEstado = 'pendiente';
    public ?string $mensaje = null;

    // ===== Alta de cheque de TERCEROS (recibido) =====
    public bool $modalTercero = false;
    public ?int $tCliente = null;
    public string $tNumero = '';
    public string $tBanco = '';
    public string $tMonto = '';
    public string $tVencimiento = '';

    // ===== Alta de cheque PROPIO (emitido) =====
    public bool $modalPropio = false;
    public ?int $pProveedor = null;
    public string $pNumero = '';
    public string $pBanco = '';
    public string $pMonto = '';
    public string $pEmision = '';
    public string $pVencimiento = '';

    // ===== Rechazo =====
    public ?int $rechazandoId = null;
    public string $motivoRechazo = '';

    // ===== Endoso a proveedor =====
    public ?int $endosandoId = null;
    public ?int $eObligacion = null;

    public function mount(): void
    {
        $this->autorizar('ver_tesoreria');
    }

    public function setTab(string $t): void
    {
        $this->tab = $t;
        $this->filtroEstado = $t === 'propios' ? 'pendiente' : 'pendiente';
    }

    // ===================================================================
    //  Terceros (cartera)
    // ===================================================================
    public function nuevoTercero(): void
    {
        $this->autorizar('cargar_cheques');
        $this->reset(['tCliente', 'tNumero', 'tBanco', 'tMonto', 'tVencimiento']);
        $this->resetValidation();
        $this->modalTercero = true;
    }

    public function guardarTercero(): void
    {
        $this->autorizar('cargar_cheques');
        $this->validate([
            'tCliente' => 'required|exists:clientes,id',
            'tNumero' => 'required|max:60',
            'tMonto' => 'required|numeric|min:0.01',
            'tVencimiento' => 'required|date',
        ], attributes: ['tCliente' => 'cliente', 'tNumero' => 'número', 'tMonto' => 'monto', 'tVencimiento' => 'vencimiento']);

        $ch = ChequeCliente::create([
            'cliente_id' => $this->tCliente,
            'numero' => trim($this->tNumero),
            'banco' => trim($this->tBanco) ?: null,
            'monto' => (float) $this->tMonto,
            'fecha_vencimiento' => $this->tVencimiento,
            'fecha_deposito' => ChequeCliente::calcularDeposito($this->tVencimiento),
            'estado' => 'pendiente',
        ]);

        $this->modalTercero = false;
        $this->mensaje = "Cheque {$ch->numero} ingresado a la cartera. Se deposita el " . $ch->fecha_deposito->format('d/m/Y') . '.';
    }

    public function depositar(int $id): void
    {
        $this->autorizar('cargar_cheques');
        $ch = ChequeCliente::with('cliente:id,nombre')->find($id);
        if (! $ch || $ch->estado !== 'pendiente') {
            return;
        }
        $ch->update(['estado' => 'depositado', 'fecha_deposito' => $ch->fecha_deposito ?? now()]);
        MovimientoCaja::create([
            'tipo' => 'ingreso',
            'concepto' => 'Cheque depositado ' . $ch->numero . ' · ' . ($ch->cliente?->nombre ?? ''),
            'medio' => 'Cheque', 'monto' => $ch->monto, 'fecha' => now(), 'referencia' => $ch->numero,
        ]);
        $this->mensaje = "Cheque {$ch->numero} depositado: ingreso registrado en caja.";
    }

    public function acreditar(int $id): void
    {
        $this->autorizar('cargar_cheques');
        $ch = ChequeCliente::find($id);
        if (! $ch || $ch->estado !== 'depositado') {
            return;
        }
        $ch->update(['estado' => 'acreditado']);
        $this->mensaje = "Cheque {$ch->numero} acreditado en la cuenta.";
    }

    public function pedirRechazo(int $id): void
    {
        $this->autorizar('cargar_cheques');
        $this->rechazandoId = $id;
        $this->motivoRechazo = '';
    }

    public function rechazar(): void
    {
        $this->autorizar('cargar_cheques');
        $ch = ChequeCliente::find($this->rechazandoId);
        if (! $ch) {
            $this->reset(['rechazandoId', 'motivoRechazo']);

            return;
        }
        // Si ya se había depositado (ingreso en caja), el rebote revierte ese ingreso.
        if ($ch->estado === 'depositado') {
            MovimientoCaja::create([
                'tipo' => 'egreso',
                'concepto' => 'Cheque RECHAZADO ' . $ch->numero . ' — se revierte el depósito',
                'medio' => 'Cheque', 'monto' => $ch->monto, 'fecha' => now(), 'referencia' => 'RECHCHQ',
            ]);
        }
        $ch->update(['estado' => 'rechazado', 'motivo_rechazo' => trim($this->motivoRechazo) ?: 'Sin fondos']);
        $this->mensaje = "Cheque {$ch->numero} marcado como RECHAZADO.";
        $this->reset(['rechazandoId', 'motivoRechazo']);
    }

    // ===================================================================
    //  Endoso: pagar a un proveedor con un cheque de la cartera
    // ===================================================================
    public function pedirEndoso(int $id): void
    {
        $this->autorizar('cargar_cheques');
        $this->endosandoId = $id;
        $this->eObligacion = null;
        $this->resetValidation();
    }

    /** Facturas de proveedor impagas, para elegir cuál se salda con el cheque. */
    public function getObligacionesProperty(): array
    {
        return PagoProveedor::with('proveedor:id,nombre', 'compra:id,numero,factura_numero')
            ->whereIn('estado', ['pendiente', 'parcial'])
            ->orderBy('fecha_vencimiento')->get()
            ->map(fn (PagoProveedor $o) => [
                'id' => $o->id,
                'proveedor' => $o->proveedor?->nombre ?? '—',
                'factura' => $o->compra?->factura_numero ?: ($o->compra?->numero ?? '—'),
                'saldo' => round(max(0, (float) $o->monto - (float) $o->monto_pagado), 2),
            ])->filter(fn ($o) => $o['saldo'] > 0)->values()->all();
    }

    public function endosar(): void
    {
        $this->autorizar('cargar_cheques');
        $this->validate(
            ['eObligacion' => 'required|exists:pagos_proveedor,id'],
            ['eObligacion.required' => 'Elegí la factura del proveedor que se salda con este cheque.']
        );

        $ch = ChequeCliente::with('cliente:id,nombre')->find($this->endosandoId);
        $ob = PagoProveedor::with('proveedor:id,nombre', 'compra:id,numero,factura_numero')->find($this->eObligacion);
        if (! $ch || ! $ob || $ch->estado !== 'pendiente') {
            $this->reset(['endosandoId', 'eObligacion']);

            return;
        }
        if (in_array($ch->id, Cartera::chequesConEndosoEnCurso(), true)) {
            $this->mensaje = "El cheque {$ch->numero} ya tiene un endoso pedido, esperando autorización.";
            $this->reset(['endosandoId', 'eObligacion']);

            return;
        }

        $saldo = round(max(0, (float) $ob->monto - (float) $ob->monto_pagado), 2);
        $fac = $ob->compra?->factura_numero ?: ($ob->compra?->numero ?? '');

        // Va al tablero de autorización: el jefe autoriza y el tesorero lo procesa (ahí sale de la cartera).
        Pagos::solicitar([
            'tipo' => 'proveedor',
            'proveedor_id' => $ob->proveedor_id,
            'obligacion_id' => $ob->id,
            'beneficiario' => $ob->proveedor?->nombre ?? 'Proveedor',
            'concepto' => 'Endoso de cheque ' . $ch->numero . ' (' . ($ch->banco ?: 's/banco') . ') de ' . ($ch->cliente?->nombre ?? 'tercero') . ($fac ? " · Factura {$fac}" : ''),
            'importe' => min((float) $ch->monto, $saldo),
            'medio' => 'cheque',
            'banco' => $ch->banco,
            'cheque_numero' => $ch->numero,
            'cheque_cliente_id' => $ch->id,
            'comentario' => 'Endoso de cheque de terceros: no mueve la caja (el cheque nunca ingresó a caja).',
        ], auth()->id());

        $this->mensaje = "Endoso del cheque {$ch->numero} solicitado. Queda pendiente de autorización en Tesorería → Autorización de pagos.";
        $this->reset(['endosandoId', 'eObligacion']);
    }

    // ===================================================================
    //  Propios (emitidos a proveedores)
    // ===================================================================
    public function nuevoPropio(): void
    {
        $this->autorizar('cargar_cheques');
        $this->reset(['pProveedor', 'pNumero', 'pBanco', 'pMonto', 'pEmision', 'pVencimiento']);
        $this->pEmision = Carbon::today()->toDateString();
        $this->resetValidation();
        $this->modalPropio = true;
    }

    public function guardarPropio(): void
    {
        $this->autorizar('cargar_cheques');
        $this->validate([
            'pProveedor' => 'required|exists:proveedores,id',
            'pNumero' => 'required|max:60',
            'pMonto' => 'required|numeric|min:0.01',
            'pVencimiento' => 'required|date',
            'pEmision' => 'nullable|date',
        ], attributes: ['pProveedor' => 'proveedor', 'pNumero' => 'número', 'pMonto' => 'monto', 'pVencimiento' => 'vencimiento', 'pEmision' => 'emisión']);

        $ch = Cheque::create([
            'proveedor_id' => $this->pProveedor,
            'numero' => trim($this->pNumero),
            'banco' => trim($this->pBanco) ?: null,
            'monto' => (float) $this->pMonto,
            'fecha_emision' => $this->pEmision ?: null,
            'fecha_vencimiento' => $this->pVencimiento,
            'estado' => 'pendiente',
        ]);

        $this->modalPropio = false;
        $this->mensaje = "Cheque propio {$ch->numero} registrado. Se debita el " . $ch->fecha_vencimiento->format('d/m/Y') . '.';
    }

    public function debitar(int $id): void
    {
        $this->autorizar('cargar_cheques');
        $ch = Cheque::with('proveedor:id,nombre')->find($id);
        if (! $ch || $ch->estado !== 'pendiente') {
            return;
        }
        $ch->update(['estado' => 'cobrado']);
        MovimientoCaja::create([
            'tipo' => 'egreso',
            'concepto' => 'Cheque debitado ' . $ch->numero . ' · ' . ($ch->proveedor?->nombre ?? ''),
            'medio' => 'Cheque', 'monto' => $ch->monto, 'fecha' => now(), 'referencia' => $ch->numero,
        ]);
        $this->mensaje = "Cheque {$ch->numero} debitado: egreso registrado en caja.";
    }

    public function render()
    {
        $hoy = Carbon::today();

        return view('livewire.tesoreria.cheques', [
            'hoy' => $hoy,
            'kpis' => Cartera::kpis($hoy),
            'terceros' => $this->tab === 'cartera' ? Cartera::terceros($this->filtroEstado, $this->buscar) : collect(),
            'propios' => $this->tab === 'propios' ? Cartera::propios($this->filtroEstado, $this->buscar) : collect(),
            'calendario' => $this->tab === 'calendario' ? Cartera::calendario(30, $hoy) : [],
            'endosoEnCurso' => Cartera::chequesConEndosoEnCurso(),
            'clientes' => Cliente::orderBy('nombre')->get(['id', 'nombre']),
            'proveedores' => Proveedor::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }
}
