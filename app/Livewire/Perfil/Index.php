<?php

namespace App\Livewire\Perfil;

use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\PlanillaCobranza;
use App\Support\Permisos;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Mi perfil: datos del usuario + (si es cobrador) estadísticas de su cobranza.
 * Accesible a todo usuario logueado (no está en rutaPermiso).
 */
#[Layout('components.layouts.app')]
#[Title('Mi perfil — E.Comercial')]
class Index extends Component
{
    public function render()
    {
        $u = auth()->user();
        $esCobrador = $u->esCobrador();

        $stats = null;
        if ($esCobrador) {
            $hoy = Carbon::today();
            $inicioMes = Carbon::now()->startOfMonth();

            $zonas = $u->zonasComoCobrador()->with('local')->get();
            $zonaIds = $zonas->pluck('id');

            $cobradoHoy = (float) Cobro::where('cobrador_id', $u->id)->whereDate('fecha', $hoy)->sum('monto');
            $cobradoMes = (float) Cobro::where('cobrador_id', $u->id)->where('fecha', '>=', $inicioMes)->sum('monto');
            $pagosMes = Cobro::where('cobrador_id', $u->id)->where('fecha', '>=', $inicioMes)->count();

            $planMes = PlanillaCobranza::where('cobrador_id', $u->id)
                ->where('fecha', '>=', $inicioMes->toDateString())->get();
            $esperado = (float) $planMes->sum('total_esperado');
            $cobrado = (float) $planMes->sum('total_cobrado');
            $eficacia = $esperado > 0 ? round($cobrado / $esperado * 100, 1) : null;

            // Comisión estimada por tramos de eficacia (pedido del cliente; informativa por ahora).
            $comisionPct = $eficacia === null ? null : ($eficacia >= 90 ? 7 : ($eficacia >= 85 ? 6 : 5));

            $clientes = $zonaIds->isNotEmpty() ? Cliente::whereIn('zona_id', $zonaIds)->count() : 0;

            $stats = compact('zonas', 'cobradoHoy', 'cobradoMes', 'pagosMes', 'eficacia', 'comisionPct', 'clientes', 'esperado', 'cobrado');
        }

        return view('livewire.perfil.index', [
            'u' => $u,
            'rolLabel' => Permisos::rolesNombres()[$u->rol] ?? ucfirst(str_replace('_', ' ', (string) $u->rol)),
            'esCobrador' => $esCobrador,
            'stats' => $stats,
        ]);
    }
}
