<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $table = 'clientes';

    protected $fillable = ['nombre', 'numero_cuenta', 'tipo_doc', 'documento', 'telefono', 'email', 'fecha_nacimiento', 'direccion', 'zona_id', 'limite_credito', 'riesgo', 'activo', 'aprobado'];

    protected $casts = ['numero_cuenta' => 'integer', 'limite_credito' => 'decimal:2', 'activo' => 'boolean', 'aprobado' => 'boolean', 'fecha_nacimiento' => 'date'];

    /** Asigna el número de cuenta (fijo, único) al dar de alta si no se pasó. */
    protected static function booted(): void
    {
        static::creating(function (Cliente $c) {
            $c->numero_cuenta ??= (int) (static::max('numero_cuenta') ?? 38000) + 1;
        });
    }

    public function zona(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Zona::class); }
    public function domicilios(): HasMany { return $this->hasMany(DomicilioCliente::class); }
    public function cheques(): HasMany { return $this->hasMany(ChequeCliente::class); }
    public function devoluciones(): HasMany { return $this->hasMany(Devolucion::class); }
    public function movimientos(): HasMany { return $this->hasMany(MovimientoCliente::class); }

    /** Saldo de cuenta corriente (debe - haber). */
    public function saldo(): float
    {
        return (float) $this->movimientos->reduce(
            fn ($acc, $m) => $acc + ($m->tipo === 'debe' ? $m->monto : -$m->monto),
            0
        );
    }

    public function esRiesgoAlto(): bool { return $this->riesgo === 'alto'; }

    /** Domicilios activos, el principal primero. */
    public function domiciliosActivos()
    {
        return $this->domicilios()->activos()->orderByDesc('es_principal')->orderBy('etiqueta')->get();
    }

    /** Domicilio principal (o el primero activo si ninguno está marcado). */
    public function domicilioPrincipal(): ?DomicilioCliente
    {
        return $this->domicilios()->activos()->orderByDesc('es_principal')->orderBy('id')->first();
    }

    /** Dónde se cobra: primer domicilio activo que sirva para cobro; si no, la dirección del cliente. */
    public function domicilioDeCobro(): ?DomicilioCliente
    {
        return $this->domicilios()->activos()->whereIn('uso', ['ambos', 'cobro'])
            ->orderByDesc('es_principal')->orderBy('id')->first();
    }
}
