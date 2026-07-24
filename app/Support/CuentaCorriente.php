<?php

namespace App\Support;

use App\Models\Cuota;
use App\Models\MovimientoCliente;
use App\Models\Venta;
use Illuminate\Support\Carbon;

/**
 * Cuenta corriente FISCAL del cliente (saldo único, como GENESIS):
 * grilla `F.Carga · Comprobante · Concepto · Debe · Haber · Saldo · Fecha Vto.`
 * y totales **Saldo Vencido · A Vencer · Total**.
 *
 * Cómo se calcula el vencido (pedido #4 del cliente):
 *  - Lo que está en un CRÉDITO se envejece por su **cronograma de cuotas** (fuente real de
 *    los vencimientos, ya usada por Cobranza), no por la fecha de la factura.
 *  - El resto (contado en cta cte, mora, ajustes) se envejece **FIFO** por `fecha_vencimiento`:
 *    los haberes se imputan a los debe más viejos primero, y lo que queda impago con
 *    vencimiento pasado es "vencido".
 * Así el total sigue coincidiendo con el saldo de la cuenta.
 */
class CuentaCorriente
{
    /**
     * Movimientos de la cuenta con saldo acumulado y estado de vencimiento.
     *
     * @param  string  $porFecha  'carga' (fecha del movimiento) o 'vencimiento'
     * @return array<int,array<string,mixed>>
     */
    public static function movimientos(int $clienteId, ?Carbon $hoy = null, string $porFecha = 'carga', ?Carbon $desde = null, ?Carbon $hasta = null): array
    {
        $hoy ??= Carbon::today();
        $campo = $porFecha === 'vencimiento' ? 'fecha_vencimiento' : 'fecha';

        $movs = MovimientoCliente::with('comprobante')
            ->where('cliente_id', $clienteId)
            ->when($desde, fn ($q) => $q->whereDate($campo, '>=', $desde))
            ->when($hasta, fn ($q) => $q->whereDate($campo, '<=', $hasta))
            ->orderBy($campo)->orderBy('id')
            ->get();

        $saldo = 0.0;
        $out = [];
        foreach ($movs as $m) {
            $monto = (float) $m->monto;
            $saldo += $m->tipo === 'debe' ? $monto : -$monto;
            $venc = $m->fecha_vencimiento ?? $m->fecha;

            $out[] = [
                'id' => $m->id,
                'fecha' => $m->fecha?->format('d/m/Y'),
                'comprobante' => $m->comprobante?->etiqueta() ?? ($m->referencia ?: '—'),
                'comprobante_id' => $m->comprobante_id,
                'concepto' => $m->concepto,
                'tipo' => $m->tipo,
                'debe' => $m->tipo === 'debe' ? $monto : null,
                'haber' => $m->tipo === 'haber' ? $monto : null,
                'saldo' => round($saldo, 2),
                'vencimiento' => $venc?->format('d/m/Y'),
                'vencido' => $m->tipo === 'debe' && $venc && $venc->lt($hoy),
            ];
        }

        return $out;
    }

    /**
     * Vencimientos reales de una deuda: si el movimiento es el saldo financiado de un
     * crédito, se abre en sus CUOTAS (cada una con su vencimiento); si no, va entero
     * con su propia fecha de vencimiento.
     * @return array<int,array{venc:?Carbon,resto:float}>
     */
    private static function obligacionesDe(MovimientoCliente $m, array $cuotasPorVenta): array
    {
        $monto = round((float) $m->monto, 2);
        $cuotas = $m->referencia ? ($cuotasPorVenta[$m->referencia] ?? null) : null;

        // Solo se abre en cuotas el asiento del saldo financiado (no las moras del mismo crédito).
        if (! $cuotas || ! str_contains($m->concepto, 'saldo financiado')) {
            return [['venc' => $m->fecha_vencimiento ?? $m->fecha, 'resto' => $monto]];
        }

        $suma = round(array_sum(array_column($cuotas, 'monto')), 2);
        $out = [];
        $acum = 0.0;
        foreach ($cuotas as $i => $c) {
            // La última absorbe el redondeo para que el total coincida exacto con el asiento.
            $parte = ($i === count($cuotas) - 1)
                ? round($monto - $acum, 2)
                : round($monto * ($suma > 0 ? $c['monto'] / $suma : 0), 2);
            $acum = round($acum + $parte, 2);
            $out[] = ['venc' => $c['venc'], 'resto' => $parte];
        }

        return $out;
    }

