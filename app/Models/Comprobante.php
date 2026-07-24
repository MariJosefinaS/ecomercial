<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Comprobante formal: Factura (A/B/C), Nota de crédito/débito, Recibo y Orden de Pago.
 * La numeración la asigna `App\Support\Comprobantes` (correlativo por tipo+letra+punto de venta).
 */
class Comprobante extends Model
{
    protected $table = 'comprobantes';

    public const TIPOS = [
        'factura' => 'Factura',
        'nota_credito' => 'Nota de crédito',
        'nota_debito' => 'Nota de débito',
        'recibo' => 'Recibo',
        'orden_pago' => 'Orden de pago',
    ];

    protected $fillable = [
        'tipo', 'letra', 'punto_venta', 'numero', 'numero_completo',
        'cliente_id', 'proveedor_id', 'venta_id', 'cobro_id', 'devolucion_id', 'pedido_pago_id',
        'fecha', 'fecha_vencimiento', 'concepto', 'neto', 'iva_pct', 'iva', 'total',
        'estado', 'motivo_anulacion', 'emitido_por',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_vencimiento' => 'date',
        'neto' => 'decimal:2',
        'iva_pct' => 'decimal:2',
        'iva' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
    public function proveedor(): BelongsTo { return $this->belongsTo(Proveedor::class); }
    public function venta(): BelongsTo { return $this->belongsTo(Venta::class); }
    public function cobro(): BelongsTo { return $this->belongsTo(Cobro::class); }
    public function devolucion(): BelongsTo { return $this->belongsTo(Devolucion::class); }
    public function pedidoPago(): BelongsTo { return $this->belongsTo(PedidoPago::class); }
    public function emisor(): BelongsTo { return $this->belongsTo(User::class, 'emitido_por'); }

    public function tipoLabel(): string { return self::TIPOS[$this->tipo] ?? $this->tipo; }

    /** Nombre corto para la cuenta corriente: "Factura B 0001-00000123". */
    public function etiqueta(): string
    {
        return trim($this->tipoLabel() . ' ' . ($this->letra ?? '')) . ' ' . $this->numero_completo;
    }

    /** Nombre corto sin el número (para columnas angostas): "Factura B". */
    public function etiquetaCorta(): string
    {
        return trim($this->tipoLabel() . ' ' . ($this->letra ?? ''));
    }

    public function estaAnulado(): bool { return $this->estado === 'anulado'; }

    /** Solo la letra A discrimina el IVA en el cuerpo del comprobante. */
    public function discriminaIva(): bool { return $this->letra === 'A'; }

    public function scopeVigentes($q) { return $q->where('estado', 'emitido'); }
}
