<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Cobro operativo (Bloque 3): un pago recibido por el cobrador. Ver App\Support\Cobranza.
 */
class Cobro extends Model
{
    protected $table = 'cobros';

    protected $fillable = [
        'uuid', 'cuota_id', 'venta_id', 'cliente_id', 'cobrador_id', 'zona_id',
        'monto', 'medio', 'comprobante', 'banco', 'cheque_numero', 'excedente',
        'estado_conciliacion', 'conciliado_por', 'conciliado_at', 'registrado_por', 'fecha',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'excedente' => 'decimal:2',
        'fecha' => 'datetime',
        'conciliado_at' => 'datetime',
    ];

    public const MEDIOS = ['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'cheque' => 'Cheque'];

    protected static function booted(): void
    {
        static::creating(function (Cobro $c) {
            $c->uuid ??= (string) Str::uuid();
            $c->fecha ??= now();
        });
    }

    public function medios(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(CobroMedio::class); }
    public function esMixto(): bool { return $this->medio === 'mixto'; }

    public function cuota(): BelongsTo { return $this->belongsTo(Cuota::class); }
    public function venta(): BelongsTo { return $this->belongsTo(Venta::class); }
    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
    public function cobrador(): BelongsTo { return $this->belongsTo(User::class, 'cobrador_id'); }
    public function zona(): BelongsTo { return $this->belongsTo(Zona::class); }

    public function medioLabel(): string { return self::MEDIOS[$this->medio] ?? ucfirst($this->medio); }
    public function comprobanteUrl(): ?string { return $this->comprobante ? asset('storage/' . $this->comprobante) : null; }
}
