<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChequeCliente extends Model
{
    protected $table = 'cheques_cliente';

    protected $fillable = ['cliente_id', 'venta_id', 'numero', 'banco', 'monto', 'fecha_vencimiento', 'fecha_deposito', 'estado', 'motivo_rechazo', 'endosado_a_proveedor_id', 'endosado_at'];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha_vencimiento' => 'date',
        'fecha_deposito' => 'date',
        'endosado_at' => 'datetime',
    ];

    public const ESTADOS = ['pendiente' => 'En cartera', 'depositado' => 'Depositado', 'acreditado' => 'Acreditado', 'rechazado' => 'Rechazado', 'endosado' => 'Endosado'];

    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
    public function venta(): BelongsTo { return $this->belongsTo(Venta::class); }
    public function endosadoA(): BelongsTo { return $this->belongsTo(Proveedor::class, 'endosado_a_proveedor_id'); }

    /** Cheques disponibles en cartera (todavía no depositados, endosados ni rechazados). */
    public function scopeEnCartera($q) { return $q->where('estado', 'pendiente'); }

    public function estadoLabel(): string { return self::ESTADOS[$this->estado] ?? $this->estado; }

    /** Fecha en la que este cheque se hace efectivo (depósito, o el vencimiento si no se calculó). */
    public function fechaEfectiva(): ?Carbon
    {
        return $this->fecha_deposito ?? $this->fecha_vencimiento;
    }

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
