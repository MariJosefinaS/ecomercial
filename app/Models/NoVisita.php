<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Novedad "el cobrador no pasó" por una zona en una fecha (enfermedad/robo/ausencia).
 * Los días marcados NO devengan mora para las cuotas de esa zona (ver App\Models\Cuota::mora()).
 */
class NoVisita extends Model
{
    protected $table = 'no_visitas';

    protected $fillable = [
        'zona_id', 'fecha', 'motivo', 'estado', 'nota',
        'registrado_por', 'solicitado_por', 'aprobado_por', 'aprobado_at',
    ];

    protected $casts = ['fecha' => 'date', 'aprobado_at' => 'datetime'];

    public const MOTIVOS = [
        'ausente' => 'Ausencia del cobrador',
        'enfermedad' => 'Enfermedad',
        'robo' => 'Robo',
        'feriado' => 'Feriado / clima',
        'otro' => 'Otro',
    ];

    public const ESTADOS = ['pendiente' => 'Pendiente de aprobación', 'aprobada' => 'Aprobada', 'rechazada' => 'Rechazada'];

    public function zona(): BelongsTo { return $this->belongsTo(Zona::class); }
    public function registrador(): BelongsTo { return $this->belongsTo(User::class, 'registrado_por'); }
    public function solicitante(): BelongsTo { return $this->belongsTo(User::class, 'solicitado_por'); }
    public function aprobador(): BelongsTo { return $this->belongsTo(User::class, 'aprobado_por'); }

    public function motivoLabel(): string { return self::MOTIVOS[$this->motivo] ?? ucfirst($this->motivo); }
    public function estadoLabel(): string { return self::ESTADOS[$this->estado] ?? $this->estado; }

    /** Caché por request de las fechas de no-visita por zona (evita N+1 en el cálculo de mora). */
    private static array $cache = [];

    /** @return array<string> fechas 'Y-m-d' de no-visita de una zona. */
    public static function fechasDeZona(?int $zonaId): array
    {
        if (! $zonaId) {
            return [];
        }
        if (! array_key_exists($zonaId, self::$cache)) {
            // Solo las APROBADAS suspenden mora (las 'pendiente' esperan al supervisor).
            self::$cache[$zonaId] = self::where('zona_id', $zonaId)->where('estado', 'aprobada')
                ->pluck('fecha')->map(fn ($f) => $f->format('Y-m-d'))->all();
        }

        return self::$cache[$zonaId];
    }

    /** Limpia la caché (tras crear/eliminar una novedad). */
    public static function limpiarCache(): void
    {
        self::$cache = [];
    }
}
