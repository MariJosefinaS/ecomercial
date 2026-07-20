<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends Model
{
    protected $table = 'proveedores';

    protected $fillable = ['codigo_externo', 'nombre', 'rubro', 'cuit', 'telefono', 'email', 'direccion', 'dias_entrega', 'activo', 'costea_con_iva', 'iva_pct'];

    protected $casts = ['activo' => 'boolean', 'dias_entrega' => 'integer', 'costea_con_iva' => 'boolean', 'iva_pct' => 'decimal:2'];

    public function productos(): HasMany { return $this->hasMany(Producto::class); }
    public function compras(): HasMany { return $this->hasMany(Compra::class); }
    public function pagos(): HasMany { return $this->hasMany(PagoProveedor::class); }
    public function cheques(): HasMany { return $this->hasMany(Cheque::class); }

    /** Conceptos de precio que cobra este proveedor, con su % por defecto (pivot). */
    public function conceptos(): BelongsToMany
    {
        return $this->belongsToMany(ConceptoPrecio::class, 'concepto_proveedor')
            ->withPivot('porcentaje')
            ->withTimestamps()
            ->orderBy('orden');
    }
}
