<?php

namespace App\Support;

use App\Models\Compra;
use App\Models\CompraItem;
use App\Models\Producto;
use App\Models\SolicitudCompra;
use Illuminate\Support\Facades\DB;

/**
 * Circuito de reposición, de punta a punta:
 *
 *   SOLICITUD (vendedor pide / EOQ sugiere)
 *      → APROBAR / RECHAZAR (quien compra)
 *      → CONVERTIR EN ORDEN DE COMPRA (agrupa por proveedor + sucursal)
 *      → [flujo ya existente] aprobar compra → recibir → Recepción → stock
 *
 * Antes la solicitud nacía y quedaba colgada en 'pendiente': nadie la resolvía
 * y no había puente hacia la compra. Esta clase es ese puente.
 */
class Reposicion
{
    /** Aprueba una solicitud pendiente (queda lista para convertirse en orden de compra). */
    public static function aprobar(SolicitudCompra $s, int $userId): bool
    {
        if ($s->estado !== 'pendiente') {
            return false;
        }
        $s->update(['estado' => 'aprobada', 'resuelta_por' => $userId, 'resuelta_at' => now()]);

        return true;
    }

    /** Rechaza una solicitud pendiente, con motivo. */
    public static function rechazar(SolicitudCompra $s, int $userId, ?string $motivo): bool
    {
        if ($s->estado !== 'pendiente') {
            return false;
        }
        $s->update([
            'estado' => 'rechazada', 'resuelta_por' => $userId, 'resuelta_at' => now(),
            'motivo_rechazo' => $motivo ?: 'Sin motivo',
        ]);

        return true;
    }

    /** Vuelve una solicitud resuelta al estado pendiente (deshacer). No aplica si ya se convirtió. */
    public static function reabrir(SolicitudCompra $s): bool
    {
        if (! in_array($s->estado, ['aprobada', 'rechazada'], true) || $s->compra_id) {
            return false;
        }
        $s->update(['estado' => 'pendiente', 'resuelta_por' => null, 'resuelta_at' => null, 'motivo_rechazo' => null]);

        return true;
    }

    /**
     * Convierte solicitudes APROBADAS en órdenes de compra.
     * Agrupa por proveedor + sucursal: varias solicitudes del mismo proveedor entran
     * en UNA sola orden, y si el mismo producto se pidió dos veces se suman las cantidades.
     *
     * @param  array<int>  $ids
     * @return array{compras:array<int,string>,convertidas:int,sin_proveedor:int,mensaje:string}
     */
    public static function convertirEnCompras(array $ids, int $userId): array
    {
        $solicitudes = SolicitudCompra::with('producto:id,nombre,proveedor_id,precio_compra')
            ->whereIn('id', $ids)->where('estado', 'aprobada')->whereNull('compra_id')->get();

        if ($solicitudes->isEmpty()) {
            return ['compras' => [], 'convertidas' => 0, 'sin_proveedor' => 0,
                'mensaje' => 'No hay solicitudes aprobadas para convertir (¿ya se convirtieron?).'];
        }

        // Sin proveedor no se puede armar la orden: se avisa en vez de inventar uno.
        $sinProveedor = $solicitudes->filter(fn (SolicitudCompra $s) => ! ($s->proveedor_id ?: $s->producto?->proveedor_id));
        $convertibles = $solicitudes->reject(fn (SolicitudCompra $s) => ! ($s->proveedor_id ?: $s->producto?->proveedor_id));

        if ($convertibles->isEmpty()) {
            return ['compras' => [], 'convertidas' => 0, 'sin_proveedor' => $sinProveedor->count(),
                'mensaje' => 'Ninguna se pudo convertir: los productos no tienen proveedor asignado.'];
        }

        $creadas = [];
        DB::transaction(function () use ($convertibles, $userId, &$creadas) {
            $grupos = $convertibles->groupBy(fn (SolicitudCompra $s) => ($s->proveedor_id ?: $s->producto->proveedor_id) . '-' . $s->local_id);

            foreach ($grupos as $grupo) {
                /** @var SolicitudCompra $primera */
                $primera = $grupo->first();
                $proveedorId = $primera->proveedor_id ?: $primera->producto->proveedor_id;

                $compra = Compra::create([
                    'numero' => self::proximoNumeroCompra(),
                    'proveedor_id' => $proveedorId,
                    'local_id' => $primera->local_id,
                    'usuario_id' => $userId,
                    'fecha' => now(),
                    'total' => 0,
                    'estado' => 'pendiente',   // sigue el flujo normal: la aprueba quien corresponde
                ]);

                // Mismo producto pedido varias veces → una sola línea con la suma.
                $total = 0.0;
                foreach ($grupo->groupBy('producto_id') as $productoId => $delProducto) {
                    $cantidad = (int) $delProducto->sum('cantidad');
                    $costo = (float) ($delProducto->first()->producto?->precio_compra ?? 0);
                    CompraItem::create([
                        'compra_id' => $compra->id,
                        'producto_id' => $productoId,
                        'cantidad' => $cantidad,
                        'costo_unitario' => $costo,
                    ]);
                    $total += $cantidad * $costo;
                }
                $compra->update(['total' => round($total, 2)]);

                SolicitudCompra::whereIn('id', $grupo->pluck('id'))->update([
                    'estado' => 'convertida', 'compra_id' => $compra->id,
                    'resuelta_por' => $userId, 'resuelta_at' => now(),
                ]);

                $creadas[$compra->id] = $compra->numero;
            }
        });

        $n = $convertibles->count();
        $msg = count($creadas) === 1
            ? "Se generó la orden de compra {$creadas[array_key_first($creadas)]} con {$n} solicitud(es)."
            : 'Se generaron ' . count($creadas) . " órdenes de compra (una por proveedor) con {$n} solicitud(es).";
        if ($sinProveedor->count() > 0) {
            $msg .= ' ⚠ ' . $sinProveedor->count() . ' quedaron sin convertir: su producto no tiene proveedor asignado.';
        }

        return ['compras' => $creadas, 'convertidas' => $n, 'sin_proveedor' => $sinProveedor->count(), 'mensaje' => $msg];
    }

    /** Siguiente número de orden de compra (misma serie que el alta manual). */
    public static function proximoNumeroCompra(): string
    {
        $max = (int) (Compra::selectRaw("MAX(CAST(REGEXP_REPLACE(numero, '[^0-9]', '') AS UNSIGNED)) as n")->value('n') ?? 500);

        return 'OC-' . ($max + 1);
    }

    /** Siguiente número de solicitud (compartido por Stock y por el EOQ). */
    public static function proximoNumeroSolicitud(): string
    {
        $max = (int) (SolicitudCompra::selectRaw("MAX(CAST(REGEXP_REPLACE(numero, '[^0-9]', '') AS UNSIGNED)) as n")->value('n') ?? 76);

        return 'SOL-' . ($max + 1);
    }

    /** Crea una solicitud de reposición (fuente única para Stock y para el EOQ). */
    public static function solicitar(Producto $producto, int $localId, int $cantidad, int $userId, ?string $nota = null): SolicitudCompra
    {
        return SolicitudCompra::create([
            'numero' => self::proximoNumeroSolicitud(),
            'producto_id' => $producto->id,
            'proveedor_id' => $producto->proveedor_id,
            'local_id' => $localId,
            'solicitante_id' => $userId,
            'cantidad' => max(1, $cantidad),
            'nota' => $nota,
            'estado' => 'pendiente',
        ]);
    }
}
