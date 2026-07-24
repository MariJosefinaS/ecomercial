<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudCompra extends Model
{
    protected $table = 'solicitudes_compra';

    protected $fillable = ['numero', 'producto_id', 'proveedor_id', 'local_id', 'solicitante_id', 'cantidad', 'nota',
        'estado', 'compra_id', 'resuelta_por', 'resuelta_at', 'motivo_rechazo'];

    protected $casts = ['cantidad' => 'integer', 'resuelta_at' => 'datetime'];

    public const ESTADOS = [
        'pendiente' => 'Pendiente',
        'aprobada' => 'Aprobada (a comprar)',
        'convertida' => 'En orden de compra',
        'rechazada' => 'Rechazada',
    ];

    public function producto(): BelongsTo { return $this->belongsTo(Producto::class); }
    public function proveedor(): BelongsTo { return $this->belongsTo(Proveedor::class); }
    public function local(): BelongsTo { return $this->belongsTo(Local::class); }
    public function solicitante(): BelongsTo { return $this->belongsTo(User::class, 'solicitante_id'); }
    public function compra(): BelongsTo { return $this->belongsTo(Compra::class); }
    public function resolutor(): BelongsTo { return $this->belongsTo(User::class, 'resuelta_por'); }

    public function estadoLabel(): string { return self::ESTADOS[$this->estado] ?? $this->estado; }

    /** El proveedor efectivo: el de la solicitud o, si no, el del producto. */
    public function proveedorEfectivo(): ?Proveedor
    {
        return $this->proveedor ?: $this->producto?->proveedor;
    }
}
