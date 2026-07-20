<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cheque extends Model
{
    protected $table = 'cheques';

    protected $fillable = ['numero', 'banco', 'proveedor_id', 'compra_id', 'monto', 'fecha_emision', 'fecha_vencimiento', 'estado'];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
    ];

    public function proveedor(): BelongsTo { return $this->belongsTo(Proveedor::class); }
    public function compra(): BelongsTo { return $this->belongsTo(Compra::class); }
}
