<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $table = 'clientes';

    protected $fillable = ['nombre', 'tipo_doc', 'documento', 'telefono', 'email', 'fecha_nacimiento', 'direccion', 'zona_id', 'limite_credito', 'riesgo', 'activo', 'aprobado'];

    protected $casts = ['limite_credito' => 'decimal:2', 'activo' => 'boolean', 'aprobado' => 'boolean', 'fecha_nacimiento' => 'date'];

    public function zona(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Zona::class); }
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
}
