<div class="space-y-6">

    @php
        $hoy = \Carbon\Carbon::today();
        $money = fn ($n) => '$' . number_format((float) $n, 0, ',', '.');
        $money2 = fn ($n) => '$' . number_format((float) $n, 2, ',', '.');
        $diasBadge = function (int $d) {
            if ($d <= 0)  return ['bg-gray-100 text-graphite', 'al día'];
            if ($d <= 3)  return ['bg-amber-100 text-amber-700', $d . ($d === 1 ? ' día' : ' días')];
            if ($d <= 7)  return ['bg-orange-100 text-orange-700', $d . ' días'];
            return ['bg-red-100 text-red-700', $d . ' días'];
        };
    @endphp

    {{-- ===== Encabezado ===== --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-ink">Cobranza — Supervisión</h1>
            <p class="text-sm text-muted">Apertura del día, atrasos y eficacia de todos los cobradores</p>
        </div>
        <div class="rounded-lg bg-anthracite px-4 py-2 text-right">
            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Apertura</p>
            <p class="tabular text-sm font-extrabold text-white">{{ $hoy->format('d/m/Y') }}</p>
        </div>
    </div>

    @if ($mensaje)
        <div class="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm font-semibold text-green-700">
            <span class="material-symbols-outlined text-[18px]">check_circle</span> {{ $mensaje }}
        </div>
    @endif

    {{-- ===== Filtros (cobrador / zona / cliente) ===== --}}
    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-gray-100 bg-white p-3 shadow-card">
        <span class="material-symbols-outlined text-[20px] text-brand">filter_alt</span>
        <select wire:model.live="filtroCobradorId" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-ink focus:border-brand focus:outline-none">
            <option value="">Todos los cobradores</option>
            @foreach ($cobradores as $id => $nombre)
                <option value="{{ $id }}">{{ $nombre }}</option>
            @endforeach
        </select>
        <select wire:model.live="filtroZonaId" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-ink focus:border-brand focus:outline-none">
            <option value="">Todas las zonas</option>
            @foreach ($zonas as $id => $nombre)
                <option value="{{ $id }}">{{ $nombre }}</option>
            @endforeach
        </select>
        <div class="relative flex-1 min-w-[180px]">
            <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-gray-400">search</span>
            <input wire:model.live.debounce.300ms="buscar" type="text" placeholder="Buscar cliente…"
                   class="w-full rounded-lg border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-ink focus:border-brand focus:outline-none">
        </div>
    </div>

    {{-- ===== Tabs ===== --}}
    @php
        $tabs = [
            'apertura'  => ['Apertura', 'notifications_active'],
            'atrasados' => ['Atrasados', 'running_with_errors'],
            'hoy'       => ['Vencen hoy', 'today'],
            'agenda'    => ['Agenda semanal', 'calendar_month'],
        ];
        if ($puedeNovedades) {
            $tabs['novedades'] = ['Novedades (no-visita)', 'event_busy'];
        }
    @endphp
    <div class="flex flex-wrap gap-1 border-b border-gray-200">
        @foreach ($tabs as $key => [$label, $icon])
            <button type="button" wire:click="$set('tab', '{{ $key }}')"
                    class="flex items-center gap-1.5 border-b-2 px-4 py-2.5 text-sm font-bold transition-colors
                           {{ $tab === $key ? 'border-brand text-brand' : 'border-transparent text-muted hover:text-ink' }}">
                <span class="material-symbols-outlined text-[18px]">{{ $icon }}</span>
                {{ $label }}
                @if ($key === 'atrasados' && $kpis['cant_atrasadas'] > 0)
                    <span class="rounded-full bg-red-100 px-1.5 text-[11px] font-extrabold text-red-700">{{ $kpis['cant_atrasadas'] }}</span>
                @elseif ($key === 'hoy' && $kpis['cant_hoy'] > 0)
                    <span class="rounded-full bg-amber-100 px-1.5 text-[11px] font-extrabold text-amber-700">{{ $kpis['cant_hoy'] }}</span>
                @endif
            </button>
        @endforeach
    </div>

    {{-- ================= APERTURA ================= --}}
    @if ($tab === 'apertura')
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <x-kpi-card variant="red" title="Atrasado" :value="$money($kpis['atrasado'])" icon="warning"
                        :subtitle="$kpis['clientes_atrasados'] . ' cliente' . ($kpis['clientes_atrasados'] === 1 ? '' : 's') . ' · ' . $kpis['cant_atrasadas'] . ' cuota' . ($kpis['cant_atrasadas'] === 1 ? '' : 's')" />
            <x-kpi-card variant="beige" title="Vence Hoy" :value="$money($kpis['hoy'])" icon="event"
                        :subtitle="$kpis['cant_hoy'] . ' cuota' . ($kpis['cant_hoy'] === 1 ? '' : 's')" />
            <x-kpi-card variant="blue" title="Mora acumulada" :value="$money($kpis['mora'])" icon="trending_up" subtitle="Interés por atraso" />
            <x-kpi-card variant="brand" title="A cobrar hoy" :value="$money($kpis['total_hoy'])" icon="account_balance_wallet" subtitle="Atrasado + vence hoy" />
        </div>

        {{-- 🔔 ALERTA DE ATRASO — el pedido nº1 del cliente --}}
        <x-panel>
            <div class="flex items-center gap-2 border-b border-gray-100 bg-red-50 px-5 py-3">
                <span class="material-symbols-outlined text-[22px] text-red-600">notifications_active</span>
                <h3 class="text-base font-extrabold uppercase tracking-wide text-red-700">Clientes atrasados</h3>
                <span class="ml-auto rounded-full bg-red-600 px-2.5 py-0.5 text-xs font-extrabold text-white">{{ count($atrasadas) }}</span>
            </div>

            @forelse ($atrasadas as $f)
                @php [$badgeClass, $badgeText] = $diasBadge($f['dias']); @endphp
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-l-4 border-gray-100 border-l-red-500 px-5 py-3 hover:bg-gray-50">
                    <div class="min-w-[140px] flex-1">
                        <p class="font-bold text-ink">{{ $f['cliente'] }}</p>
                        <p class="text-[12px] text-muted">Venta {{ $f['venta'] }} · cuota {{ $f['numero'] }} · vencía {{ $f['vence']->format('d/m/Y') }}</p>
                    </div>
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[12px] font-extrabold {{ $badgeClass }}">
                        <span class="material-symbols-outlined text-[15px]">schedule</span>{{ $badgeText }} de atraso
                    </span>
                    <div class="text-[12px] text-graphite">
                        <span class="material-symbols-outlined align-middle text-[15px] text-gray-400">person_pin_circle</span>
                        {{ $f['cobrador'] }} · {{ $f['zona'] }}
                    </div>
                    <div class="tabular ml-auto text-right">
                        <p class="font-extrabold text-ink">{{ $money2($f['total']) }}</p>
                        @if ($f['mora'] > 0)
                            <p class="text-[11px] text-red-600">saldo {{ $money2($f['saldo']) }} + mora {{ $money2($f['mora']) }}</p>
                        @endif
                    </div>
                    @if ($this->puede('registrar_cobro'))
                        <button wire:click="registrarCobro({{ $f['id'] }})"
                                wire:confirm="Registrar el cobro de la cuota {{ $f['numero'] }} de {{ $f['cliente'] }} por {{ $money2($f['total']) }}?"
                                class="inline-flex items-center gap-1 rounded-lg bg-brand px-3 py-2 text-sm font-bold text-white transition-colors hover:bg-brand/90">
                            <span class="material-symbols-outlined text-[18px]">payments</span> Cobrar
                        </button>
                    @endif
                </div>
            @empty
                <div class="flex items-center gap-2 px-5 py-6 text-sm font-semibold text-success">
                    <span class="material-symbols-outlined">task_alt</span> No hay clientes atrasados con este filtro. Cartera al día. 🎉
                </div>
            @endforelse
        </x-panel>

        {{-- Vence HOY --}}
        <x-panel>
            <div class="flex items-center gap-2 border-b border-gray-100 bg-amber-50 px-5 py-3">
                <span class="material-symbols-outlined text-[22px] text-amber-600">event</span>
                <h3 class="text-base font-extrabold uppercase tracking-wide text-amber-700">Vencen hoy</h3>
                <span class="ml-auto rounded-full bg-amber-500 px-2.5 py-0.5 text-xs font-extrabold text-white">{{ count($vencenHoy) }}</span>
            </div>

            @forelse ($vencenHoy as $f)
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-l-4 border-gray-100 border-l-amber-400 px-5 py-3 hover:bg-gray-50">
                    <div class="min-w-[140px] flex-1">
                        <p class="font-bold text-ink">{{ $f['cliente'] }}</p>
                        <p class="text-[12px] text-muted">Venta {{ $f['venta'] }} · cuota {{ $f['numero'] }}</p>
                    </div>
                    <div class="text-[12px] text-graphite">
                        <span class="material-symbols-outlined align-middle text-[15px] text-gray-400">person_pin_circle</span>
                        {{ $f['cobrador'] }} · {{ $f['zona'] }}
                    </div>
                    <p class="tabular ml-auto font-extrabold text-ink">{{ $money2($f['total']) }}</p>
                    @if ($this->puede('registrar_cobro'))
                        <button wire:click="registrarCobro({{ $f['id'] }})"
                                wire:confirm="Registrar el cobro de la cuota {{ $f['numero'] }} de {{ $f['cliente'] }} por {{ $money2($f['total']) }}?"
                                class="inline-flex items-center gap-1 rounded-lg bg-brand px-3 py-2 text-sm font-bold text-white transition-colors hover:bg-brand/90">
                            <span class="material-symbols-outlined text-[18px]">payments</span> Cobrar
                        </button>
                    @endif
                </div>
            @empty
                <p class="px-5 py-6 text-sm text-muted">Ninguna cuota vence hoy con este filtro.</p>
            @endforelse
        </x-panel>
    @endif

    {{-- ================= ATRASADOS (tabla) ================= --}}
    @if ($tab === 'atrasados')
        <x-panel title="Cuotas atrasadas (impagas y vencidas)">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wide text-muted">
                            <th class="px-5 py-3 font-bold">Cliente</th>
                            <th class="px-5 py-3 font-bold">Venta · cuota</th>
                            <th class="px-5 py-3 font-bold">Vencía</th>
                            <th class="px-5 py-3 font-bold">Atraso</th>
                            <th class="px-5 py-3 font-bold">Cobrador · zona</th>
                            <th class="px-5 py-3 text-right font-bold">Saldo</th>
                            <th class="px-5 py-3 text-right font-bold">Mora</th>
                            <th class="px-5 py-3 text-right font-bold">Total</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="tabular">
                        @forelse ($atrasadas as $f)
                            @php [$badgeClass, $badgeText] = $diasBadge($f['dias']); @endphp
                            <tr class="border-t border-gray-100">
                                <td class="px-5 py-3 font-semibold text-ink">{{ $f['cliente'] }}</td>
                                <td class="px-5 py-3 text-graphite">{{ $f['venta'] }} · {{ $f['numero'] }}</td>
                                <td class="px-5 py-3 text-graphite">{{ $f['vence']->format('d/m/Y') }}</td>
                                <td class="px-5 py-3"><span class="rounded-full px-2 py-0.5 text-[11px] font-extrabold {{ $badgeClass }}">{{ $badgeText }}</span></td>
                                <td class="px-5 py-3 text-graphite">{{ $f['cobrador'] }} · {{ $f['zona'] }}</td>
                                <td class="px-5 py-3 text-right text-graphite">{{ $money2($f['saldo']) }}</td>
                                <td class="px-5 py-3 text-right {{ $f['mora'] > 0 ? 'font-bold text-red-600' : 'text-gray-300' }}">{{ $f['mora'] > 0 ? $money2($f['mora']) : '—' }}</td>
                                <td class="px-5 py-3 text-right font-extrabold text-ink">{{ $money2($f['total']) }}</td>
                                <td class="px-5 py-3 text-right">
                                    @if ($this->puede('registrar_cobro'))
                                        <button wire:click="registrarCobro({{ $f['id'] }})"
                                                wire:confirm="Registrar el cobro de la cuota {{ $f['numero'] }} de {{ $f['cliente'] }} por {{ $money2($f['total']) }}?"
                                                class="inline-flex items-center gap-1 rounded-lg bg-brand px-3 py-1.5 text-xs font-bold text-white hover:bg-brand/90">
                                            <span class="material-symbols-outlined text-[16px]">payments</span> Cobrar
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-5 py-8 text-center text-sm font-semibold text-success">Sin cuotas atrasadas con este filtro. 🎉</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-panel>
    @endif

    {{-- ================= VENCEN HOY (tabla) ================= --}}
    @if ($tab === 'hoy')
        <x-panel title="Cuotas que vencen hoy">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wide text-muted">
                            <th class="px-5 py-3 font-bold">Cliente</th>
                            <th class="px-5 py-3 font-bold">Venta · cuota</th>
                            <th class="px-5 py-3 font-bold">Cobrador · zona</th>
                            <th class="px-5 py-3 text-right font-bold">A cobrar</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="tabular">
                        @forelse ($vencenHoy as $f)
                            <tr class="border-t border-gray-100">
                                <td class="px-5 py-3 font-semibold text-ink">{{ $f['cliente'] }}</td>
                                <td class="px-5 py-3 text-graphite">{{ $f['venta'] }} · {{ $f['numero'] }}</td>
                                <td class="px-5 py-3 text-graphite">{{ $f['cobrador'] }} · {{ $f['zona'] }}</td>
                                <td class="px-5 py-3 text-right font-extrabold text-ink">{{ $money2($f['total']) }}</td>
                                <td class="px-5 py-3 text-right">
                                    @if ($this->puede('registrar_cobro'))
                                        <button wire:click="registrarCobro({{ $f['id'] }})"
                                                wire:confirm="Registrar el cobro de la cuota {{ $f['numero'] }} de {{ $f['cliente'] }} por {{ $money2($f['total']) }}?"
                                                class="inline-flex items-center gap-1 rounded-lg bg-brand px-3 py-1.5 text-xs font-bold text-white hover:bg-brand/90">
                                            <span class="material-symbols-outlined text-[16px]">payments</span> Cobrar
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-muted">Ninguna cuota vence hoy con este filtro.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-panel>
    @endif

    {{-- ================= AGENDA SEMANAL (lun-sáb) ================= --}}
    @if ($tab === 'agenda')
        <x-panel title="Agenda semanal · lunes a sábado">
            @php $maxMonto = max(1, collect($agenda)->max('monto')); @endphp
            <p class="px-5 pt-4 text-xs text-muted">Los 6 días de cobranza de la semana (no se cobra domingo). Tocá un día para ver los clientes a visitar.</p>
            <div class="divide-y divide-gray-100 p-2">
                @foreach ($agenda as $d)
                    <details class="group rounded-lg {{ $d['es_hoy'] ? 'bg-brand/5' : '' }}" @if ($d['es_hoy']) open @endif>
                        <summary class="flex cursor-pointer list-none items-center gap-4 rounded-lg px-3 py-3 hover:bg-gray-50">
                            <div class="w-28">
                                <p class="text-sm font-bold capitalize text-ink">{{ $d['fecha']->isoFormat('ddd D/MM') }}</p>
                                @if ($d['es_hoy'])<span class="text-[11px] font-extrabold uppercase text-brand">Hoy</span>@endif
                            </div>
                            <div class="flex-1">
                                <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                                    <div class="h-full rounded-full {{ $d['es_hoy'] ? 'bg-brand' : 'bg-graphite/40' }}" style="width: {{ min(100, round($d['monto'] / $maxMonto * 100)) }}%"></div>
                                </div>
                            </div>
                            <div class="w-16 text-right text-[12px] font-semibold text-muted">{{ $d['cant'] }} cuota{{ $d['cant'] === 1 ? '' : 's' }}</div>
                            <div class="tabular w-32 text-right font-extrabold text-ink">{{ $money($d['monto']) }}</div>
                            <span class="material-symbols-outlined text-[20px] text-muted transition-transform group-open:rotate-180">expand_more</span>
                        </summary>
                        <div class="border-t border-gray-100 bg-gray-50/60 px-4 py-2">
                            @forelse ($d['clientes'] as $cli)
                                <div class="flex items-center justify-between py-1 text-sm">
                                    <span class="text-ink"><span class="font-semibold">{{ $cli['cliente'] }}</span> <span class="text-[11px] text-muted">· cuota #{{ $cli['numero'] }} · {{ $cli['zona'] }} ({{ $cli['cobrador'] }})</span></span>
                                    <span class="tabular font-bold text-graphite">{{ $money($cli['total']) }}</span>
                                </div>
                            @empty
                                <p class="py-2 text-sm text-muted">Sin cobros programados ese día.</p>
                            @endforelse
                        </div>
                    </details>
                @endforeach
            </div>
        </x-panel>
    @endif

    {{-- ================= NOVEDADES: "el cobrador no pasó" ================= --}}
    @if ($tab === 'novedades' && $puedeNovedades)
        <x-panel title="Novedades de cobranza — el cobrador no pasó">
            <p class="px-5 pt-4 text-xs text-muted">Marcá una <b>zona + fecha</b> en la que el cobrador no fue (enfermedad, robo, ausencia). Esos días <b>no devengan mora</b> para las cuotas de esa zona — el cliente no absorbe lo que no le corresponde. <span class="font-bold text-graphite">Solo administración</span> (el cobrador no puede marcarlo).</p>

            <div class="flex flex-wrap items-end gap-3 border-b border-gray-100 p-5">
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase text-muted">Zona</label>
                    <select wire:model="nvZonaId" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand">
                        <option value="">— elegí zona —</option>
                        @foreach ($zonas as $id => $nombre)
                            <option value="{{ $id }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                    @error('nvZonaId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase text-muted">Fecha</label>
                    <input type="date" wire:model="nvFecha" max="{{ now()->toDateString() }}" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
                    @error('nvFecha') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase text-muted">Motivo</label>
                    <select wire:model="nvMotivo" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand">
                        @foreach ($motivos as $val => $lbl)
                            <option value="{{ $val }}">{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1 min-w-[160px]">
                    <label class="mb-1 block text-xs font-bold uppercase text-muted">Nota (opcional)</label>
                    <input type="text" wire:model="nvNota" placeholder="Ej: parte médico, nº de denuncia…" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
                </div>
                <button type="button" wire:click="registrarNoVisita" class="flex items-center gap-2 rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-dark">
                    <span class="material-symbols-outlined text-[20px]">event_busy</span> Registrar no-visita
                </button>
            </div>

            <div class="overflow-x-auto p-5">
                <table class="w-full text-left text-sm">
                    @php $estBadge = ['pendiente' => 'bg-sky-100 text-sky-700', 'aprobada' => 'bg-green-100 text-green-700', 'rechazada' => 'bg-red-100 text-red-700']; @endphp
                    <thead><tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-muted">
                        <th class="px-3 py-2.5 font-bold">Fecha</th><th class="px-3 py-2.5 font-bold">Zona</th>
                        <th class="px-3 py-2.5 font-bold">Motivo</th><th class="px-3 py-2.5 font-bold">Estado</th>
                        <th class="px-3 py-2.5 font-bold">Origen</th><th class="px-3 py-2.5 font-bold">Nota</th><th class="px-3 py-2.5"></th>
                    </tr></thead>
                    <tbody>
                        @forelse ($novedades as $nv)
                            <tr class="border-t border-gray-100 {{ $nv->estado === 'pendiente' ? 'bg-sky-50/50' : '' }}" wire:key="nv-{{ $nv->id }}">
                                <td class="px-3 py-2.5 font-semibold text-ink">{{ $nv->fecha->format('d/m/Y') }}</td>
                                <td class="px-3 py-2.5">{{ $nv->zona?->nombre ?? '—' }}</td>
                                <td class="px-3 py-2.5"><span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-700">{{ $nv->motivoLabel() }}</span></td>
                                <td class="px-3 py-2.5"><span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $estBadge[$nv->estado] ?? 'bg-gray-100 text-graphite' }}">{{ $nv->estadoLabel() }}</span></td>
                                <td class="px-3 py-2.5 text-[11px] text-muted">
                                    @if ($nv->solicitante)Reportó: {{ $nv->solicitante->name }}@else Cargó: {{ $nv->registrador?->name ?? '—' }}@endif
                                    @if ($nv->aprobador)<br>Aprobó: {{ $nv->aprobador->name }}@endif
                                </td>
                                <td class="px-3 py-2.5 text-muted">{{ $nv->nota ?: '—' }}</td>
                                <td class="px-3 py-2.5 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        @if ($nv->estado === 'pendiente')
                                            <button type="button" wire:click="aprobarNoVisita({{ $nv->id }})" class="inline-flex items-center gap-1 rounded-lg bg-green-600 px-2.5 py-1 text-[11px] font-bold text-white hover:bg-green-700"><span class="material-symbols-outlined text-[14px]">check</span> Aprobar</button>
                                            <button type="button" wire:click="rechazarNoVisita({{ $nv->id }})" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2.5 py-1 text-[11px] font-bold text-graphite hover:bg-gray-100">Rechazar</button>
                                        @endif
                                        <button type="button" wire:click="eliminarNoVisita({{ $nv->id }})" wire:confirm="¿Eliminar la novedad? La mora de ese día volverá a correr." class="text-muted hover:text-danger" title="Eliminar"><span class="material-symbols-outlined text-[20px]">delete</span></button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-muted">Sin novedades registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-panel>
    @endif

</div>
