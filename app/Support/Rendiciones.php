<?php

namespace App\Support;

use App\Models\Cobro;
use App\Models\CobroMedio;
use App\Models\MovimientoCaja;
use App\Models\Rendicion;
use App\Models\User;
use App\Support\CuentaEmpleado;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rendición / conciliación de cobranza (Tesorería):
 *  - EFECTIVO: el cobrador rinde la plata; el admin ingresa cuánto recibió vs lo esperado.
 *    La diferencia (faltante/sobrante) se ajusta en caja para que refleje lo real.
 *  - TRANSFERENCIAS / CHEQUES: se concilian una a una (registrado → conciliado) al verlas en el banco/cartera.
 * Trabaja sobre `cobro_medios.estado_conciliacion` ('registrado' | 'conciliado').
 */
class Rendiciones
{
    /** Partes de cobro de un día (opc. de un cobrador), con cobro+cliente cargados. */
    private static function partes(?int $cobradorId, Carbon $fecha)
    {
        return CobroMedio::with(['cobro.cliente:id,nombre', 'cobro.cobrador:id,name'])
            ->whereHas('cobro', fn ($q) => $q
                ->when($cobradorId, fn ($qq) => $qq->where('cobrador_id', $cobradorId))
                ->whereDate('fecha', $fecha))
            ->get();
    }

    /** Arma un renglón de vista de una parte. */
    private static function fila(CobroMedio $m): array
    {
        return [
            'id' => $m->id,
            'cliente' => $m->cobro?->cliente?->nombre ?? '—',
            'cobrador' => $m->cobro?->cobrador?->name ?? '—',
            'monto' => (float) $m->monto,
            'banco' => $m->banco,
            'cheque_numero' => $m->cheque_numero,
            'comprobante' => $m->comprobanteUrl(),
            'conciliado' => $m->estado_conciliacion === 'conciliado',
            'no_rendido' => $m->estado_conciliacion === 'no_rendido',
            'motivo' => $m->no_rendido_motivo,
            'hora' => $m->cobro?->fecha?->format('H:i'),
        ];
    }

    /** Resumen de rendición del día (por cobrador si se indica). */
    public static function resumen(?int $cobradorId, Carbon $fecha): array
    {
        $partes = self::partes($cobradorId, $fecha);

        $porMedio = fn (string $medio) => $partes->where('medio', $medio)->map(fn ($m) => self::fila($m))->values()->all();
        $efectivo = $partes->where('medio', 'efectivo');
        $transfer = $partes->where('medio', 'transferencia');
        $cheque = $partes->where('medio', 'cheque');

        // "Pendiente" = solo 'registrado' (no_rendido/conciliado ya no cuentan para rendir).
        $sumPend = fn ($col) => round($col->where('estado_conciliacion', 'registrado')->sum(fn ($m) => (float) $m->monto), 2);
        $sumCon = fn ($col) => round($col->where('estado_conciliacion', 'conciliado')->sum(fn ($m) => (float) $m->monto), 2);
        $sumNoRend = fn ($col) => round($col->where('estado_conciliacion', 'no_rendido')->sum(fn ($m) => (float) $m->monto), 2);

        // Rendiciones de efectivo ya registradas ese día (para el cobrador filtrado).
        $rendiciones = Rendicion::with('registrador:id,name')
            ->when($cobradorId, fn ($q) => $q->where('cobrador_id', $cobradorId))
            ->whereDate('fecha', $fecha)->latest('id')->get();

        return [
            'efectivo' => [
                'filas' => $porMedio('efectivo'),
                'esperado_pendiente' => $sumPend($efectivo),   // aún no rendido
                'ya_rendido' => $sumCon($efectivo),
                'no_rendido' => $sumNoRend($efectivo),
                'total' => round((float) $efectivo->sum(fn ($m) => (float) $m->monto), 2),
                'cant' => $efectivo->count(),
            ],
            'transferencia' => [
                'filas' => $porMedio('transferencia'),
                'pendiente' => $sumPend($transfer),
                'conciliado' => $sumCon($transfer),
                'total' => round((float) $transfer->sum(fn ($m) => (float) $m->monto), 2),
                'cant' => $transfer->count(),
            ],
            'cheque' => [
                'filas' => $porMedio('cheque'),
                'pendiente' => $sumPend($cheque),
                'conciliado' => $sumCon($cheque),
                'total' => round((float) $cheque->sum(fn ($m) => (float) $m->monto), 2),
                'cant' => $cheque->count(),
            ],
            'rendiciones' => $rendiciones,
        ];
    }

    /** Marca UNA parte (transferencia/cheque) como conciliada (vista en el banco/cartera). */
    public static function conciliarParte(int $cobroMedioId, int $userId): bool
    {
        $m = CobroMedio::find($cobroMedioId);
        if (! $m || in_array($m->estado_conciliacion, ['conciliado', 'no_rendido'], true)) {
            return false;
        }
        $m->update(['estado_conciliacion' => 'conciliado', 'conciliado_por' => $userId, 'conciliado_at' => now()]);
        // Al confirmar el cobro, se DEVENGA la comisión del cobrador en su cuenta.
        CuentaEmpleado::acreditarComisionDeMedio($m, $userId);

        return true;
    }

    /** Concilia todas las partes pendientes de un medio para el día/cobrador (bulk). */
    public static function conciliarMedio(string $medio, ?int $cobradorId, Carbon $fecha, int $userId): int
    {
        $partes = self::partes($cobradorId, $fecha)
            ->where('medio', $medio)->where('estado_conciliacion', 'registrado');
        if ($partes->isEmpty()) {
            return 0;
        }
        CobroMedio::whereIn('id', $partes->pluck('id'))->update([
            'estado_conciliacion' => 'conciliado', 'conciliado_por' => $userId, 'conciliado_at' => now(),
        ]);
        foreach ($partes as $m) {
            CuentaEmpleado::acreditarComisionDeMedio($m, $userId);
        }

        return $partes->count();
    }

