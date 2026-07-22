<?php

namespace App\Support;

use App\Models\MovimientoCaja;
use App\Models\PagoProveedor;
use App\Models\PedidoPago;
use Illuminate\Support\Facades\DB;

/**
 * Tablero de autorización de pagos: SOLICITAR → AUTORIZAR (jefe) → PROCESAR (tesorero).
 * El egreso en caja y la imputación de la obligación ocurren SOLO al procesar (nunca antes).
 */
class Pagos
{
    /** Crea un pedido de pago (pendiente, o pre-autorizado si ya viene aprobado, ej. adelanto). */
    public static function solicitar(array $d, int $userId): PedidoPago
    {
        $pre = (bool) ($d['preautorizado'] ?? false);

        return PedidoPago::create([
            'tipo' => $d['tipo'],
            'proveedor_id' => $d['proveedor_id'] ?? null,
            'obligacion_id' => $d['obligacion_id'] ?? null,
            'empleado_id' => $d['empleado_id'] ?? null,
            'adelanto_id' => $d['adelanto_id'] ?? null,
            'beneficiario' => $d['beneficiario'],
            'concepto' => $d['concepto'],
            'importe' => round((float) $d['importe'], 2),
            'medio' => $d['medio'],
            'comprobante' => $d['comprobante'] ?? null,
            'banco' => $d['banco'] ?? null,
            'cheque_numero' => $d['cheque_numero'] ?? null,
            'comentario' => $d['comentario'] ?? null,
            'estado' => $pre ? 'autorizado' : 'pendiente',
            'solicitado_por' => $userId,
            'autorizado_por' => $pre ? $userId : null,
            'autorizado_at' => $pre ? now() : null,
        ]);
    }

    public static function autorizar(PedidoPago $p, int $userId): bool
    {
        if ($p->estado !== 'pendiente') {
            return false;
        }
        $p->update(['estado' => 'autorizado', 'autorizado_por' => $userId, 'autorizado_at' => now()]);

        return true;
    }

    public static function rechazar(PedidoPago $p, int $userId, ?string $motivo): bool
    {
        if ($p->estado !== 'pendiente') {
            return false;
        }
        $p->update(['estado' => 'rechazado', 'autorizado_por' => $userId, 'autorizado_at' => now(), 'motivo_rechazo' => $motivo ?: null]);

        return true;
    }

    public static function anular(PedidoPago $p, int $userId): bool
    {
        if ($p->estado === 'pagado') {
            return false; // un pago ya procesado no se anula acá
        }
        $p->update(['estado' => 'anulado', 'autorizado_por' => $userId, 'autorizado_at' => now()]);

        return true;
    }

    /** Procesa un pedido AUTORIZADO: hace el egreso real + imputa la obligación / crea el pago. */
    public static function procesar(PedidoPago $p, int $userId): array
    {
        if ($p->estado !== 'autorizado') {
            return ['ok' => false, 'mensaje' => 'El pedido no está autorizado.'];
        }

        return DB::transaction(function () use ($p, $userId) {
            $ref = null;

            if ($p->tipo === 'empleado' && $p->empleado_id) {
                $pago = CuentaEmpleado::registrarPago($p->empleado_id, (float) $p->importe, $p->medio, [
                    'comprobante' => $p->comprobante, 'banco' => $p->banco, 'nota' => $p->comentario, 'adelanto_id' => $p->adelanto_id,
                ], $userId);
                $ref = 'PagoEmpleado:' . $pago->id;
            } elseif ($p->tipo === 'proveedor' && $p->obligacion_id) {
                $ref = self::aplicarPagoProveedor($p);
            } else { // gasto genérico
                MovimientoCaja::create([
                    'tipo' => 'egreso', 'medio' => PedidoPago::MEDIOS[$p->medio] ?? ucfirst($p->medio),
                    'concepto' => $p->concepto . ' — ' . $p->beneficiario, 'monto' => (float) $p->importe, 'fecha' => now(), 'referencia' => 'PAGO',
                ]);
                $ref = 'Gasto';
            }

            $p->update(['estado' => 'pagado', 'procesado_por' => $userId, 'procesado_at' => now(), 'resultado_ref' => $ref]);

            return ['ok' => true, 'ref' => $ref, 'mensaje' => 'Pago procesado — egreso en caja registrado.'];
        });
    }

    /** Imputa el pago a la obligación del proveedor + egreso en caja. Devuelve la ref. */
    private static function aplicarPagoProveedor(PedidoPago $p): string
    {
        $ob = PagoProveedor::with('proveedor:id,nombre', 'compra:id,numero,factura_numero')->find($p->obligacion_id);
        if (! $ob) {
            return 'PagoProveedor:?';
        }
        $saldo = round(max(0, (float) $ob->monto - (float) $ob->monto_pagado), 2);
        $monto = min((float) $p->importe, $saldo);
        $ob->monto_pagado = round((float) $ob->monto_pagado + $monto, 2);
        $ob->fecha_pago = now();
        $ob->estado = $ob->monto_pagado >= (float) $ob->monto - 0.005 ? 'pagado' : 'parcial';
        $ob->save();

        $fac = $ob->compra?->factura_numero ?: ($ob->compra?->numero ?? '');
        MovimientoCaja::create([
            'tipo' => 'egreso', 'medio' => PedidoPago::MEDIOS[$p->medio] ?? ucfirst($p->medio),
            'concepto' => 'Pago a proveedor ' . ($ob->proveedor?->nombre ?? '') . ($fac ? " · Factura {$fac}" : ''),
            'monto' => $monto, 'fecha' => now(), 'referencia' => 'PAGOPROV',
        ]);

        return 'PagoProveedor:' . $ob->id;
    }

    /** Totales por estado (para el tablero). */
    public static function totales()
    {
        return PedidoPago::selectRaw('estado, count(*) as c, sum(importe) as total')->groupBy('estado')
            ->get()->keyBy('estado');
    }
}
