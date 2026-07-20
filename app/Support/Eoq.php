<?php

namespace App\Support;

/**
 * Motor de cálculo del Lote Económico de Pedido (EOQ) y derivados para
 * control de stock: punto de reposición, stock de seguridad, frecuencia y
 * costo total anual. Cálculo puro (sin DB) para que sea testeable y reusable.
 *
 *   EOQ = √(2·D·S / H)
 *     D = demanda anual (unidades/año)
 *     S = costo de emitir un pedido ($/orden)
 *     H = costo anual de mantener una unidad en stock ($/unidad·año) = tasa% · costo_unit
 *
 *   Stock de seguridad (SS) = z · σ_demanda_durante_lead_time
 *   Punto de reposición (ROP) = demanda_diaria · lead_time + SS
 */
class Eoq
{
    public const DIAS_ANIO = 365;

    /** Factor z según nivel de servicio (%). Aproximación de la normal estándar. */
    public static function z(float $nivelServicioPct): float
    {
        // OJO: claves enteras a propósito — PHP trunca las claves float (99.9 → 99)
        // y pisaría valores. El nivel de servicio se maneja como % entero.
        $tabla = [
            50 => 0.00, 75 => 0.67, 80 => 0.84, 85 => 1.04,
            90 => 1.28, 95 => 1.65, 97 => 1.88, 98 => 2.05, 99 => 2.33,
        ];
        // Coincidencia exacta o el nivel definido más cercano por debajo.
        if (isset($tabla[$nivelServicioPct])) {
            return $tabla[$nivelServicioPct];
        }
        $z = 1.65;
        foreach ($tabla as $nivel => $valor) {
            if ($nivelServicioPct >= $nivel) {
                $z = $valor;
            }
        }
        return $z;
    }

    /**
     * @param  float  $demandaAnual   D (unidades/año)
     * @param  float  $costoPedido    S ($/orden)
     * @param  float  $costoUnit      C (costo de compra de la unidad)
     * @param  float  $tasaMantPct    tasa anual de mantenimiento (%) → H = tasa% · C
     * @param  int    $leadTimeDias   L (días de entrega del proveedor)
     * @param  float  $sigmaDiaria    desvío estándar de la demanda diaria (variabilidad)
     * @param  float  $nivelServicio  % de nivel de servicio (define z)
     * @param  int    $stockActual    existencias actuales (para decidir si reponer ya)
     * @return array<string,float|int|bool>
     */
    public static function calcular(
        float $demandaAnual,
        float $costoPedido,
        float $costoUnit,
        float $tasaMantPct,
        int $leadTimeDias,
        float $sigmaDiaria = 0.0,
        float $nivelServicio = 95,
        int $stockActual = 0,
    ): array {
        $H = $costoUnit * ($tasaMantPct / 100);                 // costo de mantener 1 unidad/año
        $demandaDiaria = $demandaAnual / self::DIAS_ANIO;

        $eoq = ($demandaAnual > 0 && $H > 0)
            ? (int) max(1, ceil(sqrt((2 * $demandaAnual * $costoPedido) / $H)))
            : 0;

        $z = self::z($nivelServicio);
        $sigmaLead = $sigmaDiaria * sqrt(max(1, $leadTimeDias));  // σ durante el lead time
        $stockSeguridad = (int) ceil($z * $sigmaLead);
        $puntoReposicion = (int) ceil($demandaDiaria * $leadTimeDias + $stockSeguridad);

        $pedidosPorAnio = $eoq > 0 ? $demandaAnual / $eoq : 0;
        $diasEntrePedidos = $pedidosPorAnio > 0 ? self::DIAS_ANIO / $pedidosPorAnio : 0;

        // Costo total anual de la política = emitir pedidos + mantener (ciclo medio + SS).
        $costoOrdenar = $eoq > 0 ? $pedidosPorAnio * $costoPedido : 0;
        $costoMantener = $H * ($eoq / 2 + $stockSeguridad);
        $costoTotalAnual = $costoOrdenar + $costoMantener;

        return [
            'eoq' => $eoq,
            'demanda_anual' => round($demandaAnual, 1),
            'demanda_diaria' => round($demandaDiaria, 2),
            'H' => round($H, 2),
            'z' => $z,
            'stock_seguridad' => $stockSeguridad,
            'punto_reposicion' => $puntoReposicion,
            'pedidos_por_anio' => round($pedidosPorAnio, 1),
            'dias_entre_pedidos' => (int) round($diasEntrePedidos),
            'costo_ordenar' => round($costoOrdenar, 2),
            'costo_mantener' => round($costoMantener, 2),
            'costo_total_anual' => round($costoTotalAnual, 2),
            'reponer_ahora' => $eoq > 0 && $stockActual <= $puntoReposicion,
            'sugerido_pedir' => $eoq > 0 && $stockActual <= $puntoReposicion ? $eoq : 0,
        ];
    }
}
