<?php

namespace App\Support;

use App\Models\AdelantoSueldo;
use App\Models\CobroMedio;
use App\Models\MovimientoCaja;
use App\Models\MovimientoEmpleado;
use App\Models\PagoEmpleado;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cuenta corriente del empleado (cobrador).
 *  - COMISIÓN (haber): se devenga cuando Tesorería CONFIRMA la cobranza (concilia el cobro_medio).
 *  - PAGO / ADELANTO (debe): egreso de Tesorería al empleado (impacta caja) + recibo firmable.
 *  Saldo a favor del empleado = Σhaber − Σdebe (negativo = se le pagó/adelantó de más).
 */
class CuentaEmpleado
{
    /** Saldo a favor del empleado (lo que la empresa le debe). Negativo = a favor de la empresa. */
    public static function saldo(int $empleadoId): float
    {
        $hab = (float) MovimientoEmpleado::where('empleado_id', $empleadoId)->where('tipo', 'haber')->sum('monto');
        $deb = (float) MovimientoEmpleado::where('empleado_id', $empleadoId)->where('tipo', 'debe')->sum('monto');

        return round($hab - $deb, 2);
    }

    public static function totalDevengado(int $empleadoId, ?Carbon $desde = null): float
    {
        return (float) MovimientoEmpleado::where('empleado_id', $empleadoId)->where('tipo', 'haber')
            ->when($desde, fn ($q) => $q->where('fecha', '>=', $desde))->sum('monto');
    }

    public static function totalPagado(int $empleadoId, ?Carbon $desde = null): float
    {
        return (float) MovimientoEmpleado::where('empleado_id', $empleadoId)->where('tipo', 'debe')
            ->when($desde, fn ($q) => $q->where('fecha', '>=', $desde))->sum('monto');
    }

    /** Movimientos de la cuenta (más recientes primero). */
    public static function movimientos(int $empleadoId, int $limit = 60)
    {
        return MovimientoEmpleado::where('empleado_id', $empleadoId)
            ->orderByDesc('fecha')->orderByDesc('id')->limit($limit)->get();
    }

    /**
     * Devenga la comisión de UNA parte de cobro recién CONFIRMADA (idempotente por referencia).
     * Se llama desde la conciliación/rendición en Tesorería.
     */
    public static function acreditarComisionDeMedio(CobroMedio $m, int $userId): void
    {
        $m->loadMissing('cobro.cobrador', 'cobro.cliente');
        $cobrador = $m->cobro?->cobrador;
        if (! $cobrador) {
            return; // cobro sin cobrador → sin comisión
        }
        $ref = 'medio:' . $m->id;
        if (MovimientoEmpleado::where('referencia', $ref)->where('tipo', 'haber')->exists()) {
            return; // ya devengada
        }
        $pct = Comisiones::pct($cobrador);
        $monto = round((float) $m->monto * $pct / 100, 2);
        if ($monto <= 0) {
            return;
        }
        MovimientoEmpleado::create([
            'empleado_id' => $cobrador->id, 'tipo' => 'haber',
            'concepto' => 'Comisión ' . rtrim(rtrim(number_format($pct, 2, ',', '.'), '0'), ',') . '% · cobro ' . ($m->cobro?->cliente?->nombre ?? '—'),
            'monto' => $monto, 'referencia' => $ref, 'fecha' => now(), 'registrado_por' => $userId,
        ]);
    }

