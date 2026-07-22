<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'telefono', 'avatar', 'password', 'rol', 'local_id', 'activo', 'comision_pct', 'ultimo_acceso', 'acceso_previo', 'notificaciones_vistas_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'comision_pct' => 'decimal:2',
            'ultimo_acceso' => 'datetime',
            'acceso_previo' => 'datetime',
            'notificaciones_vistas_at' => 'datetime',
        ];
    }

    /** % de comisión efectivo del cobrador: el propio, o el general si no tiene uno. */
    public function comisionPctEfectivo(): float
    {
        return $this->comision_pct !== null
            ? (float) $this->comision_pct
            : \App\Models\Parametro::num('comision_cobrador_general', 0);
    }

    public function local(): BelongsTo { return $this->belongsTo(Local::class); }
    public function ventas(): HasMany { return $this->hasMany(Venta::class, 'vendedor_id'); }
    public function solicitudes(): HasMany { return $this->hasMany(SolicitudCompra::class, 'solicitante_id'); }

    /** Zonas de cobranza donde este usuario es el cobrador asignado. */
    public function zonasComoCobrador(): HasMany { return $this->hasMany(Zona::class, 'cobrador_id'); }

    public function esRol(string ...$roles): bool { return in_array($this->rol, $roles, true); }

    /** ¿Es cobrador? = tiene al menos una zona asignada (reuso: no hay rol dedicado). */
    public function esCobrador(): bool { return $this->zonasComoCobrador()->exists(); }
}
