<?php

namespace App\Support;

use App\Models\Cobro;
use App\Models\Cuota;
use App\Models\Venta;
use Illuminate\Support\Carbon;

/**
 * Estado de cuenta de un crédito (como CRED de GENESIS, "la joya"): métricas del acuerdo y su avance.
 * Total cobrado, debería haber pagado, atraso, cuotas atrasadas, cobro promedio, % avance, a vencer, etc.
 */
class EstadoCredito
{
    public static function de(Venta $v, ?Carbon $al = null): array
    {
        $al = $al ?? Carbon::today();
        $cuotas = Cuota::where('venta_id', $v->id)->orderBy('numero')->get();
        $total = $cuotas->count();
        $pagadas = $cuotas->where('estado', 'cobrada')->count();
        $pend = $cuotas->where('estado', 'pendiente');
        $vencidas = $pend->filter(fn (Cuota $c) => $c->estaVencida($al));

        $totalCredito = round($cuotas->sum(fn (Cuota $c) => (float) $c->monto), 2);      // sin anticipo
        $totalCobrado = round($cuotas->sum(fn (Cuota $c) => (float) $c->pagado_monto), 2);
        // Debería haber pagado a la fecha = Σ monto de cuotas ya vencidas (venc <= hoy).
        $deberia = round($cuotas->filter(fn (Cuota $c) => $c->fecha_vencimiento->lte($al))->sum(fn (Cuota $c) => (float) $c->monto), 2);
        $atrasoTotal = round(max(0, $deberia - $totalCobrado), 2);

        $cobros = Cobro::where('venta_id', $v->id)->orderByDesc('fecha')->get();
        $ultimo = $cobros->first();

        return [
            // Acuerdo
            'total_credito' => $totalCredito,
            'anticipo' => (float) $v->anticipo,
            'cuota' => (float) $v->cuota,
            'plazo' => (int) $v->plazo,
            'modalidad' => $v->modalidad,
            'plan' => $v->plan_nombre,
            'vendedor' => $v->vendedor?->name ?? '—',
            'fecha_solicitud' => $v->fecha?->format('d/m/Y'),
            'primera_cuota' => $v->fecha_primera_cuota?->format('d/m/Y'),
            'fin_acuerdo' => optional($cuotas->max('fecha_vencimiento'))->format('d/m/Y'),
            // Garante
            'garante' => $v->garante_nombre,
            'garante_doc' => $v->garante_documento,
            'garante_tel' => $v->garante_telefono,
            // Avance / atraso
            'total_cuotas' => $total,
            'cuotas_pagadas' => $pagadas,
            'cuotas_atrasadas' => $vencidas->count(),
            'dias_atraso' => (int) ($vencidas->max(fn (Cuota $c) => $c->diasAtraso($al)) ?? 0),
            'total_cobrado' => $totalCobrado,
            'deberia_pagado' => $deberia,
            'atraso_total' => $atrasoTotal,
            'saldo' => round($pend->sum(fn (Cuota $c) => $c->totalAcobrar($al)), 2),
            'vencido' => round($vencidas->sum(fn (Cuota $c) => $c->saldo()), 2),
            'a_vencer' => round($pend->filter(fn (Cuota $c) => ! $c->estaVencida($al))->sum(fn (Cuota $c) => $c->saldo()), 2),
            'mora' => round($pend->sum(fn (Cuota $c) => $c->mora($al)), 2),
            'avance' => $total > 0 ? round($pagadas / $total * 100, 1) : 0.0,
            'cobro_promedio' => $pagadas > 0 ? round($totalCobrado / $pagadas, 2) : 0.0,  // por cuota pagada
            'cobros_count' => $cobros->count(),
            'ultimo_cobro_fecha' => $ultimo?->fecha?->format('d/m/Y'),
            'ultimo_cobro_monto' => (float) ($ultimo?->monto ?? 0),
            // Productos
            'productos' => $v->items->map(fn ($it) => [
                'nombre' => $it->producto?->nombre ?? 'Producto',
                'cantidad' => (int) $it->cantidad,
                'precio' => (float) $it->precio_unitario,
            ])->all(),
        ];
    }
}
