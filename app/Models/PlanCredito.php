<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Plan de crédito configurable (catálogo editable en Configuración).
 * El motor de cálculo vive en App\Support\PlanesCredito, que lee de acá.
 */
class PlanCredito extends Model
{
    protected $table = 'planes_credito';

    protected $fillable = ['codigo', 'nombre', 'modalidad', 'anticipo_pct', 'tasa_periodo', 'plazo_default', 'unidad', 'activo', 'orden'];

    protected $casts = [
        'anticipo_pct' => 'decimal:2',
        'tasa_periodo' => 'decimal:4',
        'plazo_default' => 'integer',
        'activo' => 'boolean',
    ];

    /** Modalidades posibles. */
    public const MODALIDADES = ['contado' => 'Contado', 'diario' => 'Diario', 'semanal' => 'Semanal'];

    /** ¿Es financiación propia (no contado)? */
    public function esCredito(): bool
    {
        return $this->modalidad !== 'contado';
    }
}
