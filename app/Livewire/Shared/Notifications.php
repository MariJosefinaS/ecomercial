<?php

namespace App\Livewire\Shared;

use App\Models\Compra;
use App\Models\SolicitudCompra;
use App\Models\Traspaso;
use App\Models\User;
use App\Models\Venta;
use App\Support\Permisos;
use Livewire\Component;

class Notifications extends Component
{
    /**
     * Marca de "leído hasta" capturada al montar (epoch). El listado y el badge
     * se calculan contra este valor → no parpadean al marcar leído en la misma
     * sesión (recién con un refresh la marca nueva oculta lo ya visto).
     */
    public ?int $seenRef = null;

    /** El usuario ya abrió la campanita en esta carga → ocultar el badge. */
    public bool $leido = false;

    public function mount(): void
    {
        $this->seenRef = auth()->user()?->notificaciones_vistas_at?->timestamp;
    }

    /** Al abrir la campanita: persistir la marca de visto (no leídas → leídas). */
    public function abrir(): void
    {
        $u = auth()->user();
        if ($u) {
            $u->forceFill(['notificaciones_vistas_at' => now()])->save();
        }
        $this->leido = true;
    }

    private function fmt(float|int|string|null $n): string
    {
        return '$' . number_format((float) $n, 2, ',', '.');
    }

    /** ¿El usuario aprueba cosas? → ve la bandeja de aprobaciones pendientes. */
    private function esAprobador(?User $u): bool
    {
        if (! $u) {
            return false;
        }

        return Permisos::puede($u->rol, 'aprobar_ventas')
            || Permisos::puede($u->rol, 'aprobar_compras')
            || Permisos::puede($u->rol, 'aprobar_devoluciones')
            || Permisos::puede($u->rol, 'aprobar_traspasos');
    }

    /**
     * Aprobaciones pendientes — SÓLO las que el usuario puede resolver por su permiso.
     * Antes se le mostraba TODO a cualquier aprobador (una solicitud de reposición
     * aparecía hasta en perfiles que no aprueban compras, sin que pudieran hacer nada).
     * Ahora cada categoría se gatea por su permiso específico.
     */
    public function aprobacionesPendientes(User $u): array
    {
        $items = collect();

        // Ventas → sólo quien aprueba ventas.
        if (Permisos::puede($u->rol, 'aprobar_ventas')) {
            $items = $items->concat(
                Venta::with(['vendedor:id,name'])->withCount('items')->where('estado', 'pendiente')->orderByDesc('id')->get()
                    ->map(fn (Venta $v) => [
                        'ts' => $v->updated_at?->timestamp ?? 0,
                        'tipo' => 'Venta', 'id' => $v->numero, 'icon' => 'point_of_sale', 'vv' => 'brand',
                        'desc' => ($v->cliente_nombre ?: 'Venta') . ' · ' . $v->items_count . ' ítem(s)',
                        'sub' => 'Solicita ' . ($v->vendedor?->name ?? '—'),
                        'monto' => $this->fmt($v->total),
                        'url' => route('ventas', ['highlight' => $v->numero]),
                    ])
            );
        }

        // Compras (órdenes) + solicitudes de reposición → sólo quien aprueba compras.
        if (Permisos::puede($u->rol, 'aprobar_compras')) {
            $items = $items->concat(
                Compra::with(['proveedor:id,nombre', 'usuario:id,name'])->where('estado', 'pendiente')->orderByDesc('id')->get()
                    ->map(fn (Compra $c) => [
                        'ts' => $c->updated_at?->timestamp ?? 0,
                        'tipo' => 'Compra', 'id' => $c->numero, 'icon' => 'shopping_cart', 'vv' => 'blue',
                        'desc' => 'Orden de compra · ' . ($c->proveedor?->nombre ?? '—'),
                        'sub' => 'Solicita ' . ($c->usuario?->name ?? '—'),
                        'monto' => $this->fmt($c->total),
                        'url' => route('compras', ['highlight' => $c->numero]),
                    ])
            )->concat(
                SolicitudCompra::with(['producto:id,nombre', 'solicitante:id,name'])->where('estado', 'pendiente')->orderByDesc('id')->get()
                    ->map(fn (SolicitudCompra $s) => [
                        'ts' => $s->updated_at?->timestamp ?? 0,
                        'tipo' => 'Solicitud', 'id' => $s->numero, 'icon' => 'inventory_2', 'vv' => 'gray',
                        'desc' => 'Reposición · ' . ($s->producto?->nombre ?? '—') . ' (x' . $s->cantidad . ')',
                        'sub' => 'Solicita ' . ($s->solicitante?->name ?? '—'),
                        'monto' => '',
                        'url' => route('compras', ['sub' => 'solicitudes']),
                    ])
            );
        }

        // Traspasos → sólo quien aprueba traspasos.
        if (Permisos::puede($u->rol, 'aprobar_traspasos')) {
            $items = $items->concat(
                Traspaso::with(['origen:id,nombre', 'destino:id,nombre', 'usuario:id,name'])->withCount('items')
                    ->where('estado', 'pendiente')->orderByDesc('id')->get()
                    ->map(fn (Traspaso $t) => [
                        'ts' => $t->updated_at?->timestamp ?? 0,
                        'tipo' => 'Traspaso', 'id' => $t->numero, 'icon' => 'swap_horiz', 'vv' => 'blue',
                        'desc' => 'Traspaso · ' . ($t->origen?->nombre ?? '—') . ' → ' . ($t->destino?->nombre ?? '—') . ' (' . $t->items_count . ' caja/s)',
                        'sub' => 'Solicita ' . ($t->usuario?->name ?? '—'),
                        'monto' => '',
                        'url' => route('traspasos', ['highlight' => $t->numero]),
                    ])
            );
        }

        return $items->all();
    }

