<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConceptoPrecio extends Model
{
    protected $table = 'conceptos_precio';

    protected $fillable = ['nombre', 'ambito', 'porcentaje', 'activo', 'orden'];

    protected $casts = ['porcentaje' => 'decimal:2', 'activo' => 'boolean'];

    /** Ámbitos posibles: 'costo' (recarga el costo) | 'venta' (recarga el precio de venta). */
    public const AMBITOS = ['costo' => 'Costo', 'venta' => 'Venta'];
}
