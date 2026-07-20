<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Devolucion extends Model
{
    protected $table = 'devoluciones';

    protected $fillable = [
        'cliente_id', 'venta_id', 'unidad_id', 'producto_id', 'producto', 'cantidad', 'monto',
        'motivo', 'medio_pago', 'condicion', 'estado_producto', 'fecha', 'estado',
    ];

    protected $casts = ['monto' => 'decimal:2', 'cantidad' => 'integer', 'fecha' => 'date'];

    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
    public function venta(): BelongsTo { return $this->belongsTo(Venta::class); }
    public function unidad(): BelongsTo { return $this->belongsTo(UnidadTrazable::class, 'unidad_id'); }
    public function productoRel(): BelongsTo { return $this->belongsTo(Producto::class, 'producto_id'); }
}
