<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaItem extends Model
{
    protected $table = 'venta_items';

    protected $fillable = ['venta_id', 'producto_id', 'cantidad', 'precio_unitario', 'sugerido'];

    protected $casts = ['cantidad' => 'integer', 'precio_unitario' => 'decimal:2', 'sugerido' => 'boolean'];

    public function venta(): BelongsTo { return $this->belongsTo(Venta::class); }
    public function producto(): BelongsTo { return $this->belongsTo(Producto::class); }
}
