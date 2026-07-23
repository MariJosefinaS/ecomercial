<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pedido de pago del tablero de autorización. Ver App\Support\Pagos. */
class PedidoPago extends Model
{
    protected $table = 'pedidos_pago';

    protected $fillable = [
        'tipo', 'proveedor_id', 'obligacion_id', 'empleado_id', 'adelanto_id',
        'beneficiario', 'concepto', 'importe', 'medio', 'comprobante', 'banco', 'cheque_numero', 'cheque_cliente_id', 'comentario',
        'estado', 'solicitado_por', 'autorizado_por', 'autorizado_at', 'motivo_rechazo',
        'procesado_por', 'procesado_at', 'resultado_ref',
    ];

    protected $casts = ['importe' => 'decimal:2', 'autorizado_at' => 'datetime', 'procesado_at' => 'datetime'];

    public const ESTADOS = [
        'pendiente' => 'Pendiente de autorización',
        'autorizado' => 'Autorizado (a pagar)',
        'rechazado' => 'Rechazado',
        'pagado' => 'Pagado',
        'anulado' => 'Anulado',
    ];

    public const MEDIOS = ['transferencia' => 'Transferencia', 'efectivo' => 'Efectivo', 'cheque' => 'Cheque'];

    /** Cheque de terceros que se endosa para saldar este pedido (si el pago es por endoso). */
    public function chequeCliente(): BelongsTo { return $this->belongsTo(ChequeCliente::class, 'cheque_cliente_id'); }

    public function solicitante(): BelongsTo { return $this->belongsTo(User::class, 'solicitado_por'); }
    public function autorizador(): BelongsTo { return $this->belongsTo(User::class, 'autorizado_por'); }
    public function procesador(): BelongsTo { return $this->belongsTo(User::class, 'procesado_por'); }
    public function proveedor(): BelongsTo { return $this->belongsTo(Proveedor::class); }
    public function empleado(): BelongsTo { return $this->belongsTo(User::class, 'empleado_id'); }
    public function obligacion(): BelongsTo { return $this->belongsTo(PagoProveedor::class, 'obligacion_id'); }

    public function estadoLabel(): string { return self::ESTADOS[$this->estado] ?? $this->estado; }
    public function medioLabel(): string { return self::MEDIOS[$this->medio] ?? ucfirst($this->medio); }
    public function tipoLabel(): string { return ['proveedor' => 'Proveedor', 'empleado' => 'Empleado', 'gasto' => 'Gasto'][$this->tipo] ?? $this->tipo; }
}
