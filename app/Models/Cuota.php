<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Cuota de un plan de crédito (cronograma de cobros). Generada al aprobar la venta.
 *
 * "vencida" es DERIVADO (pendiente && vencimiento <= hoy): una cuota impaga sigue
 * `pendiente` y reaparece en la planilla de cobro cada día, sumando mora por día.
 */
class Cuota extends Model
{
    protected $table = 'cuotas';

    protected $fillable = [
        'venta_id', 'cliente_id', 'numero', 'fecha_vencimiento', 'monto',
        'capital', 'interes', 'tasa_mora', 'estado', 'pagado_monto', 'cobrador', 'zona', 'zona_id', 'cobrada_at',
    ];

    protected $casts = [
        'numero' => 'integer',
        'fecha_vencimiento' => 'date',
        'monto' => 'decimal:2',
        'capital' => 'decimal:2',
        'interes' => 'decimal:2',
        'tasa_mora' => 'decimal:4',
        'pagado_monto' => 'decimal:2',
        'cobrada_at' => 'datetime',
    ];

    public function venta(): BelongsTo { return $this->belongsTo(Venta::class); }
    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
    public function zonaRel(): BelongsTo { return $this->belongsTo(Zona::class, 'zona_id'); }

    /** Cobrador ACTUAL de la cuota = el de su zona (si tiene); si no, el string legado. */
    public function cobradorActual(): ?User
    {
        return $this->zonaRel?->cobrador;
    }

    /** Saldo impago de la cuota. */
    public function saldo(): float
    {
        return max(0.0, (float) $this->monto - (float) $this->pagado_monto);
    }

    /** Días de atraso a una fecha (0 si todavía no venció o ya está cobrada). */
    public function diasAtraso(?Carbon $al = null): int
    {
        $al = $al ?? Carbon::today();
        if ($this->estado !== 'pendiente' || $this->fecha_vencimiento->gte($al)) {
            return 0;
        }

        return $this->fecha_vencimiento->diffInDays($al);
    }

    /** ¿Está pendiente y ya venció (fecha < hoy)? */
    public function estaVencida(?Carbon $al = null): bool
    {
        return $this->diasAtraso($al) > 0;
    }

    /**
     * Días en que el cobrador NO pasó por la zona dentro del atraso (suspenden mora).
     * Regla de negocio (audios del cliente): si el cobrador no fue, el cliente no absorbe esa mora.
     */
    public function diasNoVisita(?Carbon $al = null): int
    {
        if ($this->diasAtraso($al) <= 0) {
            return 0;
        }
        $al = $al ?? Carbon::today();
        $venc = $this->fecha_vencimiento;
        $n = 0;
        foreach (\App\Models\NoVisita::fechasDeZona($this->zona_id) as $f) {
            $fc = Carbon::parse($f);
            if ($fc->gt($venc) && $fc->lte($al)) {
                $n++;
            }
        }

        return $n;
    }

    /** Días efectivos de mora = atraso − días de no-visita del cobrador (nunca negativo). */
    public function diasMora(?Carbon $al = null): int
    {
        return max(0, $this->diasAtraso($al) - $this->diasNoVisita($al));
    }

    /**
     * Mora acumulada a una fecha (lazy, sin proceso diario):
     *   mora = saldo impago × tasa_mora%/día × días EFECTIVOS de atraso
     * (se descuentan los días en que el cobrador no pasó — ver diasNoVisita()).
     */
    public function mora(?Carbon $al = null): float
    {
        $dias = $this->diasMora($al);

        return $dias > 0 ? round($this->saldo() * (float) $this->tasa_mora / 100 * $dias, 2) : 0.0;
    }

    /** Total a cobrar hoy de esta cuota = saldo impago + mora acumulada. */
    public function totalAcobrar(?Carbon $al = null): float
    {
        return round($this->saldo() + $this->mora($al), 2);
    }
}
