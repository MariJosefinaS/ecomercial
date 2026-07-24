<?php

namespace App\Support;

use App\Models\Local;
use App\Models\Producto;
use Illuminate\Support\Collection;

/**
 * Valorización del stock: cuánta plata hay inmovilizada, a COSTO y a VENTA,
 * con el margen potencial. Se puede ver por sucursal, por proveedor y por categoría.
 *
 *   Valor a costo  = cantidad × precio_compra del producto
 *   Valor a venta  = cantidad × precio_venta DEL LOCAL (el precio vive en stock_locales)
 *   Margen         = venta − costo   ·   Margen % = margen / venta (markup sobre el precio)
 *
 * Los productos sin costo cargado se cuentan aparte (avisan que falta el dato) para no
 * inflar el margen con costo 0.
 */
class StockValorizado
{
    /**
     * Renglones producto×local con su valorización.
     *
     * @param  int|null  $localId      null = todas las sucursales
     * @param  int|null  $proveedorId  null = todos
     * @param  int|null  $categoriaId  null = todas
     * @return Collection<int,array<string,mixed>>
     */
    public static function filas(?int $localId = null, ?int $proveedorId = null, ?int $categoriaId = null, string $buscar = '', bool $soloConStock = true): Collection
    {
        $productos = Producto::with(['proveedor:id,nombre', 'categoria:id,nombre', 'stock.local:id,nombre'])
            ->where('activo', true)
            ->when($proveedorId, fn ($q) => $q->where('proveedor_id', $proveedorId))
            ->when($categoriaId, fn ($q) => $q->where('categoria_id', $categoriaId))
            ->when(trim($buscar) !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('nombre', 'like', "%{$buscar}%")
                ->orWhere('codigo', 'like', "%{$buscar}%")
                ->orWhere('sku', 'like', "%{$buscar}%")
                ->orWhere('marca', 'like', "%{$buscar}%")))
            ->orderBy('nombre')
            ->get();

        $filas = collect();
        foreach ($productos as $p) {
            $costoUnit = (float) $p->precio_compra;
            foreach ($p->stock as $sl) {
                if ($localId && $sl->local_id !== $localId) {
                    continue;
                }
                $cant = (int) $sl->cantidad;
                if ($soloConStock && $cant <= 0) {
                    continue;
                }
                $ventaUnit = (float) $sl->precio_venta;
                $vCosto = round($cant * $costoUnit, 2);
                $vVenta = round($cant * $ventaUnit, 2);

                $filas->push([
                    'producto_id' => $p->id,
                    'codigo' => $p->codigo,
                    'nombre' => $p->nombre,
                    'marca' => $p->marca,
                    'categoria' => $p->categoria?->nombre ?? '—',
                    'categoria_id' => $p->categoria_id,
                    'proveedor' => $p->proveedor?->nombre ?? '—',
                    'proveedor_id' => $p->proveedor_id,
                    'local' => $sl->local?->nombre ?? '—',
                    'local_id' => $sl->local_id,
                    'cantidad' => $cant,
                    'costo_unit' => $costoUnit,
                    'venta_unit' => $ventaUnit,
                    'valor_costo' => $vCosto,
                    'valor_venta' => $vVenta,
                    'margen' => round($vVenta - $vCosto, 2),
                    'margen_pct' => $vVenta > 0 ? round(($vVenta - $vCosto) / $vVenta * 100, 1) : 0.0,
                    'sin_costo' => $costoUnit <= 0,
                ]);
            }
        }

        return $filas;
    }

    /** Totales de un conjunto de renglones. */
    public static function totales(Collection $filas): array
    {
        $costo = round((float) $filas->sum('valor_costo'), 2);
        $venta = round((float) $filas->sum('valor_venta'), 2);
        $sinCosto = $filas->where('sin_costo', true);

        return [
            'unidades' => (int) $filas->sum('cantidad'),
            'articulos' => $filas->pluck('producto_id')->unique()->count(),
            'valor_costo' => $costo,
            'valor_venta' => $venta,
            'margen' => round($venta - $costo, 2),
            'margen_pct' => $venta > 0 ? round(($venta - $costo) / $venta * 100, 1) : 0.0,
            'sin_costo_articulos' => $sinCosto->pluck('producto_id')->unique()->count(),
            'sin_costo_valor_venta' => round((float) $sinCosto->sum('valor_venta'), 2),
        ];
    }

    /**
     * Agrupa y totaliza por una clave ('proveedor', 'local' o 'categoria').
     * @return array<int,array<string,mixed>>
     */
    public static function agrupado(Collection $filas, string $por): array
    {
        return $filas->groupBy($por)->map(function (Collection $g, $nombre) {
            $t = self::totales($g);

            return $t + ['nombre' => (string) $nombre];
        })->sortByDesc('valor_costo')->values()->all();
    }

    /** Sucursales activas (para el filtro). */
    public static function locales(): Collection
    {
        return Local::where('activo', true)->orderBy('id')->get(['id', 'nombre']);
    }
}
