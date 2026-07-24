<?php

namespace App\Support;

use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\Comprobante;
use App\Models\Devolucion;
use App\Models\MovimientoCliente;
use App\Models\PedidoPago;
use App\Models\Parametro;
use App\Models\Venta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Motor fiscal: emite comprobantes con numeración correlativa y resuelve la letra
 * según la condición de IVA del cliente (empresa Responsable Inscripto por defecto).
 *
 *   Cliente Responsable Inscripto  → Factura A (IVA discriminado)
 *   Monotributo / CF / Exento      → Factura B (IVA incluido, no se discrimina)
 *   Si la empresa fuera Monotributo → siempre C
 *
 * Los precios de venta del sistema son FINALES (IVA incluido): el neto se calcula
 * hacia atrás (total / (1 + iva%)), que es como corresponde para no alterar el precio.
 */
class Comprobantes
{
    public const CONDICIONES = [
        'responsable_inscripto' => 'Responsable Inscripto',
        'monotributo' => 'Monotributo',
        'consumidor_final' => 'Consumidor Final',
        'exento' => 'Exento',
    ];

    /** Condición de IVA de la EMPRESA (parametrizable). */
    public static function condicionEmpresa(): string
    {
        return (string) Parametro::get('condicion_iva_empresa', 'responsable_inscripto');
    }

    /** Punto de venta que se usa al emitir. */
    public static function puntoVenta(): int
    {
        return (int) Parametro::num('punto_venta', 1);
    }

    /** Alícuota de IVA de las ventas (%). */
    public static function ivaPct(): float
    {
        return (float) Parametro::num('iva_pct_venta', 21);
    }

    /** Días de plazo por defecto de una factura en cuenta corriente (no crédito). */
    public static function plazoCtaCte(): int
    {
        return (int) Parametro::num('plazo_cta_cte_dias', 30);
    }

    /** Letra que corresponde a una factura para este cliente. */
    public static function letraPara(?Cliente $cliente): string
    {
        if (self::condicionEmpresa() !== 'responsable_inscripto') {
            return 'C';   // Monotributista emite siempre C
        }

        return ($cliente?->tipo_iva === 'responsable_inscripto') ? 'A' : 'B';
    }

    /**
     * Desagrega un total FINAL en neto + IVA.
     * En A el IVA se discrimina; en B/C queda incluido (se informa igual para la contabilidad).
     * @return array{neto:float,iva_pct:float,iva:float,total:float}
     */
    public static function desagregar(float $total, ?float $ivaPct = null): array
    {
        $pct = $ivaPct ?? self::ivaPct();
        $neto = $pct > 0 ? round($total / (1 + $pct / 100), 2) : round($total, 2);

        return [
            'neto' => $neto,
            'iva_pct' => $pct,
            'iva' => round($total - $neto, 2),
            'total' => round($total, 2),
        ];
    }

    /**
     * Emite un comprobante con el siguiente número correlativo (tipo+letra+punto de venta).
     * Usa lockForUpdate para que dos emisiones simultáneas no repitan número.
     */
    public static function emitir(string $tipo, array $datos): Comprobante
    {
        $letra = $datos['letra'] ?? null;
        $pv = $datos['punto_venta'] ?? self::puntoVenta();
        $total = round((float) ($datos['total'] ?? 0), 2);

        $iva = array_key_exists('neto', $datos)
            ? ['neto' => (float) $datos['neto'], 'iva_pct' => (float) ($datos['iva_pct'] ?? 0), 'iva' => (float) ($datos['iva'] ?? 0), 'total' => $total]
            : self::desagregar($total, $datos['iva_pct'] ?? null);

        return DB::transaction(function () use ($tipo, $letra, $pv, $datos, $iva, $total) {
            $ultimo = (int) Comprobante::where('tipo', $tipo)->where('letra', $letra)->where('punto_venta', $pv)
                ->lockForUpdate()->max('numero');
            $numero = $ultimo + 1;

            return Comprobante::create([
                'tipo' => $tipo,
                'letra' => $letra,
                'punto_venta' => $pv,
                'numero' => $numero,
                'numero_completo' => self::formatoNumero($pv, $numero),
                'cliente_id' => $datos['cliente_id'] ?? null,
                'proveedor_id' => $datos['proveedor_id'] ?? null,
                'venta_id' => $datos['venta_id'] ?? null,
                'cobro_id' => $datos['cobro_id'] ?? null,
                'devolucion_id' => $datos['devolucion_id'] ?? null,
                'pedido_pago_id' => $datos['pedido_pago_id'] ?? null,
                'fecha' => $datos['fecha'] ?? now(),
                'fecha_vencimiento' => $datos['fecha_vencimiento'] ?? null,
                'concepto' => $datos['concepto'] ?? '',
                'neto' => $iva['neto'],
                'iva_pct' => $iva['iva_pct'],
                'iva' => $iva['iva'],
                'total' => $total,
                'estado' => 'emitido',
                'emitido_por' => $datos['emitido_por'] ?? auth()->id(),
            ]);
        });
    }

    public static function formatoNumero(int $pv, int $numero): string
    {
        return str_pad((string) $pv, 4, '0', STR_PAD_LEFT) . '-' . str_pad((string) $numero, 8, '0', STR_PAD_LEFT);
    }

    // ===================================================================
    //  Emisiones concretas del circuito
    // ===================================================================