    /** ¿Es encargado de depósito? (recepciona pero no aprueba). */
    private function esDeposito(?User $u): bool
    {
        return $u && Permisos::puede($u->rol, 'recepcionar') && ! $this->esAprobador($u);
    }

    /**
     * Avisos operativos del depósito: mercadería que LLEGA HOY y mercadería
     * ATRASADA (no llegó en la fecha estimada → debería correrse al día
     * siguiente). Son alertas vigentes del día (no se ocultan por "visto").
     */
    public function avisosDeposito(): array
    {
        $hoy = today();
        $compras = Compra::with(['proveedor:id,nombre', 'local:id,nombre'])
            ->whereIn('estado', ['pendiente', 'aprobada'])
            ->whereNotNull('fecha_estimada')
            ->orderBy('fecha_estimada')->get();

        $items = [];
        foreach ($compras as $c) {
            $fe = $c->fecha_estimada;
            $base = ($c->numero ?? 'OC') . ' · ' . ($c->proveedor?->nombre ?? '—')
                . ' · ' . ($c->local?->nombre ?? '—');

            if ($fe->isToday()) {
                $items[] = [
                    'ts' => now()->timestamp,
                    'tipo' => 'Llega hoy', 'id' => $c->numero, 'icon' => 'local_shipping', 'vv' => 'amber',
                    'desc' => 'Llega hoy: ' . $base,
                    'sub' => 'Entrega estimada para hoy · ' . $c->items()->count() . ' ítem(s)',
                    'monto' => '',
                    'url' => route('recepcion', ['highlight' => $c->numero]),
                ];
            } elseif ($fe->isPast()) {
                $dias = (int) $fe->startOfDay()->diffInDays($hoy);
                $items[] = [
                    'ts' => now()->timestamp,
                    'tipo' => 'Atrasada', 'id' => $c->numero, 'icon' => 'event_busy', 'vv' => 'red',
                    'desc' => 'No llegó: ' . $base,
                    'sub' => 'Debía llegar el ' . $fe->format('d/m') . ' (hace ' . $dias . ' día' . ($dias === 1 ? '' : 's') . ') · correr al día siguiente',
                    'monto' => '',
                    'url' => route('recepcion', ['highlight' => $c->numero]),
                ];
            }
        }

        return $items;
    }

