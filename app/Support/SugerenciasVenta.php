<?php

namespace App\Support;

use App\Models\Producto;
use Illuminate\Support\Facades\DB;

/**
 * Motor de sugerencias de venta cruzada. Para los productos ya cargados en una
 * nota de pedido, propone otros combinando tres capas (en orden de prioridad):
 *   1. manual    — relaciones curadas en `producto_sugerencias`.
 *   2. juntos    — histórico "comprados juntos" (co-ocurrencia en ventas).
 *   3. categoria — respaldo por misma categoría (para productos nuevos o sin historia).
 * Sólo devuelve productos con stock disponible en el local.
 */
class SugerenciasVenta
{
    private const PRIORIDAD = ['manual' => 3, 'juntos' => 2, 'categoria' => 1];

    private const LABEL = [
        'manual' => 'Sugerido',
        'juntos' => 'Suelen llevarse juntos',
        'categoria' => 'Misma categoría',
    ];

    /**
     * @param array<int> $enCarrito ids de productos ya en la nota de pedido
     * @return array<int,array{id:int,cod:string,nom:string,prov:string,precio:float,origen:string,origen_label:string}>
     */
    public static function para(array $enCarrito, ?int $localId, int $limite = 6): array
    {
        $enCarrito = array_values(array_unique(array_filter(array_map('intval', $enCarrito))));
        if ($enCarrito === []) {
            return [];
        }

        $ranking = []; // id => ['origen' => string, 'peso' => int]

        $registrar = function (int $id, string $origen) use (&$ranking, $enCarrito) {
            if (in_array($id, $enCarrito, true)) {
                return;
            }
            $peso = self::PRIORIDAD[$origen];
            if ($peso > ($ranking[$id]['peso'] ?? 0)) {
                $ranking[$id] = ['origen' => $origen, 'peso' => $peso];
            }
        };

        // (1) Manual curado.
        foreach (DB::table('producto_sugerencias')->whereIn('producto_id', $enCarrito)->orderBy('orden')->pluck('sugerido_id') as $id) {
            $registrar((int) $id, 'manual');
        }

        // (2) Histórico: productos que aparecen en las mismas ventas (aprobadas/entregadas).
        $ventaIds = DB::table('venta_items')->whereIn('producto_id', $enCarrito)->pluck('venta_id');
        if ($ventaIds->isNotEmpty()) {
            $juntos = DB::table('venta_items as vi')
                ->join('ventas as v', 'v.id', '=', 'vi.venta_id')
                ->whereIn('vi.venta_id', $ventaIds)
                ->whereNotIn('vi.producto_id', $enCarrito)
                ->whereIn('v.estado', ['aprobada', 'entregada'])
                ->groupBy('vi.producto_id')
                ->orderByRaw('COUNT(DISTINCT vi.venta_id) DESC')
                ->limit(20)
                ->pluck('vi.producto_id');
            foreach ($juntos as $id) {
                $registrar((int) $id, 'juntos');
            }
        }

        // (3) Respaldo por misma categoría.
        $cats = Producto::whereIn('id', $enCarrito)->pluck('categoria_id')->filter()->unique();
        if ($cats->isNotEmpty()) {
            $mismaCat = Producto::whereIn('categoria_id', $cats)
                ->whereNotIn('id', $enCarrito)
                ->where('activo', true)
                ->limit(20)->pluck('id');
            foreach ($mismaCat as $id) {
                $registrar((int) $id, 'categoria');
            }
        }

        if ($ranking === []) {
            return [];
        }

        // Orden por prioridad (estable: respeta el orden de inserción dentro de cada peso).
        uasort($ranking, fn ($a, $b) => $b['peso'] <=> $a['peso']);

        $productos = Producto::with(['proveedor:id,nombre', 'stock'])
            ->whereIn('id', array_keys($ranking))->get()->keyBy('id');

        $salida = [];
        foreach ($ranking as $id => $info) {
            $p = $productos->get($id);
            if (! $p) {
                continue;
            }
            $sl = $localId ? $p->stock->firstWhere('local_id', $localId) : null;
            // Sólo sugerir lo que hay disponible en el local elegido.
            if ($localId && (! $sl || $sl->cantidad <= 0)) {
                continue;
            }
            $salida[] = [
                'id' => $p->id,
                'cod' => $p->codigo,
                'nom' => $p->nombre,
                'prov' => $p->proveedor?->nombre ?? '—',
                'precio' => (float) ($sl?->precio_venta ?? 0),
                'origen' => $info['origen'],
                'origen_label' => self::LABEL[$info['origen']] ?? 'Sugerido',
            ];
            if (count($salida) >= $limite) {
                break;
            }
        }

        return $salida;
    }
}
