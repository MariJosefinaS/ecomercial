<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Movimiento de la cuenta del empleado. 'haber' = comisión devengada; 'debe' = pago. Ver App\Support\CuentaEmpleado. */
class MovimientoEmpleado extends Model
{
    protected $table = 'movimientos_empleado';

    protected $fillable = ['empleado_id', 'tipo', 'concepto', 'monto', 'referencia', 'fecha', 'registrado_por'];

    protected $casts = ['monto' => 'decimal:2', 'fecha' => 'datetime'];

    public function empleado(): BelongsTo { return $this->belongsTo(User::class, 'empleado_id'); }
    public function registrador(): BelongsTo { return $this->belongsTo(User::class, 'registrado_por'); }

    /** Signo del movimiento sobre el saldo a favor del empleado (+ haber / − debe). */
    public function signo(): int { return $this->tipo === 'debe' ? -1 : 1; }
}