    /**
     * Novedades del propio usuario (para quien NO aprueba): el resultado de
     * sus ventas (aprobada / rechazada) y el estado de sus solicitudes de
     * reposición.
     */
    public function misNovedades(User $u): array
    {
        $desde = now()->subDays(30);

        $ventas = Venta::where('vendedor_id', $u->id)
            ->whereIn('estado', ['aprobada', 'rechazada'])
            ->where('updated_at', '>=', $desde)
            ->orderByDesc('updated_at')->limit(20)->get()
            ->map(function (Venta $v) {
                $ok = $v->estado === 'aprobada';

                return [
                    'ts' => $v->updated_at?->timestamp ?? 0,
                    'tipo' => 'Venta', 'id' => $v->numero,
                    'icon' => $ok ? 'check_circle' : 'cancel',
                    'vv' => $ok ? 'green' : 'red',
                    'desc' => $ok
                        ? 'Tu venta fue aprobada'
                        : 'Tu venta fue rechazada' . ($v->motivo_rechazo ? ' · ' . $v->motivo_rechazo : ''),
                    'sub' => trim(($v->cliente_nombre ? $v->cliente_nombre . ' · ' : '') . ($v->updated_at?->diffForHumans() ?? '')),
                    'monto' => $this->fmt($v->total),
                    'url' => route('ventas', ['highlight' => $v->numero]),
                ];
            });

        // Sólo estados RESUELTOS: una solicitud recién creada ("pendiente") no es una
        // novedad para quien la pidió — no deriva en ninguna acción suya.
        $estadosSol = [
            'aprobada' => ['Tu reposición fue aprobada', 'check_circle', 'green'],
            'convertida' => ['Tu reposición ya está en una orden de compra', 'shopping_cart_checkout', 'green'],
            'rechazada' => ['Tu reposición fue rechazada', 'cancel', 'red'],
        ];

        $solicitudes = SolicitudCompra::with('producto:id,nombre', 'compra:id,numero')
            ->where('solicitante_id', $u->id)
            ->whereIn('estado', ['aprobada', 'convertida', 'rechazada'])
            ->where('updated_at', '>=', $desde)
            ->orderByDesc('updated_at')->limit(20)->get()
            ->map(function (SolicitudCompra $s) use ($estadosSol) {
                [$label, $icon, $vv] = $estadosSol[$s->estado] ?? ['Reposición', 'inventory_2', 'gray'];

                return [
                    'ts' => $s->updated_at?->timestamp ?? 0,
                    'tipo' => 'Solicitud', 'id' => $s->numero,
                    'icon' => $icon, 'vv' => $vv,
                    'desc' => $label,
                    'sub' => ($s->producto?->nombre ?? '—') . ' (x' . $s->cantidad . ')'
                        . ($s->compra ? ' · ' . $s->compra->numero : '')
                        . ($s->motivo_rechazo ? ' · ' . $s->motivo_rechazo : '')
                        . ' · ' . ($s->updated_at?->diffForHumans() ?? ''),
                    'monto' => '',
                    'url' => null,
                ];
            });

        return $ventas->concat($solicitudes)->all();
    }

    public function render()
    {
        $u = auth()->user();
        $aprobador = $this->esAprobador($u);
        $deposito = $this->esDeposito($u);

        if ($deposito) {
            // Avisos operativos del día: siempre visibles (no se filtran por "visto").
            $items = collect($this->avisosDeposito())
                ->sortByDesc(fn ($n) => $n['vv'] === 'red' ? 1 : 0)  // atrasadas primero
                ->map(fn ($n) => collect($n)->except('ts')->all())
                ->values()->all();

            return view('livewire.shared.notifications', [
                'items' => $items,
                'count' => $this->leido ? 0 : count($items),
                'titulo' => 'Avisos de depósito',
                'vacio' => 'No hay entregas para hoy ni atrasadas.',
                'verTodas' => route('recepcion'),
                'verTodasLabel' => 'Ir a Recepción',
            ]);
        }

        $todos = $aprobador
            ? $this->aprobacionesPendientes($u)
            : ($u ? $this->misNovedades($u) : []);

        // Sólo lo NO leído: ítems con timestamp posterior a la última visita.
        $items = collect($todos)
            ->filter(fn ($n) => $this->seenRef === null || ($n['ts'] ?? 0) > $this->seenRef)
            ->sortByDesc('ts')
            ->map(fn ($n) => collect($n)->except('ts')->all())
            ->values()
            ->all();

        return view('livewire.shared.notifications', [
            'items' => $items,
            'count' => $this->leido ? 0 : count($items),
            'titulo' => $aprobador ? 'Aprobaciones pendientes' : 'Mis novedades',
            'vacio' => $aprobador ? 'No hay aprobaciones nuevas.' : 'Estás al día, sin novedades nuevas.',
            'verTodas' => route('ventas'),
            'verTodasLabel' => 'Ver todas',
        ]);
    }
}
