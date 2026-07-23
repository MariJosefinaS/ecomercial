<?php

namespace App\Livewire\Tesoreria;

use App\Livewire\Concerns\AutorizaPermisos;
use App\Models\Cobro;
use App\Models\User;
use App\Support\Rendiciones;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pantalla del TESORERO: el registro de lo que cada cobrador indicó que cobró, SEPARADO POR COBRADOR
 * (una planilla reducida por cobrador). El tesorero NO ve los clientes a cobrar (eso es del supervisor);
 * acá ve/confirma la recepción de los pagos y rinde el efectivo. Reusa App\Support\Rendiciones.
 */
#[Layout('components.layouts.app')]
#[Title('Cobros y rendición — E.Comercial')]
class Cobros extends Component
{
    use AutorizaPermisos;

    #[Url]
    public string $fecha = '';
    public ?string $mensaje = null;

    // Rendición de efectivo por cobrador (modal)
    public ?int $rendirCobradorId = null;
    public string $rendirCobradorNombre = '';
    public float $rendirEsperado = 0;
    public string $rendRecibido = '';
    public string $rendNota = '';

    // Cobro no rendido (modal)
    public ?int $noRendMedioId = null;
    public string $noRendMotivo = '';

    public function mount(): void
    {
        $this->autorizar('registrar_pago');
        if ($this->fecha === '') {
            $this->fecha = Carbon::today()->toDateString();
        }
    }

    private function fechaC(): Carbon
    {
        return Carbon::parse($this->fecha)->startOfDay();
    }

    /** Confirma la recepción de un cobro (concilia sus partes + devenga comisión). */
    public function confirmarCobro(int $cobroId): void
    {
        $this->autorizar('registrar_pago');
        $n = Rendiciones::confirmarCobro($cobroId, auth()->id());
        $this->mensaje = $n > 0 ? "Cobro confirmado ({$n} medio/s recibido/s)." : 'El cobro ya estaba confirmado.';
    }

    /** Concilia una transferencia/cheque (vista en el banco/cartera). */
    public function conciliarParte(int $cobroMedioId): void
    {
        $this->autorizar('registrar_pago');
        Rendiciones::conciliarParte($cobroMedioId, auth()->id());
        $this->mensaje = 'Movimiento conciliado.';
    }

    // ===== Rendición de efectivo por cobrador =====
    public function pedirRendir(int $cobradorId): void
    {
        $this->autorizar('registrar_pago');
        $u = User::find($cobradorId);
        $this->rendirCobradorId = $cobradorId;
        $this->rendirCobradorNombre = $u?->name ?? '—';
        $this->rendirEsperado = Rendiciones::resumen($cobradorId, $this->fechaC())['efectivo']['esperado_pendiente'];
        $this->rendRecibido = (string) $this->rendirEsperado;
        $this->rendNota = '';
        $this->resetValidation();
    }

    public function cerrarRendir(): void
    {
        $this->reset(['rendirCobradorId', 'rendRecibido', 'rendNota']);
    }

    public function rendir(): void
    {
        $this->autorizar('registrar_pago');
        if (! $this->rendirCobradorId) {
            return;
        }
        $this->validate(['rendRecibido' => 'required|numeric|min:0'], attributes: ['rendRecibido' => 'efectivo recibido']);
        $r = Rendiciones::rendirEfectivo($this->rendirCobradorId, $this->fechaC(), (float) $this->rendRecibido, $this->rendNota ?: null, auth()->id());
        $this->mensaje = $r['mensaje'];
        $this->cerrarRendir();
    }

    // ===== Cobro no rendido =====
    public function pedirNoRendido(int $cobroMedioId): void
    {
        $this->autorizar('registrar_pago');
        $this->noRendMedioId = $cobroMedioId;
        $this->noRendMotivo = '';
        $this->resetValidation();
    }

    public function cerrarNoRendido(): void
    {
        $this->reset(['noRendMedioId', 'noRendMotivo']);
    }

    public function marcarNoRendido(): void
    {
        $this->autorizar('registrar_pago');
        if (! $this->noRendMedioId) {
            return;
        }
        $this->validate(['noRendMotivo' => 'required|min:3'], attributes: ['noRendMotivo' => 'motivo']);
        Rendiciones::marcarNoRendido($this->noRendMedioId, $this->noRendMotivo, auth()->id());
        $this->mensaje = 'Cobro marcado como NO RENDIDO: se revirtió el ingreso en caja y se le cargó al cobrador.';
        $this->cerrarNoRendido();
    }

    public function render()
    {
        $fecha = $this->fechaC();
        $ids = Cobro::whereDate('fecha', $fecha)->whereNotNull('cobrador_id')->distinct()->pluck('cobrador_id');
        $grupos = User::whereIn('id', $ids)->orderBy('name')->get()
            ->map(fn (User $c) => [
                'cobrador' => $c,
                'cobros' => Rendiciones::cobrosDelDia($c->id, $fecha),
                'efectivo' => Rendiciones::resumen($c->id, $fecha)['efectivo'],
            ])->all();

        return view('livewire.tesoreria.cobros', ['grupos' => $grupos]);
    }
}