    /**
     * Totales de la cuenta: saldo, vencido, a vencer y saldo a favor.
     *
     * Una sola fuente: TODOS los movimientos. Cada débito se abre en sus vencimientos
     * reales (las cuotas, si es un crédito) y los pagos se imputan FIFO al más viejo.
     * Por construcción **vencido + a_vencer = saldo**, así que la grilla siempre cierra.
     *
     * @return array{saldo:float,vencido:float,a_vencer:float,mora:float,a_favor:float}
     */
    public static function resumen(int $clienteId, ?Carbon $hoy = null): array
    {
        $hoy ??= Carbon::today();

        // Cronogramas por número de venta (para abrir el saldo financiado en cuotas).
        $ventas = Venta::where('cliente_id', $clienteId)->where('credito', true)->pluck('numero', 'id');
        $cuotasPorVenta = [];
        if ($ventas->isNotEmpty()) {
            foreach (Cuota::whereIn('venta_id', $ventas->keys())->orderBy('venta_id')->orderBy('numero')->get() as $c) {
                $num = $ventas[$c->venta_id] ?? null;
                if ($num) {
                    $cuotasPorVenta[$num][] = ['venc' => $c->fecha_vencimiento, 'monto' => (float) $c->monto];
                }
            }
        }

        $movs = MovimientoCliente::where('cliente_id', $clienteId)
            ->orderByRaw('COALESCE(fecha_vencimiento, fecha)')->orderBy('id')->get();

        $pendientes = [];
        $haberTotal = 0.0;
        $debeTotal = 0.0;
        foreach ($movs as $m) {
            if ($m->tipo === 'debe') {
                $debeTotal += (float) $m->monto;
                foreach (self::obligacionesDe($m, $cuotasPorVenta) as $o) {
                    $pendientes[] = $o;
                }
            } else {
                $haberTotal += (float) $m->monto;
            }
        }

        // Ordenar por vencimiento y aplicar los pagos al más viejo primero.
        usort($pendientes, fn ($a, $b) => ($a['venc']?->timestamp ?? 0) <=> ($b['venc']?->timestamp ?? 0));
        $libre = round($haberTotal, 2);
        foreach ($pendientes as &$p) {
            if ($libre <= 0) {
                break;
            }
            $aplica = min($libre, $p['resto']);
            $p['resto'] = round($p['resto'] - $aplica, 2);
            $libre = round($libre - $aplica, 2);
        }
        unset($p);

        $vencido = 0.0;
        $aVencer = 0.0;
        foreach ($pendientes as $p) {
            if ($p['resto'] <= 0.004) {
                continue;
            }
            if ($p['venc'] && $p['venc']->lt($hoy)) {
                $vencido += $p['resto'];
            } else {
                $aVencer += $p['resto'];
            }
        }

        // Mora devengada y todavía no asentada (informativa): sale del cronograma.
        $mora = round(Cuota::where('cliente_id', $clienteId)->where('estado', 'pendiente')->get()
            ->sum(fn (Cuota $c) => $c->mora($hoy)), 2);

        return [
            'saldo' => round($debeTotal - $haberTotal, 2),
            'vencido' => round($vencido, 2),
            'a_vencer' => round($aVencer, 2),
            'mora' => $mora,
            'a_favor' => round(max(0, $libre), 2),   // pagó de más
        ];
    }
}
