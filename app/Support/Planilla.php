<?php

namespace App\Support;

use App\Models\Cuota;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Cálculo de la planilla de cobranza del cobrador (líneas + totales), derivado del cronograma.
 * Fuente única compartida por el componente Livewire (Cobranza\Planilla) y la vista de impresión.
 */
class Planilla
{
    /** Cuotas del día del cobrador: pendientes a cobrar (venc <= fecha, incl. morosos) + cobradas ese día. */
    public static function cuotasDelDia(int $cobradorId, Carbon $f): Collection
    {
        return Cuota::with(['cliente:id,nombre,direccion,telefono', 'venta:id,numero,modalidad,plan_nombre', 'zonaRel:id,nombre'])
            ->whereHas('zonaRel', fn ($q) => $q->where('cobrador_id', $cobradorId))
            ->where(function ($q) use ($f) {
                $q->where(fn ($w) => $w->where('estado', 'pendiente')->whereDate('fecha_vencimiento', '<=', $f))
                  ->orWhere(fn ($w) => $w->where('estado', 'cobrada')->whereDate('cobrada_at', $f));
            })
            ->orderBy('fecha_vencimiento')
            ->get();
    }

    /** Modalidades presentes ese día, en orden diario→semanal→mensual. */
    public static function modalidadesPresentes(Collection $cuotas): Collection
    {
        $orden = ['diario', 'semanal', 'mensual'];

        return $cuotas->map(fn (Cuota $c) => $c->venta?->modalidad)->filter()->unique()
            ->sortBy(fn ($m) => array_search($m, $orden))->values();
    }

    /** Líneas de una modalidad (para vista, impresión y export). */
    public static function filas(Collection $cuotas, Carbon $f, string $modalidad): array
    {
        return $cuotas
            ->filter(fn (Cuota $c) => ($c->venta?->modalidad) === $modalidad)
            ->map(fn (Cuota $c) => [
                'id' => $c->id,
                'cliente' => $c->cliente?->nombre ?? '—',
                'domicilio' => $c->cliente?->direccion ?? '',
                'telefono' => $c->cliente?->telefono ?? '',
                'zona' => $c->zonaRel?->nombre ?? ($c->zona ?: '—'),
                'credito' => $c->venta?->numero ?? '—',
                'plan' => $c->venta?->plan_nombre ?? ucfirst($modalidad),
                'numero' => (int) $c->numero,
                'vence' => $c->fecha_vencimiento->format('d/m/Y'),
                'dias' => $c->diasAtraso($f),
                'saldo' => $c->saldo(),
                'mora' => $c->mora($f),
                'total' => $c->totalAcobrar($f),
                'estado' => $c->estado === 'cobrada' ? 'Cobrada' : ($c->estaVencida($f) ? 'Atrasada' : 'A cobrar'),
                'cobrada' => $c->estado === 'cobrada',
            ])->values()->all();
    }

    /** Totales de una modalidad (esperado a cobrar vs cobrado ese día + eficacia). */
    public static function totales(Collection $cuotas, Carbon $f, string $modalidad): array
    {
        $delGrupo = $cuotas->filter(fn (Cuota $c) => ($c->venta?->modalidad) === $modalidad);
        $esperado = $delGrupo->where('estado', 'pendiente')->sum(fn (Cuota $c) => $c->totalAcobrar($f));
        $cobrado = $delGrupo->where('estado', 'cobrada')->sum(fn (Cuota $c) => (float) $c->pagado_monto);

        return [
            'esperado' => round($esperado, 2),
            'cobrado' => round($cobrado, 2),
            'eficacia' => $esperado > 0 ? round($cobrado / $esperado * 100, 1) : ($cobrado > 0 ? 100.0 : 0.0),
        ];
    }
}
