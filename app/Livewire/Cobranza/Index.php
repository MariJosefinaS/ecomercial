<?php

namespace App\Livewire\Cobranza;

use App\Livewire\Concerns\AutorizaPermisos;
use App\Models\Cuota;
use App\Models\NoVisita;
use App\Models\Zona;
use App\Support\Cobranza;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Cobranza — planilla del cobrador con el pedido nº1 del cliente:
 * al ABRIR cobranza, ver de una qué clientes están atrasados y qué vence hoy.
 *
 * Trabaja sobre el cronograma real (App\Models\Cuota). "Atrasado" es derivado
 * (pendiente && vencimiento < hoy) y suma mora por día; nada de proceso nocturno.
 */
#[Layout('components.layouts.app')]
#[Title('Cobranza — E.Comercial')]
class Index extends Component
{
    use AutorizaPermisos;

    #[Url(as: 'sub')]
    public string $tab = 'apertura';   // apertura | atrasados | hoy | agenda | novedades

    #[Url(as: 'cobrador')]
    public ?int $filtroCobradorId = null;

    #[Url(as: 'zona')]
    public ?int $filtroZonaId = null;

    public string $buscar = '';
    public ?string $mensaje = null;

    // ===== Novedades "el cobrador no pasó" (suspende mora) — solo con permiso =====
    public ?int $nvZonaId = null;
    public string $nvFecha = '';
    public string $nvMotivo = 'ausente';
    public string $nvNota = '';

    // ===== Rendición de efectivo (Tesorería) =====
    public string $rendFecha = '';
    public string $rendRecibido = '';
    public string $rendNota = '';

    public function mount(): void
    {
        // Tablero de SUPERVISIÓN (todos los cobradores) — vive en Tesorería. El cobrador usa /cobranza/planilla.
        $this->autorizar('supervisar_cobranza');
        if ($this->nvFecha === '') {
            $this->nvFecha = Carbon::today()->toDateString();
        }
        if ($this->rendFecha === '') {
            $this->rendFecha = Carbon::today()->toDateString();
        }
    }

    private function rendFechaCarbon(): Carbon
    {
        return Carbon::parse($this->rendFecha)->startOfDay();
    }

    /** Registra la rendición de efectivo del cobrador filtrado (esperado vs recibido + ajuste de caja). */
    public function rendirEfectivo(): void
    {
        $this->autorizar('supervisar_cobranza');
        if (! $this->filtroCobradorId) {
            $this->mensaje = 'Elegí un cobrador para rendir su efectivo.';
            return;
        }
        $this->validate(['rendRecibido' => 'required|numeric|min:0'], attributes: ['rendRecibido' => 'efectivo recibido']);

        $r = \App\Support\Rendiciones::rendirEfectivo(
            $this->filtroCobradorId, $this->rendFechaCarbon(), (float) $this->rendRecibido, $this->rendNota ?: null, auth()->id(),
        );
        $this->reset(['rendRecibido', 'rendNota']);
        $this->mensaje = $r['mensaje'];
    }

    /** Concilia UNA parte (transferencia/cheque): la vi acreditada en el banco / ingresada a cartera. */
    public function conciliarParte(int $cobroMedioId): void
    {
        $this->autorizar('supervisar_cobranza');
        \App\Support\Rendiciones::conciliarParte($cobroMedioId, auth()->id());
        $this->mensaje = 'Movimiento conciliado.';
    }

    /** Confirma la recepción de un cobro completo (concilia todas sus partes pendientes). */
    public function confirmarCobro(int $cobroId): void
    {
        $this->autorizar('supervisar_cobranza');
        $n = \App\Support\Rendiciones::confirmarCobro($cobroId, auth()->id());
        $this->mensaje = $n > 0 ? "Cobro confirmado ({$n} medio/s recibido/s)." : 'El cobro ya estaba confirmado.';
    }

    // ===== Cobro NO rendido / robado =====
    public ?int $noRendMedioId = null;
    public string $noRendMotivo = '';

    public function pedirNoRendido(int $cobroMedioId): void
    {
        $this->autorizar('supervisar_cobranza');
        $this->noRendMedioId = $cobroMedioId;
        $this->noRendMotivo = '';
        $this->resetValidation();
    }

    public function cerrarNoRendido(): void
    {
        $this->noRendMedioId = null;
        $this->noRendMotivo = '';
    }

    public function marcarNoRendido(): void
    {
        $this->autorizar('supervisar_cobranza');
        if (! $this->noRendMedioId) {
            return;
        }
        $this->validate(['noRendMotivo' => 'required|min:3'], attributes: ['noRendMotivo' => 'motivo']);
        $ok = \App\Support\Rendiciones::marcarNoRendido($this->noRendMedioId, $this->noRendMotivo, auth()->id());
        $this->mensaje = $ok
            ? 'Cobro marcado como NO RENDIDO: se revirtió el ingreso en caja y se le cargó al cobrador. El cliente no se afecta.'
            : 'No se pudo marcar (ya estaba conciliado o no rendido).';
        $this->cerrarNoRendido();
    }

