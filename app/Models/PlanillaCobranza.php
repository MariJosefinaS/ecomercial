<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Planilla de cobranza del cobrador (Bloque 2): encabezado con apertura/cierre + estado + totales.
 * Las líneas (cuotas del día) son derivadas del cronograma — ver App\Livewire\Cobranza\Planilla.
 */
class PlanillaCobranza extends Model
{
    protected $table = 'planillas_cobranza';

    protected $fillable = [
        'cobrador_id', 'fecha', 'modalidad', 'estado',
        'hora_apertura', 'hora_cierre', 'total_esperado', 'total_cobrado',
        'auditada_por', 'auditada_at',
    ];

    protected $casts = [
        'fecha' => 'date',
        'hora_apertura' => 'datetime',
        'hora_cierre' => 'datetime',
        'auditada_at' => 'datetime',
        'total_esperado' => 'decimal:2',
        'total_cobrado' => 'decimal:2',
    ];

    public const ESTADOS = [
        'en_confeccion' => 'En confección',
        'pend_auditoria' => 'Cargada — pend. auditoría',
        'cerrada' => 'Cerrada',
    ];

    public const MODALIDADES = ['diario' => 'Diaria', 'semanal' => 'Semanal', 'mensual' => 'Mensual'];

    public function cobrador(): BelongsTo { return $this->belongsTo(User::class, 'cobrador_id'); }
    public function auditor(): BelongsTo { return $this->belongsTo(User::class, 'auditada_por'); }

    public function estadoLabel(): string { return self::ESTADOS[$this->estado] ?? $this->estado; }
    public function modalidadLabel(): string { return self::MODALIDADES[$this->modalidad] ?? ucfirst($this->modalidad); }

    public function abierta(): bool { return $this->hora_apertura !== null && $this->estado === 'en_confeccion'; }
    public function cerrada(): bool { return $this->estado === 'cerrada'; }
}
