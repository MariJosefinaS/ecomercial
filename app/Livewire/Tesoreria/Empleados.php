<?php

namespace App\Livewire\Tesoreria;

use App\Livewire\Concerns\AutorizaPermisos;
use App\Models\AdelantoSueldo;
use App\Models\User;
use App\Support\CuentaEmpleado;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Tesorería → Pago a empleados. El tesorero ve la cuenta corriente de cada cobrador (comisiones
 * devengadas − pagos = saldo a favor), le registra pagos (egreso en caja + recibo firmable) y
 * gestiona los adelantos de sueldo (el empleado los pide; el SUPER ADMIN los aprueba; luego se pagan).
 */
#[Layout('components.layouts.app')]
#[Title('Pago a empleados — E.Comercial')]
class Empleados extends Component
{
    use AutorizaPermisos, WithFileUploads;

    #[Url(as: 'emp')]
    public ?int $empleadoId = null;

    public ?string $mensaje = null;
    public ?int $ultimoPagoId = null;

    // Form de pago
    public string $pagoMonto = '';
    public string $pagoMedio = 'efectivo';
    public $pagoComprobante = null;
    public string $pagoBanco = '';
    public string $pagoNota = '';
    public ?int $adelantoPagandoId = null;   // si el pago salda un adelanto aprobado

    // Rechazo de adelanto
    public ?int $rechazandoId = null;
    public string $motivoRechazo = '';

    public function mount(): void
    {
        $this->autorizar('pagar_empleados');
    }

    private function esSuper(): bool
    {
        return auth()->user()?->esRol('super_admin') ?? false;
    }

    public function seleccionar(int $id): void
    {
        $this->empleadoId = $id;
        $this->reset(['pagoMonto', 'pagoMedio', 'pagoComprobante', 'pagoBanco', 'pagoNota', 'adelantoPagandoId', 'ultimoPagoId']);
        $this->pagoMedio = 'efectivo';
        $this->resetValidation();
    }

    public function registrarPago(): void
    {
        $this->autorizar('pagar_empleados');
        if (! $this->empleadoId) {
            return;
        }
        $this->validate([
            'pagoMonto' => 'required|numeric|min:0.01',
            'pagoMedio' => 'required|in:efectivo,transferencia',
            'pagoComprobante' => $this->pagoMedio === 'transferencia' ? 'required|image|max:4096' : 'nullable|image|max:4096',
        ], attributes: ['pagoMonto' => 'importe', 'pagoComprobante' => 'comprobante']);

        $comp = $this->pagoComprobante ? $this->pagoComprobante->store('pagos_empleado', 'public') : null;
        $emp = User::find($this->empleadoId);

        // Genera un PEDIDO DE PAGO. Si es un adelanto (ya aprobado por el super_admin) queda
        // PRE-AUTORIZADO; si es un pago común, queda pendiente de autorización. El egreso + recibo
        // se generan al PROCESARLO en Tesorería → Autorización de pagos.
        \App\Support\Pagos::solicitar([
            'tipo' => 'empleado', 'empleado_id' => $this->empleadoId, 'adelanto_id' => $this->adelantoPagandoId,
            'beneficiario' => $emp?->name ?? 'Empleado',
            'concepto' => $this->adelantoPagandoId ? 'Adelanto de sueldo' : 'Pago de comisiones',
            'importe' => (float) $this->pagoMonto, 'medio' => $this->pagoMedio,
            'comprobante' => $comp, 'banco' => $this->pagoBanco ?: null, 'comentario' => $this->pagoNota ?: null,
            'preautorizado' => (bool) $this->adelantoPagandoId,
        ], auth()->id());

        $this->ultimoPagoId = null;
        $this->reset(['pagoMonto', 'pagoComprobante', 'pagoBanco', 'pagoNota', 'adelantoPagandoId']);
        $this->pagoMedio = 'efectivo';
        $this->mensaje = $this->esSuper() || false
            ? 'Pedido de pago generado. Procesalo en Tesorería → Autorización de pagos.'
            : 'Pedido de pago enviado a autorización (Tesorería → Autorización de pagos).';
    }

    // ===== Adelantos =====
    public function aprobarAdelanto(int $id): void
    {
        abort_unless($this->esSuper(), 403, 'Solo el super administrador aprueba adelantos.');
        CuentaEmpleado::aprobarAdelanto($id, auth()->id());
        $this->mensaje = 'Adelanto aprobado. Ya se puede pagar desde Tesorería.';
    }

    public function pedirRechazo(int $id): void
    {
        $this->rechazandoId = $id;
        $this->motivoRechazo = '';
    }

    public function rechazarAdelanto(): void
    {
        abort_unless($this->esSuper(), 403, 'Solo el super administrador rechaza adelantos.');
        if ($this->rechazandoId) {
            CuentaEmpleado::rechazarAdelanto($this->rechazandoId, auth()->id(), $this->motivoRechazo ?: null);
            $this->mensaje = 'Adelanto rechazado.';
        }
        $this->reset(['rechazandoId', 'motivoRechazo']);
    }

    /** Prepara el pago de un adelanto aprobado (pre-carga importe + empleado). */
    public function pagarAdelanto(int $id): void
    {
        $this->autorizar('pagar_empleados');
        $ad = AdelantoSueldo::where('id', $id)->where('estado', 'aprobado')->first();
        if (! $ad) {
            return;
        }
        $this->empleadoId = $ad->empleado_id;
        $this->adelantoPagandoId = $ad->id;
        $this->pagoMonto = (string) (float) $ad->monto;
        $this->pagoMedio = 'efectivo';
        $this->mensaje = 'Confirmá el pago del adelanto (elegí el medio) para registrarlo.';
    }

    public function render()
    {
        $empleados = CuentaEmpleado::empleadosConSaldo();
        $sel = $this->empleadoId ? User::find($this->empleadoId) : null;

        return view('livewire.tesoreria.empleados', [
            'empleados' => $empleados,
            'sel' => $sel,
            'saldo' => $sel ? CuentaEmpleado::saldo($sel->id) : 0,
            'devengado' => $sel ? CuentaEmpleado::totalDevengado($sel->id) : 0,
            'pagado' => $sel ? CuentaEmpleado::totalPagado($sel->id) : 0,
            'movimientos' => $sel ? CuentaEmpleado::movimientos($sel->id) : collect(),
            'adelantosPend' => AdelantoSueldo::with('empleado:id,name')->where('estado', 'pendiente')->latest()->get(),
            'adelantosAprob' => AdelantoSueldo::with('empleado:id,name')->where('estado', 'aprobado')->latest()->get(),
            'esSuper' => $this->esSuper(),
        ]);
    }
}
