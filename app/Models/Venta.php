<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venta extends Model
{
    protected $table = 'ventas';

    protected $fillable = ['numero', 'credito_barra', 'garante_nombre', 'garante_documento', 'garante_telefono', 'local_id', 'vendedor_id', 'cliente_id', 'aprobada_por', 'cliente_nombre', 'medio_pago', 'credito', 'fecha', 'total', 'estado', 'motivo_rechazo', 'plan_codigo', 'plan_nombre', 'modalidad', 'anticipo', 'saldo_financiado', 'plazo', 'cuota', 'fecha_primera_cuota', 'zona_cobranza', 'zona_id', 'cobrador', 'entregado_at'];

    protected $casts = ['fecha' => 'date', 'total' => 'decimal:2', 'credito' => 'boolean', 'credito_barra' => 'integer', 'anticipo' => 'decimal:2', 'saldo_financiado' => 'decimal:2', 'cuota' => 'decimal:2', 'fecha_primera_cuota' => 'date', 'entregado_at' => 'datetime'];

    /** Al crear una venta a CRÉDITO, asigna el correlativo (barra) dentro de la cuenta del cliente. */
    protected static function booted(): void
    {
        static::creating(function (Venta $v) {
            if ($v->credito && $v->cliente_id && $v->credito_barra === null) {
                $v->credito_barra = (int) (static::where('cliente_id', $v->cliente_id)->where('credito', true)->max('credito_barra') ?? 0) + 1;
            }
        });
    }

    /** Etiqueta del crédito estilo cuenta/barra: "38301/15". Cae al número de venta si no es crédito. */
    public function etiquetaCredito(): string
    {
        if ($this->credito && $this->credito_barra && $this->cliente) {
            return ($this->cliente->numero_cuenta ?? '—') . '/' . $this->credito_barra;
        }

        return $this->numero ?? '—';
    }

    public function local(): BelongsTo { return $this->belongsTo(Local::class); }
    public function zona(): BelongsTo { return $this->belongsTo(Zona::class); }
    public function vendedor(): BelongsTo { return $this->belongsTo(User::class, 'vendedor_id'); }
    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
    public function aprobador(): BelongsTo { return $this->belongsTo(User::class, 'aprobada_por'); }
    public function items(): HasMany { return $this->hasMany(VentaItem::class); }
    public function cuotas(): HasMany { return $this->hasMany(Cuota::class); }
    public function unidades(): HasMany { return $this->hasMany(UnidadTrazable::class, 'venta_id'); }
}
