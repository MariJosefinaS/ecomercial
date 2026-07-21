<?php

namespace App\Support;

use App\Models\Cobro;
use App\Models\CobroMedio;
use App\Models\Cuota;
use App\Models\MovimientoCaja;
use App\Models\MovimientoCliente;
use App\Support\Recibo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Servicio de cobranza: registra el cobro de cuotas del cronograma de crédito.
 *
 * Fuente ÚNICA de verdad del asiento de cobro (planilla del cobrador + ficha de Clientes).
 * `registrarPago()` acepta MONTO LIBRE repartido en uno o varios MEDIOS (pago dividido:
 * parte efectivo, parte transferencia, parte cheque). Cada parte genera su movimiento de caja
 * (cruce con Tesorería por medio) y queda como `CobroMedio`; el total impacta la cta. corriente
 * (haber). Si sobra, adelanta la/s cuota/s siguiente/s; si falta, es pago parcial (la mora sigue).
 */
class Cobranza
{
    /** Medios válidos (canónico en minúsculas). */
    public const MEDIOS = ['efectivo', 'transferencia', 'cheque'];

    /**
     * Registra un pago de monto libre (uno o varios medios) imputado a la cuota y siguientes.
     *
     * @param  array<int,array{medio:string,monto:float|string,comprobante?:?string,banco?:?string,cheque_numero?:?string}>  $medios
     * @param  array{cobrador_id?:int}  $opts
     * @return array{ok:bool, monto:float, excedente:float, cuotas_saldadas:int, saldo_a_favor:float, mensaje:string}
     */
    public static function registrarPago(Cuota $cuota, array $medios, array $opts = [], ?Carbon $al = null): array
    {
        $vacio = ['ok' => false, 'monto' => 0.0, 'excedente' => 0.0, 'cuotas_saldadas' => 0, 'saldo_a_favor' => 0.0];

        // Normalizar las partes (medio válido + monto > 0).
        $partes = [];
        foreach ($medios as $m) {
            $monto = round((float) ($m['monto'] ?? 0), 2);
            if ($monto <= 0) {
                continue;
            }
            $partes[] = [
                'medio' => in_array($m['medio'] ?? '', self::MEDIOS, true) ? $m['medio'] : 'efectivo',
                'monto' => $monto,
                'comprobante' => $m['comprobante'] ?? null,
                'banco' => $m['banco'] ?? null,
                'cheque_numero' => $m['cheque_numero'] ?? null,
            ];
        }

        if ($cuota->estado !== 'pendiente') {
            return $vacio + ['mensaje' => 'La cuota ya no está pendiente.'];
        }
        if (empty($partes)) {
            return $vacio + ['mensaje' => 'Ingresá al menos un importe mayor a cero.'];
        }

        $montoRecibido = round(array_sum(array_column($partes, 'monto')), 2);
        $al = $al ?? Carbon::today();
        $cuota->loadMissing('venta:id,numero');
        $venta = $cuota->venta;
        $ref = $venta?->numero;
        $unMedio = count($partes) === 1;
        $medioCobro = $unMedio ? $partes[0]['medio'] : 'mixto';
        $detMedios = collect($partes)->map(fn ($p) => (Cobro::MEDIOS[$p['medio']] ?? $p['medio']) . ' $' . number_format($p['monto'], 2, ',', '.'))->implode(' + ');
        $totalPrincipal = $cuota->totalAcobrar($al);

        $out = $vacio;
        DB::transaction(function () use ($cuota, $venta, $ref, $partes, $unMedio, $medioCobro, $detMedios, $montoRecibido, $opts, $al, $totalPrincipal, &$out) {
            // 1) Cabecera del cobro (registro operativo).
            $cobro = Cobro::create([
                'cuota_id' => $cuota->id,
                'venta_id' => $venta?->id,
                'cliente_id' => $cuota->cliente_id,
                'cobrador_id' => $opts['cobrador_id'] ?? $cuota->cobradorActual()?->id,
                'zona_id' => $cuota->zona_id,
                'monto' => $montoRecibido,
                'medio' => $medioCobro,
                'comprobante' => $unMedio ? $partes[0]['comprobante'] : null,
                'banco' => $unMedio ? $partes[0]['banco'] : null,
                'cheque_numero' => $unMedio ? $partes[0]['cheque_numero'] : null,
                'excedente' => max(0.0, round($montoRecibido - $totalPrincipal, 2)),
                'registrado_por' => auth()->id(),
                'fecha' => now(),
            ]);

            // 2) Una parte + un ingreso de caja POR MEDIO (cruce con Tesorería en su bucket).
            foreach ($partes as $p) {
                CobroMedio::create([
                    'cobro_id' => $cobro->id, 'medio' => $p['medio'], 'monto' => $p['monto'],
                    'comprobante' => $p['comprobante'], 'banco' => $p['banco'], 'cheque_numero' => $p['cheque_numero'],
                ]);
                MovimientoCaja::create([
                    'tipo' => 'ingreso', 'medio' => Cobro::MEDIOS[$p['medio']] ?? ucfirst($p['medio']),
                    'concepto' => "Cobro Venta {$ref} (" . (Cobro::MEDIOS[$p['medio']] ?? $p['medio']) . ')',
                    'monto' => $p['monto'], 'fecha' => now(), 'referencia' => $ref,
                ]);
            }

            // 3) Haber único al cliente por el TOTAL (con el detalle del/los medio/s).
            MovimientoCliente::create([
                'cliente_id' => $cuota->cliente_id, 'tipo' => 'haber',
                'concepto' => "Cobro Venta {$ref} ({$detMedios})", 'monto' => $montoRecibido,
                'fecha' => now(), 'referencia' => $ref,
            ]);

            // 4) Imputar el total a la cuota y a las siguientes pendientes del MISMO crédito.
            $restante = $montoRecibido;
            $saldadas = 0;
            $cuotas = Cuota::where('venta_id', $venta?->id)->where('estado', 'pendiente')
                ->where('numero', '>=', $cuota->numero)->orderBy('numero')->get();

            foreach ($cuotas as $c) {
                if ($restante <= 0.005) {
                    break;
                }
                $mora = $c->mora($al);
                $totalC = round($c->saldo() + $mora, 2);

                if ($restante + 0.005 >= $totalC) {
                    if ($mora > 0) {
                        MovimientoCliente::create([
                            'cliente_id' => $c->cliente_id, 'tipo' => 'debe',
                            'concepto' => "Mora cuota {$c->numero} Venta {$ref} ({$c->diasMora($al)} días)",
                            'monto' => $mora, 'fecha' => now(), 'referencia' => $ref,
                        ]);
                    }
                    $c->update(['estado' => 'cobrada', 'pagado_monto' => (float) $c->monto, 'cobrada_at' => now()]);
                    $restante = round($restante - $totalC, 2);
                    $saldadas++;
                } else {
                    $c->update(['pagado_monto' => round((float) $c->pagado_monto + $restante, 2)]);
                    $restante = 0.0;
                }
            }

            $out = [
                'ok' => true,
                'cobro_id' => $cobro->id,
                'monto' => $montoRecibido,
                'excedente' => (float) $cobro->excedente,
                'cuotas_saldadas' => $saldadas,
                'saldo_a_favor' => round(max(0.0, $restante), 2),
            ];
        });

        // Recibo por mail al cliente (comprobante + alerta post-cobro). Después del commit y
        // protegido: un fallo de mail NO rompe el cobro (ver Recibo::enviarPorMail).
        $out['recibo_enviado'] = false;
        if (($out['ok'] ?? false) && ! empty($out['cobro_id']) && ($cobroModel = Cobro::find($out['cobro_id']))) {
            $out['recibo_enviado'] = Recibo::enviarPorMail($cobroModel);
        }

        $msg = 'Cobro registrado: $' . number_format($out['monto'], 2, ',', '.') . ' (' . ($unMedio ? ($detMedios) : "mixto: {$detMedios}") . ').';
        if (($out['cuotas_saldadas'] ?? 0) > 1) {
            $msg .= " Saldó {$out['cuotas_saldadas']} cuotas.";
        }
        if (($out['saldo_a_favor'] ?? 0) > 0) {
            $msg .= ' Quedó $' . number_format($out['saldo_a_favor'], 2, ',', '.') . ' a favor del cliente.';
        } elseif (($out['excedente'] ?? 0) > 0 && ($out['cuotas_saldadas'] ?? 0) >= 1) {
            $msg .= ' El excedente adelantó la próxima cuota.';
        }

        return $out + ['mensaje' => $msg];
    }

    /**
     * Cobra una cuota por su total exacto (saldo + mora) en un solo medio. Atajo de compatibilidad
     * usado por la planilla del tablero y la ficha de Clientes; delega en registrarPago().
     */
    public static function cobrarCuota(Cuota $cuota, string $medio = 'efectivo', ?Carbon $al = null): array
    {
        $al = $al ?? Carbon::today();

        return self::registrarPago($cuota, [['medio' => strtolower($medio), 'monto' => $cuota->totalAcobrar($al)]], [], $al);
    }
}
