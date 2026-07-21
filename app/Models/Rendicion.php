<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Rendición de efectivo de un cobrador en una fecha (esperado vs recibido). Ver App\Support\Rendicion. */
class Rendicion extends Model
{
    protected $table = 'rendiciones';

    protected $fillable = [
        'cobrador_id', 'fecha', 'total_esperado', 'total_recibido', 'diferencia',
        'cantidad_cobros', 'nota', 'registrado_por',
    ];

    protected $casts = [
        'fecha' => 'date',
        'total_esperado' => 'decimal:2',
        'total_recibido' => 'decimal:2',
        'diferencia' => 'decimal:2',
    ];

    public function cobrador(): BelongsTo { return $this->belongsTo(User::class, 'cobrador_id'); }
    public function registrador(): BelongsTo { return $this->belongsTo(User::class, 'registrado_por'); }
}
