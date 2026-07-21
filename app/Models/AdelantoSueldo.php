<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Adelanto de sueldo: solicitado por el empleado → aprobado por super_admin → pagado por tesorería. */
class AdelantoSueldo extends Model
{
    protected $table = 'adelantos_sueldo';

    protected $fillable = [
        'empleado_id', 'monto', 'motivo', 'estado', 'aprobado_por', 'aprobado_at',
        'motivo_rechazo', 'pago_empleado_id',
    ];

    protected $casts = ['monto' => 'decimal:2', 'aprobado_at' => 'datetime'];

    public const ESTADOS = [
        'pendiente' => 'Pendiente de aprobación',
        'aprobado' => 'Aprobado (a pagar)',
        'rechazado' => 'Rechazado',
        'pagado' => 'Pagado',
    ];

    public function empleado(): BelongsTo { return $this->belongsTo(User::class, 'empleado_id'); }
    public function aprobador(): BelongsTo { return $this->belongsTo(User::class, 'aprobado_por'); }
    public function pago(): BelongsTo { return $this->belongsTo(PagoEmpleado::class, 'pago_empleado_id'); }

    public function estadoLabel(): string { return self::ESTADOS[$this->estado] ?? $this->estado; }
}
