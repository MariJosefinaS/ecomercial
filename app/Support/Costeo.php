<?php

namespace App\Support;

use App\Models\Proveedor;

/**
 * Motor de costeo parametrizable (decisión del usuario 2026-06-19).
 *
 * Cada CONCEPTO tiene un ÁMBITO:
 *   - 'costo'  → recarga el costo puesto en depósito (flete, gestión, …).
 *   - 'venta'  → recarga el precio de venta sobre el costo (remarque/ganancia, financiación, …).
 *
 * Los conceptos del mismo ámbito se aplican EN CASCADA (compuesto), en orden de `orden`:
 *   base × (1 + c1%/100) × (1 + c2%/100) × …
 *
 * Pipeline:
 *   neto ─(conceptos costo, cascada)→ ─(× IVA si el proveedor costea con IVA)→ COSTO
 *   COSTO ─(conceptos venta, cascada)→ PRECIO DE VENTA
 *
 * La fuente de los % es, en este orden:
 *   - el snapshot por producto (productos.conceptos), si se pasa — permite agregar/quitar
 *     o ajustar conceptos por producto sin tocar el default del proveedor;
 *   - si no, los conceptos configurados del proveedor (pivot concepto_proveedor).
 */
class Costeo
{
    /**
     * Lista ordenada de % de los conceptos de un ámbito a aplicar.
     *
     * @param  array<int,array<string,mixed>>|null  $snapshot
     * @return array<int,float>
     */
    public static function porcentajes(?Proveedor $proveedor, string $ambito, ?array $snapshot = null): array
    {
        if (is_array($snapshot)) {
            return collect($snapshot)
                ->filter(fn ($c) => ! empty($c['aplica']) && ($c['ambito'] ?? 'costo') === $ambito)
                ->sortBy(fn ($c) => (int) ($c['orden'] ?? 0))
                ->map(fn ($c) => (float) ($c['porcentaje'] ?? 0))
                ->values()->all();
        }

        if (! $proveedor) {
            return [];
        }

        $proveedor->loadMissing('conceptos'); // la relación ya viene orderBy('orden')

        return $proveedor->conceptos
            ->filter(fn ($c) => ($c->ambito ?? 'costo') === $ambito)
            ->map(fn ($c) => (float) ($c->pivot->porcentaje ?? $c->porcentaje))
            ->values()->all();
    }

    /** Aplica una lista de % en cascada sobre una base. */
    public static function cascada(float $base, array $porcentajes): float
    {
        foreach ($porcentajes as $p) {
            $base *= (1 + (float) $p / 100);
        }

        return $base;
    }

    /** % de IVA a sumar al costo (0 si el proveedor no costea con IVA). */
    public static function ivaPct(?Proveedor $proveedor): float
    {
        return $proveedor && $proveedor->costea_con_iva ? (float) $proveedor->iva_pct : 0.0;
    }

    /**
     * COSTO puesto en depósito = neto, en cascada por los conceptos de costo, × (1 + IVA si aplica).
     *
     * @param  array<int,array<string,mixed>>|null  $snapshot
     */
    public static function costo(float $neto, ?Proveedor $proveedor, ?array $snapshot = null): float
    {
        $base = self::cascada($neto, self::porcentajes($proveedor, 'costo', $snapshot));
        $base *= (1 + self::ivaPct($proveedor) / 100);

        return round($base, 2);
    }

    /**
     * PRECIO DE VENTA = costo en cascada por los conceptos de venta (remarque, etc.).
     *
     * @param  array<int,array<string,mixed>>|null  $snapshot
     */
    public static function precioVenta(float $costo, ?Proveedor $proveedor, ?array $snapshot = null): float
    {
        return round(self::cascada($costo, self::porcentajes($proveedor, 'venta', $snapshot)), 2);
    }

    /**
     * Desglose completo para pantalla (neto → costo → venta), con el monto de cada concepto
     * ya calculado en cascada y un mapa id→monto para mostrarlo junto a cada fila editable.
     *
     * @param  array<int,array<string,mixed>>|null  $snapshot
     * @return array{neto:float,iva_pct:float,iva_monto:float,costo:float,precio_venta:float,montos:array<int,float>}
     */
    public static function desglose(float $neto, ?Proveedor $proveedor, ?array $snapshot = null): array
    {
        $montos = [];

        // Conceptos de COSTO (cascada sobre el neto).
        $base = $neto;
        foreach (self::conceptosOrdenados($proveedor, 'costo', $snapshot) as $c) {
            $monto = round($base * $c['porcentaje'] / 100, 2);
            $montos[$c['id']] = $monto;
            $base += $monto;
        }

        // IVA sobre el subtotal de costo.
        $ivaPct = self::ivaPct($proveedor);
        $ivaMonto = round($base * $ivaPct / 100, 2);
        $costo = round($base + $ivaMonto, 2);

        // Conceptos de VENTA (cascada sobre el costo).
        $baseV = $costo;
        foreach (self::conceptosOrdenados($proveedor, 'venta', $snapshot) as $c) {
            $monto = round($baseV * $c['porcentaje'] / 100, 2);
            $montos[$c['id']] = $monto;
            $baseV += $monto;
        }

        return [
            'neto' => round($neto, 2),
            'iva_pct' => $ivaPct,
            'iva_monto' => $ivaMonto,
            'costo' => $costo,
            'precio_venta' => round($baseV, 2),
            'montos' => $montos,
        ];
    }

    /**
     * Conceptos de un ámbito como [id, porcentaje], ya ordenados — para el desglose con montos.
     *
     * @return array<int,array{id:int,porcentaje:float}>
     */
    private static function conceptosOrdenados(?Proveedor $proveedor, string $ambito, ?array $snapshot = null): array
    {
        if (is_array($snapshot)) {
            return collect($snapshot)
                ->filter(fn ($c) => ! empty($c['aplica']) && ($c['ambito'] ?? 'costo') === $ambito)
                ->sortBy(fn ($c) => (int) ($c['orden'] ?? 0))
                ->map(fn ($c) => ['id' => (int) ($c['id'] ?? 0), 'porcentaje' => (float) ($c['porcentaje'] ?? 0)])
                ->values()->all();
        }

        if (! $proveedor) {
            return [];
        }

        $proveedor->loadMissing('conceptos');

        return $proveedor->conceptos
            ->filter(fn ($c) => ($c->ambito ?? 'costo') === $ambito)
            ->map(fn ($c) => ['id' => (int) $c->id, 'porcentaje' => (float) ($c->pivot->porcentaje ?? $c->porcentaje)])
            ->values()->all();
    }
}
