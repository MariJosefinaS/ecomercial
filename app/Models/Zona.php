<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Zona de cobranza configurable. El `cobrador` es el User ACTUAL asignado; reasignarlo mueve
 * automáticamente las cuotas abiertas de la zona (la cuota resuelve su cobrador vía `zona`).
 */
class Zona extends Model
{
    protected $table = 'zonas';

    protected $fillable = ['nombre', 'local_id', 'cobrador_id', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function local(): BelongsTo { return $this->belongsTo(Local::class); }
    public function cobrador(): BelongsTo { return $this->belongsTo(User::class, 'cobrador_id'); }
    public function clientes(): HasMany { return $this->hasMany(Cliente::class); }
    public function ventas(): HasMany { return $this->hasMany(Venta::class); }
    public function cuotas(): HasMany { return $this->hasMany(Cuota::class); }
}
