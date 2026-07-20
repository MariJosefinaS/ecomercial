<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    protected $fillable = ['tipo', 'titulo', 'detalle', 'usuario_id', 'local_id'];

    public function usuario(): BelongsTo { return $this->belongsTo(User::class, 'usuario_id'); }
    public function local(): BelongsTo { return $this->belongsTo(Local::class); }
}