    /**
     * Registra un pago del tesorero al empleado: crea el PagoEmpleado, el debe en su cuenta y el
     * EGRESO en caja. Si es un adelanto aprobado (opts['adelanto_id']) lo marca pagado.
     */
    public static function registrarPago(int $empleadoId, float $monto, string $medio, array $opts, int $tesoreroId): PagoEmpleado
    {
        $medio = in_array($medio, ['efectivo', 'transferencia'], true) ? $medio : 'efectivo';
        $monto = round($monto, 2);
        $empleado = User::find($empleadoId);
        $esAdelanto = ! empty($opts['adelanto_id']);

        return DB::transaction(function () use ($empleadoId, $monto, $medio, $opts, $tesoreroId, $empleado, $esAdelanto) {
            $saldoAntes = self::saldo($empleadoId);

            $pago = PagoEmpleado::create([
                'empleado_id' => $empleadoId, 'monto' => $monto, 'medio' => $medio,
                'comprobante' => $opts['comprobante'] ?? null, 'banco' => $opts['banco'] ?? null,
                'nota' => $opts['nota'] ?? null,
                'saldo_antes' => $saldoAntes, 'saldo_despues' => round($saldoAntes - $monto, 2),
                'fecha' => now(), 'registrado_por' => $tesoreroId,
            ]);

            MovimientoEmpleado::create([
                'empleado_id' => $empleadoId, 'tipo' => 'debe',
                'concepto' => ($esAdelanto ? 'Adelanto de sueldo' : 'Pago de comisiones') . ' (' . (PagoEmpleado::MEDIOS[$medio] ?? $medio) . ')',
                'monto' => $monto, 'referencia' => 'pago:' . $pago->id, 'fecha' => now(), 'registrado_por' => $tesoreroId,
            ]);

            // Egreso en caja (cruce con Tesorería por medio).
            MovimientoCaja::create([
                'tipo' => 'egreso', 'medio' => PagoEmpleado::MEDIOS[$medio] ?? ucfirst($medio),
                'concepto' => ($esAdelanto ? 'Adelanto de sueldo' : 'Pago a empleado') . ' ' . ($empleado?->name ?? "#{$empleadoId}"),
                'monto' => $monto, 'fecha' => now(), 'referencia' => 'PAGEMP',
            ]);

            if ($esAdelanto) {
                AdelantoSueldo::where('id', $opts['adelanto_id'])->update([
                    'estado' => 'pagado', 'pago_empleado_id' => $pago->id,
                ]);
            }

            return $pago;
        });
    }

    /**
     * Carga un DEBE en la cuenta del empleado (ej. cobro no rendido/robado). Reduce su saldo a favor
     * (puede quedar negativo = el empleado le debe a la empresa). No toca caja (eso lo hace quien llama).
     */
    public static function cargar(int $empleadoId, float $monto, string $concepto, ?string $referencia, int $userId): void
    {
        if ($monto <= 0) {
            return;
        }
        MovimientoEmpleado::create([
            'empleado_id' => $empleadoId, 'tipo' => 'debe', 'concepto' => $concepto,
            'monto' => round($monto, 2), 'referencia' => $referencia, 'fecha' => now(), 'registrado_por' => $userId,
        ]);
    }

    // ===== Adelantos de sueldo =====
    public static function solicitarAdelanto(int $empleadoId, float $monto, ?string $motivo): AdelantoSueldo
    {
        return AdelantoSueldo::create([
            'empleado_id' => $empleadoId, 'monto' => round($monto, 2),
            'motivo' => $motivo ?: null, 'estado' => 'pendiente',
        ]);
    }

    public static function aprobarAdelanto(int $id, int $superId): void
    {
        AdelantoSueldo::where('id', $id)->where('estado', 'pendiente')
            ->update(['estado' => 'aprobado', 'aprobado_por' => $superId, 'aprobado_at' => now()]);
    }

    public static function rechazarAdelanto(int $id, int $superId, ?string $motivo): void
    {
        AdelantoSueldo::where('id', $id)->where('estado', 'pendiente')
            ->update(['estado' => 'rechazado', 'aprobado_por' => $superId, 'aprobado_at' => now(), 'motivo_rechazo' => $motivo ?: null]);
    }

    /** Resumen de empleados (cobradores) con su saldo a favor, para la sección de Tesorería. */
    public static function empleadosConSaldo()
    {
        return User::whereHas('zonasComoCobrador')->orderBy('name')->get()
            ->map(fn (User $u) => [
                'id' => $u->id, 'name' => $u->name,
                'saldo' => self::saldo($u->id),
                'devengado' => self::totalDevengado($u->id),
                'pagado' => self::totalPagado($u->id),
            ]);
    }
}
