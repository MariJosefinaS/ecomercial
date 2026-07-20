<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Unidad física trazable (una caja). El código se imprime en la etiqueta al
 * recibir y permite reconstruir toda la historia del producto.
 */
class UnidadTrazable extends Model
{
    protected $table = 'unidades_trazables';

    protected $fillable = [
        'codigo', 'remito_item_id', 'producto_id', 'proveedor_id', 'local_id',
        'costo', 'estado', 'venta_id', 'entregado_at', 'devuelto_at',
    ];

    protected $casts = [
        'costo' => 'decimal:2',
        'entregado_at' => 'datetime',
        'devuelto_at' => 'datetime',
    ];

    public const EN_STOCK = 'en_stock';
    public const ENTREGADO = 'entregado';
    public const DEVUELTO = 'devuelto';
    public const EN_REPARACION = 'en_reparacion';
    public const BAJA = 'baja';

    public function producto(): BelongsTo { return $this->belongsTo(Producto::class); }
    public function proveedor(): BelongsTo { return $this->belongsTo(Proveedor::class); }
    public function local(): BelongsTo { return $this->belongsTo(Local::class); }
    public function venta(): BelongsTo { return $this->belongsTo(Venta::class); }
    public function remitoItem(): BelongsTo { return $this->belongsTo(RemitoItem::class); }
    public function eventos(): HasMany { return $this->hasMany(UnidadEvento::class, 'unidad_id'); }

    /** Registra un evento en la historia de la unidad. */
    public function registrar(string $tipo, ?int $localId = null, ?string $referencia = null, ?string $nota = null): UnidadEvento
    {
        return $this->eventos()->create([
            'tipo' => $tipo,
            'local_id' => $localId ?? $this->local_id,
            'referencia' => $referencia,
            'usuario_id' => auth()->id(),
            'nota' => $nota,
            'created_at' => now(),
        ]);
    }
}
