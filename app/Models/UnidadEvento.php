<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnidadEvento extends Model
{
    protected $table = 'unidad_eventos';

    public $timestamps = false;

    protected $fillable = ['unidad_id', 'tipo', 'local_id', 'referencia', 'usuario_id', 'nota', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function unidad(): BelongsTo { return $this->belongsTo(UnidadTrazable::class, 'unidad_id'); }
    public function local(): BelongsTo { return $this->belongsTo(Local::class); }
    public function usuario(): BelongsTo { return $this->belongsTo(User::class, 'usuario_id'); }
}
