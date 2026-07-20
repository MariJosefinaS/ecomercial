<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Traspaso extends Model
{
    protected $table = 'traspasos';

    protected $fillable = [
        'numero', 'local_origen_id', 'local_destino_id', 'usuario_id',
        'aprobada_por', 'fecha', 'motivo', 'estado', 'motivo_rechazo',
    ];

    protected $casts = ['fecha' => 'date'];

    public function origen(): BelongsTo { return $this->belongsTo(Local::class, 'local_origen_id'); }
    public function destino(): BelongsTo { return $this->belongsTo(Local::class, 'local_destino_id'); }
    public function usuario(): BelongsTo { return $this->belongsTo(User::class, 'usuario_id'); }
    public function aprobador(): BelongsTo { return $this->belongsTo(User::class, 'aprobada_por'); }
    public function items(): HasMany { return $this->hasMany(TraspasoItem::class); }
}
