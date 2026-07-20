<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemitoItem extends Model
{
    protected $table = 'remito_items';

    protected $fillable = [
        'remito_id', 'compra_item_id', 'producto_id',
        'cantidad_recibida', 'cantidad_defectuosa', 'estado_item', 'costo_unitario', 'nota',
    ];

    protected $casts = [
        'cantidad_recibida' => 'integer',
        'cantidad_defectuosa' => 'integer',
        'costo_unitario' => 'decimal:2',
    ];

    public function remito(): BelongsTo { return $this->belongsTo(Remito::class); }
    public function compraItem(): BelongsTo { return $this->belongsTo(CompraItem::class); }
    public function producto(): BelongsTo { return $this->belongsTo(Producto::class); }

    public function unidades(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UnidadTrazable::class, 'remito_item_id');
    }
}
