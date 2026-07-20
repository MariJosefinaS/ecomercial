<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChequeCliente extends Model
{
    protected $table = 'cheques_cliente';

    protected $fillable = ['cliente_id', 'venta_id', 'numero', 'banco', 'monto', 'fecha_vencimiento', 'fecha_deposito', 'estado', 'motivo_rechazo'];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha_vencimiento' => 'date',
        'fecha_deposito' => 'date',
    ];

    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
    public function venta(): BelongsTo { return $this->belongsTo(Venta::class); }

    /** Fecha de depósito = vencimiento + 1 día hábil. */
    public static function calcularDeposito(string|Carbon $vencimiento): Carbon
    {
        return Carbon::parse($vencimiento)->addWeekday();
    }

    /** Un cheque rechazado NO cuenta como pago acreditado. */
    public function cuentaComoPago(): bool
    {
        return $this->estado === 'acreditado';
    }
}
