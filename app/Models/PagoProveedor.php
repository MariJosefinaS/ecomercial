<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoProveedor extends Model
{
    protected $table = 'pagos_proveedor';

    protected $fillable = ['proveedor_id', 'compra_id', 'monto', 'monto_pagado', 'fecha_vencimiento', 'fecha_pago', 'estado'];

    protected $casts = [
        'monto' => 'decimal:2',
        'monto_pagado' => 'decimal:2',
        'fecha_vencimiento' => 'date',
        'fecha_pago' => 'date',
    ];

    public function proveedor(): BelongsTo { return $this->belongsTo(Proveedor::class); }
    public function compra(): BelongsTo { return $this->belongsTo(Compra::class); }

    public function saldo(): float { return (float) $this->monto - (float) $this->monto_pagado; }
}
