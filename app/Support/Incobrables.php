<?php

namespace App\Support;

use App\Models\Cuota;
use App\Models\PlanCredito;
use App\Models\Venta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Clientes / créditos INCOBRABLES. Un crédito es incobrable cuando acumula ≥ N cuotas vencidas,
 * siendo N el umbral configurado en SU PLAN (`planes_credito.cuotas_incobrable`, por tipo de plan).
 * Los créditos incobrables dejan de aparecer en la planilla del cobrador (no se visitan más).
 */
class Incobrables
{
    /** Umbral por código de plan (solo los que tienen umbral > 0). */
    private static function umbrales(): Collection
    {
        return PlanCredito::where('cuotas_incobrable', '>', 0)->pluck('cuotas_incobrable', 'codigo');
    }

    /**
     * De un conjunto de venta_ids, cuáles son incobrables a la fecha (≥ umbral de su plan en cuotas vencidas).
     * Eficiente (una query agregada) — se usa para filtrar la planilla.
     *
     * @param  array<int>  $ventaIds
     * @return array<int>
     */
    public static function ventaIdsIncobrables(array $ventaIds, Carbon $al): array
    {
        if (empty($ventaIds)) {
            return [];
        }
        $umbral = self::umbrales();
        if ($umbral->isEmpty()) {
            return [];
        }

        $ventas = Venta::whereIn('id', $ventaIds)->get(['id', 'plan_codigo']);
        $vencidas = Cuota::selectRaw('venta_id, count(*) as c')
            ->whereIn('venta_id', $ventaIds)
            ->where('estado', 'pendiente')
            ->whereDate('fecha_vencimiento', '<', $al->toDateString())
            ->groupBy('venta_id')->pluck('c', 'venta_id');

        $out = [];
        foreach ($ventas as $v) {
            $n = (int) ($umbral[$v->plan_codigo] ?? 0);
            if ($n > 0 && (int) ($vencidas[$v->id] ?? 0) >= $n) {
                $out[] = $v->id;
            }
        }

        return $out;
    }

    /** Detalle de créditos incobrables (para el tablero de supervisión), opcional por cobrador/zona. */
    public static function detalle(?int $cobradorId, ?int $zonaId, Carbon $al): Collection
    {
        $umbral = self::umbrales();
        if ($umbral->isEmpty()) {
            return collect();
        }

        $ventas = Venta::query()
            ->where('credito', true)
            ->when($zonaId, fn ($q) => $q->where('zona_id', $zonaId))
            ->when($cobradorId, fn ($q) => $q->whereHas('zona', fn ($z) => $z->where('cobrador_id', $cobradorId)))
            ->whereHas('cuotas', fn ($q) => $q->where('estado', 'pendiente')->whereDate('fecha_vencimiento', '<', $al->toDateString()))
            ->with(['cliente:id,nombre,telefono,direccion', 'zona:id,nombre,cobrador_id', 'zona.cobrador:id,name', 'cuotas'])
            ->get();

        return $ventas->map(function (Venta $v) use ($umbral, $al) {
            $n = (int) ($umbral[$v->plan_codigo] ?? 0);
            if ($n <= 0) {
                return null;
            }
            $pendientes = $v->cuotas->where('estado', 'pendiente');
            $vencidas = $pendientes->filter(fn (Cuota $c) => $c->estaVencida($al));
            if ($vencidas->count() < $n) {
                return null;
            }

            return [
                'venta' => $v->numero,
                'cliente' => $v->cliente?->nombre ?? '—',
                'telefono' => $v->cliente?->telefono ?? '',
                'domicilio' => $v->cliente?->direccion ?? '',
                'zona' => $v->zona?->nombre ?? '—',
                'cobrador' => $v->zona?->cobrador?->name ?? '—',
                'plan' => $v->plan_nombre ?? '—',
                'umbral' => $n,
                'vencidas' => $vencidas->count(),
                'deuda' => round($pendientes->sum(fn (Cuota $c) => $c->totalAcobrar($al)), 2),
            ];
        })->filter()->sortByDesc('vencidas')->values();
    }
}
