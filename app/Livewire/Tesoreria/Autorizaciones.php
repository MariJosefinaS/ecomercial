<?php

namespace App\Livewire\Tesoreria;

use App\Livewire\Concerns\AutorizaPermisos;
use App\Models\PedidoPago;
use App\Support\Pagos;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Tablero de autorización de pagos (como GENESIS Finanzas):
 * Pendiente → (jefe) Autoriza/Rechaza → (tesorero) Procesa. El egreso ocurre al procesar.
 */
#[Layout('components.layouts.app')]
#[Title('Autorización de pagos — E.Comercial')]
class Autorizaciones extends Component
{
    use AutorizaPermisos;

    public ?string $mensaje = null;
    public ?int $ultimoReciboPagoId = null;

    // Rechazo
    public ?int $rechazandoId = null;
    public string $motivoRechazo = '';

    public function mount(): void
    {
        $this->autorizar('ver_tesoreria');
    }

    public function autorizar_(int $id): void
    {
        $this->autorizar('autorizar_pagos');
        if ($p = PedidoPago::find($id)) {
            Pagos::autorizar($p, auth()->id());
            $this->mensaje = 'Pedido autorizado. Ya se puede procesar el pago.';
        }
    }

    public function pedirRechazo(int $id): void
    {
        $this->autorizar('autorizar_pagos');
        $this->rechazandoId = $id;
        $this->motivoRechazo = '';
    }

    public function rechazar(): void
    {
        $this->autorizar('autorizar_pagos');
        if ($this->rechazandoId && ($p = PedidoPago::find($this->rechazandoId))) {
            Pagos::rechazar($p, auth()->id(), $this->motivoRechazo ?: null);
            $this->mensaje = 'Pedido rechazado.';
        }
        $this->reset(['rechazandoId', 'motivoRechazo']);
    }

    public function anular(int $id): void
    {
        $this->autorizar('autorizar_pagos');
        if ($p = PedidoPago::find($id)) {
            Pagos::anular($p, auth()->id());
            $this->mensaje = 'Pedido anulado.';
        }
    }

    public function procesar(int $id): void
    {
        $this->autorizar('registrar_pago');
        $p = PedidoPago::find($id);
        if (! $p) {
            return;
        }
        $r = Pagos::procesar($p, auth()->id());
        $this->mensaje = $r['mensaje'];
        // Si generó un recibo de empleado, ofrecerlo.
        if (($r['ok'] ?? false) && str_starts_with((string) ($r['ref'] ?? ''), 'PagoEmpleado:')) {
            $this->ultimoReciboPagoId = (int) explode(':', $r['ref'])[1];
        }
    }

    public function render()
    {
        $pendientes = PedidoPago::with('solicitante:id,name')->where('estado', 'pendiente')->latest()->get();
        $autorizados = PedidoPago::with('solicitante:id,name', 'autorizador:id,name')->where('estado', 'autorizado')->latest()->get();
        $historial = PedidoPago::with('procesador:id,name', 'autorizador:id,name')
            ->whereIn('estado', ['pagado', 'rechazado', 'anulado'])->latest('updated_at')->limit(40)->get();

        $tot = Pagos::totales();

        return view('livewire.tesoreria.autorizaciones', [
            'pendientes' => $pendientes,
            'autorizados' => $autorizados,
            'historial' => $historial,
            'totPendiente' => (float) ($tot['pendiente']->total ?? 0),
            'totAutorizado' => (float) ($tot['autorizado']->total ?? 0),
            'totPagado' => (float) ($tot['pagado']->total ?? 0),
            'puedeAutorizar' => \App\Support\Permisos::puede(auth()->user()?->rol, 'autorizar_pagos'),
            'puedeProcesar' => \App\Support\Permisos::puede(auth()->user()?->rol, 'registrar_pago'),
        ]);
    }
}