    /**
     * Marca una parte de cobro como NO RENDIDA (robada/perdida por el cobrador). El cliente NO se afecta
     * (pagó, tiene recibo): se revierte el ingreso en caja y se le CARGA el importe al cobrador.
     * Solo sobre partes aún no conciliadas.
     */
    public static function marcarNoRendido(int $cobroMedioId, string $motivo, int $userId): bool
    {
        $m = CobroMedio::with('cobro.cobrador', 'cobro.venta:id,numero')->find($cobroMedioId);
        if (! $m || $m->estado_conciliacion === 'conciliado' || $m->estado_conciliacion === 'no_rendido') {
            return false;
        }
        $cobro = $m->cobro;
        $cobradorId = $cobro?->cobrador_id;
        $ref = $cobro?->venta?->numero ?? ($cobro ? 'Cobro #' . $cobro->id : null);
        $monto = (float) $m->monto;

        DB::transaction(function () use ($m, $cobradorId, $ref, $monto, $motivo, $userId) {
            $m->update([
                'estado_conciliacion' => 'no_rendido', 'no_rendido_motivo' => $motivo ?: null,
                'conciliado_por' => $userId, 'conciliado_at' => now(),
            ]);

            // Revertir el ingreso que se había registrado al cobrar (ese efectivo no está).
            MovimientoCaja::create([
                'tipo' => 'egreso', 'medio' => Cobro::MEDIOS[$m->medio] ?? ucfirst($m->medio),
                'concepto' => 'Cobro no rendido' . ($ref ? " ({$ref})" : '') . ' — ' . ($motivo ?: 'sin motivo'),
                'monto' => $monto, 'fecha' => now(), 'referencia' => 'NORENDIDO',
            ]);

            // Cargar el importe al cobrador (va contra él; el cliente no absorbe nada).
            if ($cobradorId) {
                CuentaEmpleado::cargar($cobradorId, $monto, 'Cargo por cobro no rendido' . ($ref ? " ({$ref})" : ''), 'norendido_medio:' . $m->id, $userId);
            }
        });

        return true;
    }

    /**
     * Registra la rendición de EFECTIVO de un cobrador: marca sus partes efectivo pendientes como
     * conciliadas, crea el registro de Rendicion y ajusta caja por la diferencia (faltante/sobrante).
     *
     * @return array{ok:bool, esperado:float, recibido:float, diferencia:float, cant:int, mensaje:string}
     */
    public static function rendirEfectivo(int $cobradorId, Carbon $fecha, float $recibido, ?string $nota, int $userId): array
    {
        $partes = self::partes($cobradorId, $fecha)
            ->where('medio', 'efectivo')->where('estado_conciliacion', 'registrado');

        if ($partes->isEmpty()) {
            return ['ok' => false, 'esperado' => 0, 'recibido' => $recibido, 'diferencia' => 0, 'cant' => 0,
                'mensaje' => 'No hay efectivo pendiente de rendir para ese cobrador en esa fecha.'];
        }

        $esperado = round((float) $partes->sum(fn ($m) => (float) $m->monto), 2);
        $recibido = round($recibido, 2);
        $dif = round($recibido - $esperado, 2);
        $cobrador = User::find($cobradorId);

        DB::transaction(function () use ($partes, $esperado, $recibido, $dif, $nota, $userId, $cobradorId, $fecha, $cobrador) {
            CobroMedio::whereIn('id', $partes->pluck('id'))->update([
                'estado_conciliacion' => 'conciliado', 'conciliado_por' => $userId, 'conciliado_at' => now(),
            ]);
            // Devengar comisión del cobrador por cada parte confirmada.
            foreach ($partes as $m) {
                CuentaEmpleado::acreditarComisionDeMedio($m, $userId);
            }

            Rendicion::create([
                'cobrador_id' => $cobradorId, 'fecha' => $fecha->toDateString(),
                'total_esperado' => $esperado, 'total_recibido' => $recibido, 'diferencia' => $dif,
                'cantidad_cobros' => $partes->count(), 'nota' => $nota ?: null, 'registrado_por' => $userId,
            ]);

            // Ajuste de caja si el efectivo físico difiere de lo esperado (que ya entró a caja al cobrar).
            if (abs($dif) >= 0.005) {
                MovimientoCaja::create([
                    'tipo' => $dif < 0 ? 'egreso' : 'ingreso', 'medio' => 'Efectivo',
                    'concepto' => 'Ajuste rendición ' . ($cobrador?->name ?? "cobrador #{$cobradorId}") . ' ' . $fecha->format('d/m/Y')
                        . ' (' . ($dif < 0 ? 'faltante' : 'sobrante') . ')',
                    'monto' => abs($dif), 'fecha' => now(), 'referencia' => 'REND',
                ]);
            }
        });

        $msg = 'Rendición registrada: esperado $' . number_format($esperado, 2, ',', '.')
            . ', recibido $' . number_format($recibido, 2, ',', '.');
        if (abs($dif) >= 0.005) {
            $msg .= $dif < 0
                ? '. Faltante $' . number_format(abs($dif), 2, ',', '.') . ' (ajustado en caja).'
                : '. Sobrante $' . number_format($dif, 2, ',', '.') . ' (ajustado en caja).';
        } else {
            $msg .= '. Cuadra exacto ✔';
        }

        return ['ok' => true, 'esperado' => $esperado, 'recibido' => $recibido, 'diferencia' => $dif, 'cant' => $partes->count(), 'mensaje' => $msg];
    }
}
