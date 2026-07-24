<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    protected $table = 'productos';

    protected $fillable = ['codigo', 'sku', 'codigo_externo', 'nombre', 'marca', 'imagen', 'imagenes', 'descripcion', 'detalles', 'tags', 'categoria_id', 'proveedor_id', 'unidad', 'activo', 'precio_compra', 'precio_neto', 'conceptos'];

    protected $casts = ['activo' => 'boolean', 'precio_compra' => 'decimal:2', 'precio_neto' => 'decimal:2', 'conceptos' => 'array', 'detalles' => 'array', 'imagenes' => 'array'];

    /** URL pública de la imagen de PORTADA (o null si no tiene). */
    public function imagenUrl(): ?string
    {
        return $this->imagen ? asset('storage/' . $this->imagen) : null;
    }

    /** Rutas de todas las imágenes de la galería (la portada primero). */
    public function imagenesGaleria(): array
    {
        $lista = $this->imagenes ?: ($this->imagen ? [$this->imagen] : []);

        // La portada siempre va primero, sin duplicar.
        if ($this->imagen && in_array($this->imagen, $lista, true)) {
            $lista = array_merge([$this->imagen], array_values(array_diff($lista, [$this->imagen])));
        }

        return array_values(array_unique($lista));
    }

    /** URLs públicas de la galería completa. @return array<int,string> */
    public function imagenesUrls(): array
    {
        return array_map(fn ($ruta) => asset('storage/' . $ruta), $this->imagenesGaleria());
    }

    public function categoria(): BelongsTo { return $this->belongsTo(Categoria::class); }
    public function proveedor(): BelongsTo { return $this->belongsTo(Proveedor::class); }
    public function stock(): HasMany { return $this->hasMany(StockLocal::class); }

    /** Productos sugeridos (venta cruzada) curados para este producto. */
    public function sugeridos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'producto_sugerencias', 'producto_id', 'sugerido_id')
            ->withPivot('orden')->orderBy('producto_sugerencias.orden');
    }

    /** Stock/precio de este producto en un local puntual. */
    public function stockEn(int $localId): ?StockLocal
    {
        return $this->stock->firstWhere('local_id', $localId);
    }
}
