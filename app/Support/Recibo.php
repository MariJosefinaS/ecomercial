<?php

namespace App\Support;

use App\Mail\ReciboCobro;
use App\Models\Cobro;
use App\Models\Cuota;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Recibo de un cobro: comprobante para el cliente (producto/plan/cuota, cuotas que faltan,
 * restante si paga en término, detalle de medios) con logo. Se genera en PDF y se envía por mail.
 * Es también la ALERTA post-cobro y el respaldo ante robo del cobrador (el cliente tiene comprobante).
 */
class Recibo
{
    /** Número legible del recibo (mismo id del cobro, con prefijo). */
    public static function numero(Cobro $cobro): string
    {
        return 'REC-' . str_pad((string) $cobro->id, 6, '0', STR_PAD_LEFT);
    }

    /** Datos completos del recibo para la vista/PDF/mail. */
    public static function datos(Cobro $cobro): array
    {
        $cobro->loadMissing(['cliente', 'cobrador', 'cuota', 'venta.items.producto', 'medios']);
        $venta = $cobro->venta;
        $cuota = $cobro->cuota;

        // Cronograma del crédito (estado ya refleja este cobro, que se aplicó antes).
        $todas = $venta ? Cuota::where('venta_id', $venta->id)->orderBy('numero')->get() : collect();
        $pendientes = $todas->where('estado', 'pendiente');
        $totalCuotas = $todas->count() ?: (int) ($venta?->plazo ?? 0);
        $cuotasPagadas = $todas->where('estado', 'cobrada')->count();
        $cuotasFaltan = $pendientes->count();
        $restanteTermino = round($pendientes->sum(fn (Cuota $c) => $c->saldo()), 2);
        $proxVence = $pendientes->sortBy('numero')->first()?->fecha_vencimiento;

        $productos = $venta
            ? $venta->items->map(fn ($it) => [
                'nombre' => $it->producto?->nombre ?? 'Producto',
                'cantidad' => (int) $it->cantidad,
                'precio' => (float) $it->precio_unitario,
                'subtotal' => round((float) $it->cantidad * (float) $it->precio_unitario, 2),
            ])->all()
            : [];

        $medios = $cobro->medios->map(fn ($m) => [
            'medio' => $m->medioLabel(),
            'monto' => (float) $m->monto,
            'banco' => $m->banco,
            'cheque_numero' => $m->cheque_numero,
        ])->all();
        // Fallback: cobro viejo sin CobroMedio → una sola parte con el medio del cabecera.
        if (empty($medios)) {
            $medios = [[
                'medio' => $cobro->medioLabel(), 'monto' => (float) $cobro->monto,
                'banco' => $cobro->banco, 'cheque_numero' => $cobro->cheque_numero,
            ]];
        }

        return [
            'numero' => self::numero($cobro),
            'fecha' => $cobro->fecha,
            'cliente' => $cobro->cliente,
            'cobrador' => $cobro->cobrador,
            'venta' => $venta,
            'cuota' => $cuota,
            'productos' => $productos,
            'medios' => $medios,
            'monto' => (float) $cobro->monto,
            'excedente' => (float) $cobro->excedente,
            'total_cuotas' => $totalCuotas,
            'cuotas_pagadas' => $cuotasPagadas,
            'cuotas_faltan' => $cuotasFaltan,
            'restante_termino' => $restanteTermino,
            'prox_vence' => $proxVence,
        ];
    }

    /** Instancia dompdf del recibo (A4 vertical). */
    public static function pdf(Cobro $cobro)
    {
        return \Barryvdh\DomPDF\Facade\Pdf::loadView('cobros.recibo-pdf', self::datos($cobro))
            ->setPaper('a4', 'portrait');
    }

    public static function nombreArchivo(Cobro $cobro): string
    {
        return 'recibo_' . self::numero($cobro) . '.pdf';
    }

    /**
     * Envía el recibo por mail al cliente (si tiene email). Guarda recibo_enviado_at.
     * No lanza excepción: un fallo de mail NUNCA debe romper el cobro. Devuelve true si se envió.
     */
    public static function enviarPorMail(Cobro $cobro): bool
    {
        $cobro->loadMissing('cliente');
        $email = $cobro->cliente?->email;
        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            Mail::to($email)->send(new ReciboCobro($cobro));
            $cobro->forceFill(['recibo_enviado_at' => now(), 'recibo_email' => $email])->save();

            return true;
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar el recibo de cobro #' . $cobro->id . ': ' . $e->getMessage());

            return false;
        }
    }
}
