<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una parte de un cobro por medio de pago (efectivo/transferencia/cheque) — ver App\Models\Cobro. */
class CobroMedio extends Model
{
    protected $table = 'cobro_medios';

    protected $fillable = [
        'cobro_id', 'medio', 'monto', 'comprobante', 'banco', 'cheque_numero',
        'estado_conciliacion', 'conciliado_por', 'conciliado_at',
    ];

    protected $casts = ['monto' => 'decimal:2', 'conciliado_at' => 'datetime'];

    public function cobro(): BelongsTo { return $this->belongsTo(Cobro::class); }

    public function medioLabel(): string { return Cobro::MEDIOS[$this->medio] ?? ucfirst($this->medio); }
    public function comprobanteUrl(): ?string { return $this->comprobante ? asset('storage/' . $this->comprobante) : null; }
}
