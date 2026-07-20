<?php

namespace App\Support;

use App\Models\PlanCredito;
use Illuminate\Support\Carbon;

/**
 * Planes comerciales / motor de cálculo de la Nota de Pedido.
 *
 * Catálogo EDITABLE en `planes_credito` (Configuración → "Productos de crédito").
 * Si la tabla está vacía, usa el catálogo de respaldo (`fallback()`).
 *
 * Modelo "cuota fija con interés incluido" (decisión del usuario 2026-06-19):
 * al elegir el plan se calcula el total financiado (saldo + interés del plazo)
 * y se reparte en N cuotas fijas (`cronograma()`). Las cuotas vencidas impagas
 * suman MORA por día a la misma tasa diaria del plan (lazy, ver App\Models\Cuota).
 *
 * ⚠️ TASAS PROVISIONALES — confirmar con el cliente; se editan en Configuración.
 */
class PlanesCredito
{
    /** Catálogo de respaldo (si `planes_credito` está vacía, p/ tests y primera corrida). */
    private static function fallback(): array
    {
        return [
            'contado' => ['nombre' => 'Contado', 'modalidad' => 'contado', 'anticipo_pct' => 100, 'tasa' => 0, 'plazo_default' => 0, 'unidad' => ''],
            'd30_020' => ['nombre' => '30% anticipo + 0,20 diario', 'modalidad' => 'diario', 'anticipo_pct' => 30, 'tasa' => 0.20, 'plazo_default' => 100, 'unidad' => 'días'],
            's50_si0' => ['nombre' => '50% anticipo, saldo sin interés', 'modalidad' => 'diario', 'anticipo_pct' => 50, 'tasa' => 0, 'plazo_default' => 100, 'unidad' => 'días'],
            'd0_045'  => ['nombre' => '0% anticipo + 0,45 diario', 'modalidad' => 'diario', 'anticipo_pct' => 0, 'tasa' => 0.45, 'plazo_default' => 100, 'unidad' => 'días'],
            'm5_13s'  => ['nombre' => '30% anticipo en 13 semanas (M5)', 'modalidad' => 'semanal', 'anticipo_pct' => 30, 'tasa' => 0.20, 'plazo_default' => 13, 'unidad' => 'semanas'],
        ];
    }

    /** @return array<string,array{nombre:string,modalidad:string,anticipo_pct:float,tasa:float,plazo_default:int,unidad:string}> */
    public static function planes(): array
    {
        $rows = PlanCredito::where('activo', true)->orderBy('orden')->get();

        if ($rows->isEmpty()) {
            return self::fallback();
        }

        return $rows->mapWithKeys(fn (PlanCredito $p) => [$p->codigo => [
            'nombre' => $p->nombre,
            'modalidad' => $p->modalidad,
            'anticipo_pct' => (float) $p->anticipo_pct,
            'tasa' => (float) $p->tasa_periodo,
            'plazo_default' => (int) $p->plazo_default,
            'unidad' => $p->unidad,
        ]])->all();
    }

    public static function get(string $codigo): ?array
    {
        return self::planes()[$codigo] ?? null;
    }

    /**
     * Calcula la financiación de un plan para un total dado.
     *
     * @return array{modalidad:string,unidad:string,anticipo_min:float,saldo:float,plazo:int,total_financiado:float,cuota_min:float,tasa:float}
     */
    public static function calcular(string $codigo, float $total, ?int $plazo = null): array
    {
        $p = self::get($codigo) ?? self::planes()['contado'] ?? self::fallback()['contado'];
        $plazo = $plazo ?: ($p['plazo_default'] ?: 1);

        $anticipoMin = round($total * $p['anticipo_pct'] / 100, 2);
        $saldo = max(0.0, round($total - $anticipoMin, 2));

        // ⚠️ Provisional: interés simple = tasa% por período × cantidad de períodos.
        $totalFinanciado = $saldo * (1 + ($p['tasa'] / 100) * $plazo);
        $cuotaMin = $plazo > 0 ? (float) ceil($totalFinanciado / $plazo) : 0.0;

        return [
            'modalidad' => $p['modalidad'],
            'unidad' => $p['unidad'],
            'anticipo_min' => $anticipoMin,
            'saldo' => $saldo,
            'plazo' => (int) $plazo,
            'total_financiado' => round($totalFinanciado, 2),
            'cuota_min' => $cuotaMin,
            'tasa' => $p['tasa'],
        ];
    }

