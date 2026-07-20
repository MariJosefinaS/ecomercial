<?php

namespace App\Support;

use App\Models\Compra;
use App\Models\Producto;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Búsqueda interna que decide, para cada línea de una factura escaneada,
 * a QUÉ producto del sistema corresponde — para luego sumar al stock.
 *
 * Estrategia (pedido del usuario: "entender el producto, buscar internamente
 * y ante la duda consultar al encargado"):
 *   1. Anillo 1 = ítems de la compra abierta (lo esperado).
 *   2. Anillo 2 = catálogo de productos (priorizando el mismo proveedor).
 * Match por código/SKU exacto y, si no, por similitud de descripción.
 *
 * Confianza resultante:
 *   - ALTA      → código/SKU coincide o nombre casi idéntico → se vincula solo.
 *   - DUDOSA    → hay un candidato razonable → se propone, pero el encargado confirma.
 *   - SIN_MATCH → no se reconoce → el encargado elige el producto (o lo marca nuevo).
 */
class MatcheoFactura
{
    public const ALTA = 'alta';
    public const DUDOSA = 'dudosa';
    public const SIN_MATCH = 'sin_match';

    /** Umbrales de similitud de descripción (0..1). */
    private const UMBRAL_ALTA = 0.86;
    private const UMBRAL_DUDA = 0.50;

    /**
     * Enriquece cada línea con su match. Las líneas tipo "gasto" no se matchean.
     *
     * @param  array<int,array<string,mixed>>  $lineas  [{tipo,codigo,descripcion,cantidad,p_unit,total}]
     * @return array<int,array<string,mixed>>
     */
    public static function resolver(Compra $compra, array $lineas): array
    {
        $compra->loadMissing('items.producto');
        $itemsCompra = $compra->items;

        // Catálogo del anillo 2: mismo proveedor primero, luego el resto.
        $catalogo = Producto::query()
            ->where('activo', true)
            ->orderByRaw('proveedor_id = ? desc', [$compra->proveedor_id])
            ->get(['id', 'codigo', 'sku', 'nombre', 'proveedor_id']);

        return array_map(function (array $l) use ($itemsCompra, $catalogo) {
            if (($l['tipo'] ?? 'producto') === 'gasto') {
                $l['match'] = null;

                return $l;
            }
            $l['match'] = self::matchearLinea($l, $itemsCompra, $catalogo);

            return $l;
        }, $lineas);
    }

    /**
     * @param  Collection<int,\App\Models\CompraItem>  $itemsCompra
     * @param  Collection<int,Producto>  $catalogo
     * @return array<string,mixed>
     */
    private static function matchearLinea(array $linea, Collection $itemsCompra, Collection $catalogo): array
    {
        $codigo = self::norm($linea['codigo'] ?? '');
        $desc = self::norm($linea['descripcion'] ?? '');

        // ---- 1) Código/SKU exacto en los ítems de la compra (lo más confiable) ----
        foreach ($itemsCompra as $it) {
            if ($it->producto && self::codigoCoincide($it->producto, $codigo, $desc)) {
                return self::resultado(self::ALTA, $it->producto, $it->id, 'codigo', [
                    self::candidato($it->producto, 1.0, $it->id),
                ]);
            }
        }

        // ---- 2) Código/SKU exacto en el catálogo ----
        foreach ($catalogo as $p) {
            if (self::codigoCoincide($p, $codigo, $desc)) {
                $itemId = optional($itemsCompra->firstWhere('producto_id', $p->id))->id;

                return self::resultado(self::ALTA, $p, $itemId, 'codigo', [
                    self::candidato($p, 1.0, $itemId),
                ]);
            }
        }

        // ---- 3) Similitud de descripción (anillo 1 + anillo 2) ----
        $candidatos = collect();

        foreach ($itemsCompra as $it) {
            if (! $it->producto) {
                continue;
            }
            $candidatos->push(self::candidato(
                $it->producto,
                self::similitud($desc, self::norm($it->producto->nombre)),
                $it->id,
            ));
        }
        foreach ($catalogo as $p) {
            // evitar duplicar productos que ya están en la compra
            if ($candidatos->firstWhere('producto_id', $p->id)) {
                continue;
            }
            $candidatos->push(self::candidato($p, self::similitud($desc, self::norm($p->nombre))));
        }

        $top = $candidatos->sortByDesc('score')->take(3)->values()->all();
        $mejor = $top[0] ?? null;
        $score = $mejor['score'] ?? 0.0;

        if ($mejor && $score >= self::UMBRAL_ALTA) {
            return self::resultado(self::ALTA, null, $mejor['compra_item_id'], 'nombre', $top, $mejor['producto_id']);
        }
        if ($mejor && $score >= self::UMBRAL_DUDA) {
            return self::resultado(self::DUDOSA, null, $mejor['compra_item_id'], 'nombre', $top, $mejor['producto_id']);
        }

        return self::resultado(self::SIN_MATCH, null, null, null, $top, null);
    }

    private static function codigoCoincide(Producto $p, string $codigo, string $desc): bool
    {
        $cod = self::norm($p->codigo ?? '');
        $sku = self::norm($p->sku ?? '');

        if ($codigo !== '' && ($codigo === $cod || $codigo === $sku)) {
            return true;
        }
        // El código del sistema aparece embebido en la descripción de la factura
        // (caso real: "EVC330 EXHIBIDORA NEBA 330 LTS").
        if ($cod !== '' && strlen($cod) >= 3 && self::contieneToken($desc, $cod)) {
            return true;
        }
        if ($sku !== '' && strlen($sku) >= 3 && self::contieneToken($desc, $sku)) {
            return true;
        }

        return false;
    }

    /** @return array<string,mixed> */
    private static function resultado(string $confianza, ?Producto $prod, ?int $compraItemId, ?string $motivo, array $candidatos, ?int $productoIdSugerido = null): array
    {
        return [
            'confianza' => $confianza,
            'producto_id' => $prod?->id ?? $productoIdSugerido,
            'compra_item_id' => $compraItemId,
            'motivo' => $motivo,
            'candidatos' => $candidatos,
        ];
    }

    /** @return array<string,mixed> */
    private static function candidato(Producto $p, float $score, ?int $compraItemId = null): array
    {
        return [
            'producto_id' => $p->id,
            'codigo' => $p->codigo,
            'sku' => $p->sku,
            'nombre' => $p->nombre,
            'compra_item_id' => $compraItemId,
            'score' => round($score, 3),
        ];
    }

    /** Similitud 0..1 combinando solapamiento de tokens (Jaccard) y similar_text. */
    private static function similitud(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b) {
            return 1.0;
        }

        $ta = array_filter(explode(' ', $a), fn ($t) => strlen($t) >= 2);
        $tb = array_filter(explode(' ', $b), fn ($t) => strlen($t) >= 2);
        $inter = array_intersect($ta, $tb);
        $union = array_unique(array_merge($ta, $tb));
        $jaccard = $union ? count($inter) / count($union) : 0.0;

        similar_text($a, $b, $pct);

        return max($jaccard, $pct / 100);
    }

    private static function contieneToken(string $texto, string $token): bool
    {
        return in_array($token, explode(' ', $texto), true) || str_contains($texto, $token);
    }

    /** Normaliza: sin acentos, mayúsculas colapsadas, espacios simples. */
    private static function norm(?string $s): string
    {
        $s = Str::ascii((string) $s);
        $s = strtoupper(trim($s));
        $s = preg_replace('/[^A-Z0-9 ]+/', ' ', $s);

        return preg_replace('/\s+/', ' ', $s);
    }
}
