<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaEscaneo extends Model
{
    protected $table = 'factura_escaneos';

    protected $fillable = [
        'compra_id', 'archivo', 'modelo', 'estado',
        'cabecera', 'lineas', 'error', 'creado_por',
    ];

    protected $casts = [
        'cabecera' => 'array',
        'lineas' => 'array',
    ];

    public function compra(): BelongsTo { return $this->belongsTo(Compra::class); }
    public function creadoPor(): BelongsTo { return $this->belongsTo(User::class, 'creado_por'); }
}