    /** FACTURA por una venta aprobada. Devuelve null si la venta no tiene cliente. */
    public static function facturaDeVenta(Venta $venta, ?Carbon $vencimiento = null): ?Comprobante
    {
        if (! $venta->cliente_id) {
            return null;
        }
        if ($ya = Comprobante::where('venta_id', $venta->id)->where('tipo', 'factura')->vigentes()->first()) {
            return $ya;   // idempotente: no se factura dos veces la misma venta
        }

        $cliente = $venta->cliente ?: Cliente::find($venta->cliente_id);

        // Vencimiento: crédito → la 1ª cuota; contado → el día; cta cte → plazo parametrizado.
        $venc = $vencimiento
            ?? ($venta->credito
                ? ($venta->fecha_primera_cuota?->copy() ?? Carbon::today()->addDays(self::plazoCtaCte()))
                : Carbon::today()->addDays(self::plazoCtaCte()));

        return self::emitir('factura', [
            'letra' => self::letraPara($cliente),
            'cliente_id' => $venta->cliente_id,
            'venta_id' => $venta->id,
            'fecha' => now(),
            'fecha_vencimiento' => $venc,
            'concepto' => 'Venta ' . $venta->numero . ($venta->credito ? ' — crédito ' . $venta->etiquetaCredito() : ''),
            'total' => (float) $venta->total,
        ]);
    }

    /** RECIBO por un cobro (el respaldo del cliente por lo que pagó). */
    public static function reciboDeCobro(Cobro $cobro): ?Comprobante
    {
        if ($ya = Comprobante::where('cobro_id', $cobro->id)->where('tipo', 'recibo')->vigentes()->first()) {
            return $ya;
        }

        $cliente = $cobro->cliente ?: Cliente::find($cobro->cliente_id);

        return self::emitir('recibo', [
            'letra' => self::letraPara($cliente),
            'cliente_id' => $cobro->cliente_id,
            'cobro_id' => $cobro->id,
            'fecha' => $cobro->created_at ?? now(),
            'concepto' => 'Cobro de cuota' . ($cobro->venta_id ? ' — venta #' . $cobro->venta_id : ''),
            'total' => (float) $cobro->monto,
        ]);
    }

    /** NOTA DE CRÉDITO por una devolución aprobada. */
    public static function notaCreditoDeDevolucion(Devolucion $dev): ?Comprobante
    {
        if (! $dev->cliente_id) {
            return null;
        }
        if ($ya = Comprobante::where('devolucion_id', $dev->id)->where('tipo', 'nota_credito')->vigentes()->first()) {
            return $ya;
        }

        $cliente = $dev->cliente ?: Cliente::find($dev->cliente_id);

        return self::emitir('nota_credito', [
            'letra' => self::letraPara($cliente),
            'cliente_id' => $dev->cliente_id,
            'devolucion_id' => $dev->id,
            'fecha' => now(),
            'concepto' => 'Devolución de ' . ($dev->producto ?: 'mercadería') . ($dev->motivo ? ' — ' . $dev->motivo : ''),
            'total' => (float) $dev->monto,
        ]);
    }

    /** ORDEN DE PAGO por un pedido de pago procesado (proveedor o empleado). */
    public static function ordenDePago(PedidoPago $p): ?Comprobante
    {
        if ($ya = Comprobante::where('pedido_pago_id', $p->id)->where('tipo', 'orden_pago')->vigentes()->first()) {
            return $ya;
        }

        return self::emitir('orden_pago', [
            'letra' => 'X',   // documento interno, sin valor fiscal
            'proveedor_id' => $p->proveedor_id,
            'pedido_pago_id' => $p->id,
            'fecha' => now(),
            'concepto' => $p->concepto . ' — ' . $p->beneficiario,
            'total' => (float) $p->importe,
            'iva_pct' => 0,   // la OP no genera IVA: es la cancelación de una obligación
            'neto' => (float) $p->importe,
            'iva' => 0,
        ]);
    }

    /**
     * Datos que necesita la vista PDF del comprobante (destinatario, domicilio y detalle).
     * @return array<string,mixed>
     */
    public static function datosPdf(Comprobante $c): array
    {
        // Detalle: los ítems de la venta si es una factura; si no, líneas informativas.
        $detalle = [];
        if ($c->tipo === 'factura' && $c->venta) {
            foreach ($c->venta->items as $it) {
                $detalle[] = [
                    'descripcion' => '   · ' . $it->cantidad . '× ' . ($it->producto?->nombre ?? 'Artículo'),
                    'importe' => round((float) $it->cantidad * (float) $it->precio_unitario, 2),
                ];
            }
            if ($c->venta->credito) {
                $detalle[] = [
                    'descripcion' => '   Plan: ' . ($c->venta->plan_nombre ?: '—') . ' · ' . $c->venta->plazo . ' cuotas de $'
                        . number_format((float) $c->venta->cuota, 2, ',', '.'),
                    'importe' => null,
                ];
            }
        }

        // Domicilio del cliente: el principal de su ficha (si cargó varios).
        $domicilio = null;
        if ($c->cliente) {
            $dom = $c->cliente->domicilioPrincipal();
            $domicilio = $dom?->completa() ?: $c->cliente->direccion;
        }

        return [
            'c' => $c,
            'detalle' => $detalle,
            'domicilio' => $domicilio,
            'condiciones' => self::CONDICIONES,
            'empresa' => ['condicion' => self::CONDICIONES[self::condicionEmpresa()] ?? ''],
        ];
    }

    /** Anula un comprobante (deja el número usado, como corresponde). */
    public static function anular(Comprobante $c, ?string $motivo, ?int $userId = null): bool
    {
        if ($c->estaAnulado()) {
            return false;
        }
        $c->update(['estado' => 'anulado', 'motivo_anulacion' => $motivo ?: 'Sin motivo']);
        MovimientoCliente::where('comprobante_id', $c->id)->update(['comprobante_id' => null]);

        return true;
    }
}
