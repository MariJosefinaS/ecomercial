<?php

namespace App\Support;

use App\Models\CobroMedio;
use App\Models\Parametro;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Comisión del cobrador. Es un % (general o propio del cobrador) sobre el monto EFECTIVAMENTE
 * COBRADO y **confirmado por el tesorero** (partes de cobro con estado_conciliacion = 'conciliado').
 * Un cobro registrado pero no conciliado NO cuenta todavía: recién impacta cuando Tesorería lo confirma.
 */
class Comisiones
{
    public const CLAVE_GENERAL = 'comision_cobrador_general';

    /** % general (default para todos los cobradores). */
    public static function general(): float
    {
        return Parametro::num(self::CLAVE_GENERAL, 0);
    }

    /** % efectivo de un cobrador: el propio, o el general si no tiene uno. */
    public static function pct(User $cobrador): float
    {
        return $cobrador->comision_pct !== null ? (float) $cobrador->comision_pct : self::general();
    }

    /** Monto cobrado CONFIRMADO (conciliado por tesorería) por un cobrador en un rango. */
    public static function cobradoConfirmado(int $cobradorId, Carbon $desde, ?Carbon $hasta = null): float
    {
        return self::sumaMedios($cobradorId, $desde, $hasta, 'conciliado');
    }

    /** Monto cobrado PENDIENTE de confirmar (registrado, aún no conciliado). */
    public static function cobradoPendiente(int $cobradorId, Carbon $desde, ?Carbon $hasta = null): float
    {
        return self::sumaMedios($cobradorId, $desde, $hasta, 'registrado');
    }

    private static function sumaMedios(int $cobradorId, Carbon $desde, ?Carbon $hasta, string $estado): float
    {
        return (float) CobroMedio::where('estado_conciliacion', $estado)
            ->whereHas('cobro', fn ($q) => $q
                ->where('cobrador_id', $cobradorId)
                ->where('fecha', '>=', $desde)
                ->when($hasta, fn ($qq) => $qq->where('fecha', '<=', $hasta)))
            ->sum('monto');
    }

    /** Comisión a pagar = monto confirmado × % del cobrador. */
    public static function comision(User $cobrador, float $confirmado): float
    {
        return round($confirmado * self::pct($cobrador) / 100, 2);
    }
}