    /** Concilia TODAS las partes pendientes de un medio para el día/cobrador. */
    public function conciliarMedio(string $medio): void
    {
        $this->autorizar('supervisar_cobranza');
        $n = \App\Support\Rendiciones::conciliarMedio($medio, $this->filtroCobradorId, $this->rendFechaCarbon(), auth()->id());
        $this->mensaje = $n > 0 ? "{$n} movimiento(s) conciliado(s)." : 'No había movimientos pendientes.';
    }

    /** Registrar el cobro de una cuota desde la planilla (reusa el servicio central). */
    public function registrarCobro(int $cuotaId): void
    {
        $this->autorizar('registrar_cobro');
        $cuota = Cuota::with('venta:id,numero', 'cliente:id,nombre')->find($cuotaId);
        if (! $cuota) {
            return;
        }

        $r = Cobranza::cobrarCuota($cuota);
        $nombre = $cuota->cliente?->nombre ?? 'Cliente';
        $this->mensaje = $r['ok'] ? "{$nombre} — {$r['mensaje']}" : $r['mensaje'];
    }

    /** Marca "el cobrador no pasó" una zona en una fecha (suspende la mora de ese día). Solo con permiso. */
    public function registrarNoVisita(): void
    {
        $this->autorizar('gestionar_novedades_cobranza');
        $this->validate([
            'nvZonaId' => 'required|exists:zonas,id',
            'nvFecha' => 'required|date|before_or_equal:today',
            'nvMotivo' => 'required|in:' . implode(',', array_keys(NoVisita::MOTIVOS)),
        ], attributes: ['nvZonaId' => 'zona', 'nvFecha' => 'fecha', 'nvMotivo' => 'motivo']);

        // El supervisor la crea ya APROBADA (es la autoridad).
        NoVisita::updateOrCreate(
            ['zona_id' => $this->nvZonaId, 'fecha' => $this->nvFecha],
            [
                'motivo' => $this->nvMotivo, 'nota' => $this->nvNota ?: null,
                'estado' => 'aprobada', 'registrado_por' => auth()->id(),
                'aprobado_por' => auth()->id(), 'aprobado_at' => now(),
            ],
        );
        NoVisita::limpiarCache();
        $this->reset(['nvNota']);
        $this->mensaje = 'Novedad registrada y aprobada: la mora de esa zona no corre ese día.';
    }

    /** Aprueba un reporte de no-visita del cobrador → recién ahí suspende la mora. */
    public function aprobarNoVisita(int $id): void
    {
        $this->autorizar('gestionar_novedades_cobranza');
        NoVisita::where('id', $id)->update(['estado' => 'aprobada', 'aprobado_por' => auth()->id(), 'aprobado_at' => now()]);
        NoVisita::limpiarCache();
        $this->mensaje = 'Reporte aprobado — la mora de ese día no corre para esa zona.';
    }

    public function rechazarNoVisita(int $id): void
    {
        $this->autorizar('gestionar_novedades_cobranza');
        NoVisita::where('id', $id)->update(['estado' => 'rechazada', 'aprobado_por' => auth()->id(), 'aprobado_at' => now()]);
        NoVisita::limpiarCache();
        $this->mensaje = 'Reporte rechazado — la mora sigue corriendo.';
    }

    public function eliminarNoVisita(int $id): void
    {
        $this->autorizar('gestionar_novedades_cobranza');
        NoVisita::where('id', $id)->delete();
        NoVisita::limpiarCache();
        $this->mensaje = 'Novedad eliminada — la mora de ese día vuelve a correr.';
    }

    /** Cuotas pendientes (cronograma vivo) filtradas por cobrador / zona / cliente. */
    private function pendientes(): Collection
    {
        return Cuota::with('cliente:id,nombre', 'venta:id,numero', 'zonaRel.cobrador:id,name')
            ->where('estado', 'pendiente')
            // Filtros por ENTIDAD (no por el texto viejo de cuotas.cobrador/zona, que queda desactualizado al reasignar).
            ->when($this->filtroCobradorId, fn ($q) => $q->whereHas('zonaRel', fn ($z) => $z->where('cobrador_id', $this->filtroCobradorId)))
            ->when($this->filtroZonaId, fn ($q) => $q->where('zona_id', $this->filtroZonaId))
            ->orderBy('fecha_vencimiento')
            ->orderBy('numero')
            ->get()
            ->when(
                $this->buscar !== '',
                fn (Collection $c) => $c->filter(fn (Cuota $q) => str_contains(
                    mb_strtolower($q->cliente?->nombre ?? ''),
                    mb_strtolower(trim($this->buscar))
                ))
            );
    }

    /** Proyección de una fila de la planilla para la vista. */
    private function fila(Cuota $c, Carbon $hoy): array
    {
        return [
            'id' => $c->id,
            'cliente' => $c->cliente?->nombre ?? '—',
            'venta' => $c->venta?->numero ?? '—',
            'numero' => (int) $c->numero,
            'vence' => $c->fecha_vencimiento,
            'dias' => $c->diasAtraso($hoy),
            'cobrador' => $c->zonaRel?->cobrador?->name ?? ($c->cobrador ?: '—'),
            'zona' => $c->zonaRel?->nombre ?? ($c->zona ?: '—'),
            'saldo' => $c->saldo(),
            'mora' => $c->mora($hoy),
            'total' => $c->totalAcobrar($hoy),
        ];
    }

