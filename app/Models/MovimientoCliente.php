<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoCliente extends Model
{
    protected $table = 'movimientos_cliente';

    protected $fillable = ['cliente_id', 'tipo', 'concepto', 'monto', 'fecha', 'fecha_vencimiento', 'referencia', 'comprobante_id'];

    protected $casts = ['monto' => 'decimal:2', 'fecha' => 'date', 'fecha_vencimiento' => 'date'];

    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
    public function comprobante(): BelongsTo { return $this->belongsTo(Comprobante::class); }
}
