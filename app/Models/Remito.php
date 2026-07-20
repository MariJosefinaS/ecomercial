<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Remito extends Model
{
    protected $table = 'remitos';

    protected $fillable = [
        'compra_id', 'local_id', 'numero', 'estado', 'desglose',
        'factura_escaneo_id', 'recibido_por', 'recibido_at', 'nota',
    ];

    protected $casts = ['desglose' => 'array', 'recibido_at' => 'datetime'];

    public function compra(): BelongsTo { return $this->belongsTo(Compra::class); }
    public function local(): BelongsTo { return $this->belongsTo(Local::class); }
    public function recibidoPor(): BelongsTo { return $this->belongsTo(User::class, 'recibido_por'); }
    public function items(): HasMany { return $this->hasMany(RemitoItem::class); }

    /**
     * Etiquetas imprimibles del remito: una por cada unidad recibida en buen
     * estado (para pegar en cada caja). @return array<int,array<string,mixed>>
     */
    public function etiquetas(): array
    {
        $this->loadMissing(['items.unidades.producto', 'compra.proveedor', 'local', 'recibidoPor']);

        $prov = $this->compra?->proveedor?->nombre ?? '—';
        $destino = $this->local?->nombre ?? '—';
        $recibe = 'Recibió ' . ($this->recibidoPor?->name ?? '—');
        $remNum = $this->numero ?: 'Remito #' . $this->id;
        $fecha = $this->recibido_at?->format('d/m/Y H:i') ?? '—';

        // Una etiqueta por UNIDAD trazable (cada una con su código único).
        $out = [];
        foreach ($this->items as $it) {
            foreach ($it->unidades as $unidad) {
                $out[] = [
                    'codigo' => $unidad->codigo,
                    'nombre' => $unidad->producto?->nombre ?? ($it->producto?->nombre ?? '—'),
                    'sku' => $unidad->producto?->sku ?: ($unidad->producto?->codigo ?? '—'),
                    'proveedor' => $prov,
                    'remito' => $remNum,
                    'destino' => $destino,
                    'fecha' => $fecha,
                    'recibe' => $recibe,
                ];
            }
        }

        return $out;
    }
}

