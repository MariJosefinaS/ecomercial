<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudCompra extends Model
{
    protected $table = 'solicitudes_compra';

    protected $fillable = ['numero', 'producto_id', 'local_id', 'solicitante_id', 'cantidad', 'nota', 'estado'];

    protected $casts = ['cantidad' => 'integer'];

    public function producto(): BelongsTo { return $this->belongsTo(Producto::class); }
    public function local(): BelongsTo { return $this->belongsTo(Local::class); }
    public function solicitante(): BelongsTo { return $this->belongsTo(User::class, 'solicitante_id'); }
}
