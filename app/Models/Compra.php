<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compra extends Model
{
    protected $table = 'compras';

    protected $fillable = ['numero', 'proveedor_id', 'local_id', 'usuario_id', 'recibido_por', 'factura_numero', 'fecha', 'fecha_estimada', 'fecha_llegada', 'recibido_at', 'total', 'desglose', 'estado'];

    protected $casts = ['fecha' => 'date', 'fecha_estimada' => 'date', 'fecha_llegada' => 'date', 'recibido_at' => 'datetime', 'total' => 'decimal:2', 'desglose' => 'array'];

    public function proveedor(): BelongsTo { return $this->belongsTo(Proveedor::class); }
    public function local(): BelongsTo { return $this->belongsTo(Local::class); }
    public function usuario(): BelongsTo { return $this->belongsTo(User::class, 'usuario_id'); }
    public function recibidoPor(): BelongsTo { return $this->belongsTo(User::class, 'recibido_por'); }
    public function items(): HasMany { return $this->hasMany(CompraItem::class); }
    public function pagos(): HasMany { return $this->hasMany(PagoProveedor::class); }
    public function remitos(): HasMany { return $this->hasMany(Remito::class); }

    /** ¿Quedan productos por recibir (saldo pendiente en algún ítem)? */
    public function tienePendiente(): bool
    {
        $this->loadMissing('items');

        return $this->items->contains(fn (CompraItem $it) => $it->pendiente() > 0);
    }
}
