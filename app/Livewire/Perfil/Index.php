<?php

namespace App\Livewire\Perfil;

use App\Models\AdelantoSueldo;
use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\PlanillaCobranza;
use App\Support\Comisiones;
use App\Support\CuentaEmpleado;
use App\Support\Permisos;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Mi perfil: datos del usuario + (si es cobrador) estadísticas de su cobranza, su CUENTA
 * (comisiones devengadas − pagos = saldo a cobrar) y el pedido de adelanto de sueldo.
 * Accesible a todo usuario logueado (no está en rutaPermiso).
 */
#[Layout('components.layouts.app')]
#[Title('Mi perfil — E.Comercial')]
class Index extends Component
{
    // Pedido de adelanto de sueldo
    public string $adMonto = '';
    public string $adMotivo = '';
    public ?string $mensaje = null;

    /** El empleado solicita un adelanto → queda pendiente de aprobación del super admin. */
    public function solicitarAdelanto(): void
    {
        $u = auth()->user();
        abort_unless($u->esCobrador(), 403);

        // No permitir un segundo pedido mientras haya uno pendiente.
        if (AdelantoSueldo::where('empleado_id', $u->id)->where('estado', 'pendiente')->exists()) {
            $this->mensaje = 'Ya tenés un adelanto pendiente de aprobación.';
            return;
        }
        $this->validate(['adMonto' => 'required|numeric|min:0.01'], attributes: ['adMonto' => 'monto']);

        CuentaEmpleado::solicitarAdelanto($u->id, (float) $this->adMonto, $this->adMotivo ?: null);
        $this->reset(['adMonto', 'adMotivo']);
        $this->mensaje = 'Adelanto solicitado. Queda pendiente de aprobación del super administrador.';
    }

    public function render()
    {
        $u = auth()->user();
        $esCobrador = $u->esCobrador();

        $stats = null;
        $cuenta = null;
        if ($esCobrador) {
            $hoy = Carbon::today();
            $inicioMes = Carbon::now()->startOfMonth();

            $zonas = $u->zonasComoCobrador()->with('local')->get();
            $zonaIds = $zonas->pluck('id');

            // Montos CONFIRMADOS por tesorería (conciliados) — recién estos cuentan.
            $confirmadoMes = Comisiones::cobradoConfirmado($u->id, $inicioMes);
            $pendienteMes = Comisiones::cobradoPendiente($u->id, $inicioMes);
            $confirmadoHoy = Comisiones::cobradoConfirmado($u->id, $hoy->copy()->startOfDay(), $hoy->copy()->endOfDay());
            $pagosMes = Cobro::where('cobrador_id', $u->id)->where('fecha', '>=', $inicioMes)->count();

            $comisionPct = Comisiones::pct($u);
            $comisionPropia = $u->comision_pct !== null;

            $planMes = PlanillaCobranza::where('cobrador_id', $u->id)
                ->where('fecha', '>=', $inicioMes->toDateString())->get();
            $esperado = (float) $planMes->sum('total_esperado');
            $cobrado = (float) $planMes->sum('total_cobrado');
            $eficacia = $esperado > 0 ? round($cobrado / $esperado * 100, 1) : null;

            $clientes = $zonaIds->isNotEmpty() ? Cliente::whereIn('zona_id', $zonaIds)->count() : 0;

            // Cuenta del empleado (ledger): comisión devengada (al confirmar) − pagos.
            $cuenta = [
                'saldo' => CuentaEmpleado::saldo($u->id),
                'devengado_total' => CuentaEmpleado::totalDevengado($u->id),
                'devengado_mes' => CuentaEmpleado::totalDevengado($u->id, $inicioMes),
                'pagado_total' => CuentaEmpleado::totalPagado($u->id),
                'movimientos' => CuentaEmpleado::movimientos($u->id, 40),
                'adelantos' => AdelantoSueldo::where('empleado_id', $u->id)->latest()->limit(10)->get(),
            ];

            $stats = compact('zonas', 'confirmadoHoy', 'confirmadoMes', 'pendienteMes', 'pagosMes', 'eficacia', 'comisionPct', 'comisionPropia', 'clientes');
        }

        return view('livewire.perfil.index', [
            'u' => $u,
            'rolLabel' => Permisos::rolesNombres()[$u->rol] ?? ucfirst(str_replace('_', ' ', (string) $u->rol)),
            'esCobrador' => $esCobrador,
            'stats' => $stats,
            'cuenta' => $cuenta,
        ]);
    }
}