    /** ¿Es un plan a crédito (financiación propia)? */
    public static function esCredito(string $codigo): bool
    {
        return ($self = self::get($codigo)) ? $self['modalidad'] !== 'contado' : false;
    }

    /** Días que dura un período según la modalidad (para prorratear la mora diaria). */
    private static function diasPorPeriodo(string $modalidad): int
    {
        return match ($modalidad) {
            'mensual' => 30,
            'semanal' => 7,
            default => 1, // diario / contado
        };
    }

    /** Suma N períodos (día/semana/mes, según modalidad) a una fecha. */
    public static function sumarPeriodos(Carbon $fecha, string $modalidad, int $n): Carbon
    {
        return match ($modalidad) {
            'mensual' => $fecha->copy()->addMonths($n),
            'semanal' => $fecha->copy()->addWeeks($n),
            default => $fecha->copy()->addDays($n), // diario / contado
        };
    }

    /** Tasa de MORA diaria del plan (% por día) — snapshot por cuota. */
    public static function tasaMoraDiaria(string $codigo): float
    {
        $p = self::get($codigo);
        if (! $p) {
            return 0.0;
        }

        // La mora se devenga por DÍA: la tasa del período se prorratea (semanal/7, mensual/30).
        return round((float) $p['tasa'] / self::diasPorPeriodo($p['modalidad'] ?? 'diario'), 4);
    }

    /**
     * Fecha de vencimiento de la 1ª cuota POR DEFECTO = un período (día/semana/mes)
     * después de `$base` (hoy si no se pasa). Es lo que se usaba históricamente
     * (`Carbon::today()` + 1 período) y el prefill sugerido en la Nota de Pedido;
     * el vendedor puede adelantarla/atrasarla (R4).
     */
    public static function primeraCuotaPorDefecto(string $codigo, ?Carbon $base = null): Carbon
    {
        $base = ($base ?? Carbon::today())->copy();
        $modalidad = self::get($codigo)['modalidad'] ?? 'diario';

        return self::sumarPeriodos($base, $modalidad, 1);
    }

    /**
     * Cronograma de cuotas fijas a partir del cálculo del plan.
     * Reparte `total_financiado` en `plazo` cuotas iguales (2 decimales); la última
     * absorbe el redondeo para que la suma cuadre exactamente.
     *
     * @param  array<string,mixed>  $calc  resultado de calcular()
     * @param  Carbon  $primeraCuota  fecha de vencimiento de la CUOTA Nº1 (configurable, R4);
     *                                las siguientes caen 1 período (día/semana/mes) más adelante.
     * @return array<int,array{numero:int,fecha_vencimiento:Carbon,monto:float,capital:float,interes:float}>
     */
    public static function cronograma(array $calc, Carbon $primeraCuota): array
    {
        $plazo = max(1, (int) $calc['plazo']);
        $totalFin = (float) $calc['total_financiado'];
        $saldo = (float) $calc['saldo'];
        $interesTotal = max(0.0, round($totalFin - $saldo, 2));
        $modalidad = $calc['modalidad'] ?? 'diario';

        $montoBase = floor($totalFin / $plazo * 100) / 100;
        $capBase = floor($saldo / $plazo * 100) / 100;
        $intBase = floor($interesTotal / $plazo * 100) / 100;

        $rows = [];
        $accMonto = 0.0; $accCap = 0.0; $accInt = 0.0;
        for ($i = 1; $i <= $plazo; $i++) {
            // La cuota Nº1 vence en $primeraCuota; cada siguiente, un período después.
            $venc = self::sumarPeriodos($primeraCuota, $modalidad, $i - 1);

            if ($i < $plazo) {
                $monto = $montoBase; $cap = $capBase; $int = $intBase;
            } else {
                // última cuota: absorbe el redondeo
                $monto = round($totalFin - $accMonto, 2);
                $cap = round($saldo - $accCap, 2);
                $int = round($interesTotal - $accInt, 2);
            }
            $accMonto += $monto; $accCap += $cap; $accInt += $int;

            $rows[] = ['numero' => $i, 'fecha_vencimiento' => $venc, 'monto' => round($monto, 2), 'capital' => round($cap, 2), 'interes' => round($int, 2)];
        }

        return $rows;
    }
}