    public function render()
    {
        $hoy = Carbon::today();
        $manana = $hoy->copy()->addDay();
        $pendientes = $this->pendientes();

        // Particiones del cronograma
        $atrasadas = $pendientes->filter(fn (Cuota $c) => $c->estaVencida($hoy))
            ->sortByDesc(fn (Cuota $c) => $c->diasAtraso($hoy));
        $vencenHoy = $pendientes->filter(fn (Cuota $c) => $c->fecha_vencimiento->isSameDay($hoy));
        $vencenManana = $pendientes->filter(fn (Cuota $c) => $c->fecha_vencimiento->isSameDay($manana));

        // KPIs de apertura del día
        $montoAtrasado = $atrasadas->sum(fn (Cuota $c) => $c->totalAcobrar($hoy));
        $montoHoy = $vencenHoy->sum(fn (Cuota $c) => $c->saldo());
        $moraTotal = $atrasadas->sum(fn (Cuota $c) => $c->mora($hoy));
        $clientesAtrasados = $atrasadas->pluck('cliente_id')->unique()->count();

        // Agenda semanal: 6 días de cobranza (lunes a sábado; NO se cobra domingo).
        $agenda = [];
        $d = $hoy->copy();
        while (count($agenda) < 6) {
            if ($d->dayOfWeek !== Carbon::SUNDAY) {
                $delDia = $pendientes->filter(fn (Cuota $c) => $c->fecha_vencimiento->isSameDay($d))
                    ->sortByDesc(fn (Cuota $c) => $c->totalAcobrar($d));
                $agenda[] = [
                    'fecha' => $d->copy(),
                    'es_hoy' => $d->isToday(),
                    'cant' => $delDia->count(),
                    'monto' => $delDia->sum(fn (Cuota $c) => $c->totalAcobrar($d)),
                    // Clientes a visitar ese día (para el desplegable).
                    'clientes' => $delDia->map(fn (Cuota $c) => [
                        'cliente' => $c->cliente?->nombre ?? '—',
                        'zona' => $c->zonaRel?->nombre ?? '—',
                        'cobrador' => $c->zonaRel?->cobrador?->name ?? '—',
                        'numero' => (int) $c->numero,
                        'total' => $c->totalAcobrar($d),
                    ])->values()->all(),
                ];
            }
            $d->addDay();
        }

        // Opciones de filtro REALES (por entidad: id => nombre), no por el texto viejo de las cuotas.
        $cobradores = \App\Models\User::whereIn('id', Zona::whereNotNull('cobrador_id')->distinct()->pluck('cobrador_id'))
            ->orderBy('name')->pluck('name', 'id')->all();
        $zonas = Zona::where('activo', true)->orderBy('nombre')->pluck('nombre', 'id')->all();

        // Datos de rendición (solo se computan si es la pestaña activa).
        $rendicion = $this->tab === 'rendicion'
            ? \App\Support\Rendiciones::resumen($this->filtroCobradorId, $this->rendFechaCarbon())
            : null;

        // Créditos incobrables (solo si es la pestaña activa).
        $incobrables = $this->tab === 'incobrables'
            ? \App\Support\Incobrables::detalle($this->filtroCobradorId, $this->filtroZonaId, $hoy)
            : collect();

        // Cobros del día a confirmar (cobro-céntrico, con medios+comprobantes).
        $cobrosDia = $this->tab === 'cobros'
            ? \App\Support\Rendiciones::cobrosDelDia($this->filtroCobradorId, $this->rendFechaCarbon())
            : null;

        return view('livewire.cobranza.index', [
            'rendicion' => $rendicion,
            'cobrosDia' => $cobrosDia,
            'incobrables' => $incobrables,
            'kpis' => [
                'atrasado' => $montoAtrasado,
                'clientes_atrasados' => $clientesAtrasados,
                'hoy' => $montoHoy,
                'total_hoy' => $montoAtrasado + $montoHoy,
                'mora' => $moraTotal,
                'cant_atrasadas' => $atrasadas->count(),
                'cant_hoy' => $vencenHoy->count(),
            ],
            'atrasadas' => $atrasadas->map(fn (Cuota $c) => $this->fila($c, $hoy))->values()->all(),
            'vencenHoy' => $vencenHoy->map(fn (Cuota $c) => $this->fila($c, $hoy))->values()->all(),
            'vencenManana' => $vencenManana->map(fn (Cuota $c) => $this->fila($c, $hoy))->values()->all(),
            'agenda' => $agenda,
            'cobradores' => $cobradores,
            'zonas' => $zonas,
            'puedeNovedades' => \App\Support\Permisos::puede(auth()->user()?->rol, 'gestionar_novedades_cobranza'),
            'novedades' => NoVisita::with('zona:id,nombre', 'registrador:id,name')->latest('fecha')->limit(30)->get(),
            'motivos' => NoVisita::MOTIVOS,
        ]);
    }
}
