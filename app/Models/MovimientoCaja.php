<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoCaja extends Model
{
    protected $table = 'movimientos_caja';

    protected $fillable = ['tipo', 'concepto', 'medio', 'monto', 'fecha', 'referencia', 'local_id'];

    protected $casts = ['monto' => 'decimal:2', 'fecha' => 'date'];

    public function local(): BelongsTo { return $this->belongsTo(Local::class); }
}
