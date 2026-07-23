<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Un domicilio de un cliente (casa, negocio, casa de un familiar…).
 * El marcado como principal se refleja en `clientes.direccion` (domicilio fiscal / de respaldo).
 */
class DomicilioCliente extends Model
{
    protected $table = 'domicilios_cliente';

    public const USOS = ['ambos' => 'Entrega y cobro', 'entrega' => 'Solo entrega', 'cobro' => 'Solo cobro'];

    protected $fillable = [
        'cliente_id', 'etiqueta', 'direccion', 'localidad', 'provincia', 'referencia',
        'contacto', 'telefono', 'zona_id', 'latitud', 'longitud', 'uso', 'es_principal', 'activo',
    ];

    protected $casts = [
        'latitud' => 'decimal:7',
        'longitud' => 'decimal:7',
        'es_principal' => 'boolean',
        'activo' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Un solo principal por cliente + sincronía con clientes.direccion.
        static::saved(function (DomicilioCliente $d) {
            if (! $d->es_principal) {
                return;
            }
            DB::table('domicilios_cliente')
                ->where('cliente_id', $d->cliente_id)->where('id', '!=', $d->id)
                ->update(['es_principal' => false]);
            DB::table('clientes')->where('id', $d->cliente_id)->update(['direccion' => $d->direccion]);
        });
    }

    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }

    public function zona(): BelongsTo { return $this->belongsTo(Zona::class); }

    public function scopeActivos($q) { return $q->where('activo', true); }

    public function usoLabel(): string { return self::USOS[$this->uso] ?? $this->uso; }

    public function sirveParaEntrega(): bool { return in_array($this->uso, ['ambos', 'entrega'], true); }

    public function sirveParaCobro(): bool { return in_array($this->uso, ['ambos', 'cobro'], true); }

    /** Dirección completa en una línea (dirección · localidad · provincia). */
    public function completa(): string
    {
        return collect([$this->direccion, $this->localidad, $this->provincia])
            ->map(fn ($p) => trim((string) $p))->filter()->implode(' · ');
    }

    /** Link a Google Maps: por coordenadas si las hay, si no por texto de la dirección. */
    public function mapsUrl(): string
    {
        $q = ($this->latitud !== null && $this->longitud !== null)
            ? $this->latitud . ',' . $this->longitud
            : $this->completa();

        return 'https://www.google.com/maps/search/?api=1&query=' . urlencode($q);
    }

    public function tieneGeo(): bool
    {
        return $this->latitud !== null && $this->longitud !== null;
    }
}
