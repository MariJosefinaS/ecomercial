<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLocal extends Model
{
    protected $table = 'stock_locales';

    protected $fillable = ['producto_id', 'local_id', 'cantidad', 'stock_minimo', 'precio_venta'];

    protected $casts = [
        'cantidad' => 'integer',
        'stock_minimo' => 'integer',
        'precio_venta' => 'decimal:2',
    ];

    public function producto(): BelongsTo { return $this->belongsTo(Producto::class); }
    public function local(): BelongsTo { return $this->belongsTo(Local::class); }

    public function bajoMinimo(): bool { return $this->cantidad <= $this->stock_minimo; }
}
