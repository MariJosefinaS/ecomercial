<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompraItem extends Model
{
    protected $table = 'compra_items';

    protected $fillable = ['compra_id', 'producto_id', 'cantidad', 'cantidad_recibida', 'cantidad_defectuosa', 'cantidad_faltante', 'estado_item', 'nota_recepcion', 'costo_unitario'];

    protected $casts = ['cantidad' => 'integer', 'cantidad_recibida' => 'integer', 'cantidad_defectuosa' => 'integer', 'cantidad_faltante' => 'integer', 'costo_unitario' => 'decimal:2'];

    public function compra(): BelongsTo { return $this->belongsTo(Compra::class); }
    public function producto(): BelongsTo { return $this->belongsTo(Producto::class); }

    public function remitoItems(): HasMany { return $this->hasMany(RemitoItem::class); }

    /** Total recibido (sumando todos los remitos) de esta línea de factura. */
    public function recibidoTotal(): int
    {
        return $this->relationLoaded('remitoItems')
            ? (int) $this->remitoItems->sum('cantidad_recibida')
            : (int) $this->remitoItems()->sum('cantidad_recibida');
    }

    /** Saldo pendiente de entrega = facturado − recibido en remitos. */
    public function pendiente(): int
    {
        return max(0, (int) $this->cantidad - $this->recibidoTotal());
    }
}
