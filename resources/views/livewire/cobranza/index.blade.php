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
            'cobros'    => ['Cobros del día', 'receipt_long'],
            'rendicion' => ['Rendición', 'account_balance_wallet'],
            'incobrables' => ['Incobrables', 'gpp_bad'],
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

    {{-- ================= COBROS DEL DÍA (a confirmar) ================= --}}
    @if ($tab === 'cobros' && $cobrosDia)
        <div class="space-y-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase text-muted">Fecha</label>
                    <input type="date" wire:model.live="rendFecha" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
                </div>
                <p class="text-xs text-muted">Lo que cada cobrador indicó que cobró. Confirmá la recepción tras ver el comprobante. Filtrá por cobrador arriba.</p>
            </div>

            {{-- Totales esperados por medio --}}
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-soft"><p class="text-[11px] font-bold uppercase text-muted">Total del día</p><p class="tabular mt-1 text-lg font-extrabold text-ink">{{ $money2($cobrosDia['total']) }}</p><p class="text-[11px] text-muted">{{ $cobrosDia['cant'] }} cobro(s)</p></div>
                <div class="rounded-2xl border border-green-100 bg-green-50/50 p-4 shadow-soft"><p class="text-[11px] font-bold uppercase text-muted">Efectivo</p><p class="tabular mt-1 text-lg font-extrabold text-green-700">{{ $money2($cobrosDia['efectivo']) }}</p></div>
                <div class="rounded-2xl border border-sky-100 bg-sky-50/50 p-4 shadow-soft"><p class="text-[11px] font-bold uppercase text-muted">Transferencias</p><p class="tabular mt-1 text-lg font-extrabold text-sky-700">{{ $money2($cobrosDia['transferencia']) }}</p></div>
                <div class="rounded-2xl border border-amber-100 bg-amber-50/50 p-4 shadow-soft"><p class="text-[11px] font-bold uppercase text-muted">Cheques</p><p class="tabular mt-1 text-lg font-extrabold text-amber-700">{{ $money2($cobrosDia['cheque']) }}</p></div>
            </div>

            <x-panel title="Cobros indicados por los cobradores">
                <div class="divide-y divide-gray-100">
                    @forelse ($cobrosDia['filas'] as $c)
                        <div class="p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-ink">{{ $c['cliente'] }} <span class="font-normal text-muted">· {{ $money2($c['total']) }}</span> @if ($c['dividido'])<span class="ml-1 rounded-full bg-brand-soft px-2 py-0.5 text-[10px] font-bold text-brand">Pago dividido</span>@endif</p>
                                    <p class="text-[11px] text-muted">Cobró {{ $c['cobrador'] }} · {{ $c['hora'] }} · Venta {{ $c['credito'] }}@if ($c['cuota']) · cuota #{{ $c['cuota'] }}@endif</p>
                                </div>
                                <div class="shrink-0">
                                    @if ($c['confirmado'])
                                        <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-3 py-1 text-[11px] font-bold text-green-700"><span class="material-symbols-outlined text-[15px]">check_circle</span> Recepción confirmada</span>
                                    @else
                                        <button wire:click="confirmarCobro({{ $c['id'] }})" wire:confirm="¿Confirmar que recibiste este cobro ({{ $money2($c['total']) }})?" class="inline-flex items-center gap-1 rounded-lg bg-brand px-3 py-1.5 text-xs font-bold text-white hover:bg-brand-dark"><span class="material-symbols-outlined text-[16px]">verified</span> Confirmar recepción</button>
                                    @endif
                                </div>
                            </div>
                            {{-- Detalle de medios (incl. el dividido) + comprobantes --}}
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($c['medios'] as $m)
                                    @php $mc = $m['medio'] === 'efectivo' ? 'bg-green-50 text-green-700 border-green-200' : ($m['medio'] === 'transferencia' ? 'bg-sky-50 text-sky-700 border-sky-200' : 'bg-amber-50 text-amber-700 border-amber-200'); @endphp
                                    <span class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-[11px] font-bold {{ $mc }}">
                                        {{ $m['medio_label'] }} {{ $money2($m['monto']) }}
                                        @if ($m['banco'])<span class="font-normal">· {{ $m['banco'] }}</span>@endif
                                        @if ($m['cheque_numero'])<span class="font-normal">· N° {{ $m['cheque_numero'] }}</span>@endif
                                        @if ($m['comprobante'])<a href="{{ $m['comprobante'] }}" target="_blank" class="inline-flex items-center gap-0.5 underline"><span class="material-symbols-outlined text-[13px]">image</span> comprobante</a>@endif
                                        @if ($m['conciliado'])<span class="material-symbols-outlined text-[14px] text-green-600" title="Confirmado">check</span>
                                        @elseif ($m['no_rendido'])<span class="material-symbols-outlined text-[14px] text-red-600" title="No rendido">report</span>@endif
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="px-4 py-10 text-center text-sm text-muted"><span class="material-symbols-outlined mb-1 block text-3xl">receipt_long</span> No hay cobros en esta fecha@if ($filtroCobradorId) para este cobrador@endif.</p>
                    @endforelse
                </div>
            </x-panel>
        </div>
    @endif

    {{-- ================= RENDICIÓN (Tesorería) ================= --}}
    @if ($tab === 'rendicion')
        <div class="space-y-5">
            {{-- Fecha + contexto --}}
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase text-muted">Fecha de rendición</label>
                    <input type="date" wire:model.live="rendFecha" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
                </div>
                <p class="text-xs text-muted">Elegí el <b>cobrador</b> en el filtro de arriba para rendir su efectivo. Transferencias y cheques se concilian uno por uno.</p>
            </div>

            @if ($rendicion)
                @php
                    $ef = $rendicion['efectivo'];
                    $tr = $rendicion['transferencia'];
                    $ch = $rendicion['cheque'];
                    $esp = $ef['esperado_pendiente'];
                    $rec = $rendRecibido !== '' ? (float) $rendRecibido : null;
                    $dif = $rec === null ? null : round($rec - $esp, 2);
                @endphp

                {{-- ===== EFECTIVO ===== --}}
                <x-panel>
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-4">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-green-100 text-green-700"><span class="material-symbols-outlined">payments</span></span>
                            <div><p class="text-base font-extrabold text-ink">Efectivo</p><p class="text-[11px] text-muted">{{ $ef['cant'] }} cobro(s) · total {{ $money2($ef['total']) }}</p></div>
                        </div>
                        <div class="flex gap-4 text-right">
                            <div><p class="text-[11px] font-bold uppercase text-muted">A rendir</p><p class="tabular text-lg font-extrabold text-ink">{{ $money2($esp) }}</p></div>
                            <div><p class="text-[11px] font-bold uppercase text-muted">Ya rendido</p><p class="tabular text-lg font-extrabold text-green-600">{{ $money2($ef['ya_rendido']) }}</p></div>
                            @if (($ef['no_rendido'] ?? 0) > 0)
                                <div><p class="text-[11px] font-bold uppercase text-muted">No rendido</p><p class="tabular text-lg font-extrabold text-red-600">{{ $money2($ef['no_rendido']) }}</p></div>
                            @endif
                        </div>
                    </div>

                    @if (! $filtroCobradorId)
                        <p class="px-4 py-6 text-center text-sm text-muted"><span class="material-symbols-outlined mb-1 block text-2xl">person_search</span> Elegí un cobrador arriba para registrar su rendición de efectivo.</p>
                    @else
                        @if ($esp <= 0)
                            <p class="px-4 pt-4 text-center text-sm text-muted"><span class="material-symbols-outlined mb-1 block text-2xl text-green-500">task_alt</span> No hay efectivo pendiente de rendir para este cobrador en la fecha.</p>
                        @else
                        {{-- Formulario de rendición --}}
                        <div class="grid grid-cols-1 gap-3 border-b border-gray-100 p-4 sm:grid-cols-4">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-graphite">Efectivo recibido</label>
                                <div class="relative"><span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted">$</span>
                                    <input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="rendRecibido" placeholder="{{ number_format($esp, 2, '.', '') }}" class="w-full rounded-lg border border-gray-200 py-2 pl-7 pr-3 text-sm outline-none focus:border-brand" /></div>
                                @error('rendRecibido') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-graphite">Diferencia</label>
                                <div class="rounded-lg border px-3 py-2 text-sm font-bold {{ $dif === null ? 'border-gray-200 text-muted' : ($dif < 0 ? 'border-red-200 bg-red-50 text-red-700' : ($dif > 0 ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-green-200 bg-green-50 text-green-700')) }}">
                                    {{ $dif === null ? '—' : ($dif < 0 ? 'Falta ' . $money2(abs($dif)) : ($dif > 0 ? 'Sobra ' . $money2($dif) : 'Cuadra ✔')) }}
                                </div>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-semibold text-graphite">Nota (opcional)</label>
                                <div class="flex gap-2">
                                    <input type="text" wire:model="rendNota" placeholder="Observación de la rendición…" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
                                    <button type="button" wire:click="rendirEfectivo" wire:confirm="¿Registrar la rendición? Se marcarán los cobros en efectivo como rendidos y se ajustará la caja por la diferencia." class="shrink-0 rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-dark">Rendir</button>
                                </div>
                            </div>
                        </div>
                        @endif

                        {{-- Detalle de las partes en efectivo (siempre) --}}
                        @if (! empty($ef['filas']))
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead><tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-muted"><th class="px-4 py-2.5 font-bold">Cliente</th><th class="px-4 py-2.5 font-bold">Cobrador</th><th class="px-4 py-2.5 text-center font-bold">Hora</th><th class="px-4 py-2.5 text-right font-bold">Monto</th><th class="px-4 py-2.5 text-center font-bold">Estado</th><th class="px-4 py-2.5"></th></tr></thead>
                                <tbody>
                                    @foreach ($ef['filas'] as $r)
                                        <tr class="border-t border-gray-100 {{ $r['no_rendido'] ? 'bg-red-50/40' : ($r['conciliado'] ? 'bg-green-50/40' : '') }}">
                                            <td class="px-4 py-2.5 font-semibold text-ink">{{ $r['cliente'] }}</td>
                                            <td class="px-4 py-2.5 text-graphite">{{ $r['cobrador'] }}</td>
                                            <td class="px-4 py-2.5 text-center text-muted">{{ $r['hora'] }}</td>
                                            <td class="px-4 py-2.5 text-right tabular font-bold text-ink">{{ $money2($r['monto']) }}</td>
                                            <td class="px-4 py-2.5 text-center">
                                                @if ($r['no_rendido'])<span class="rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-bold text-red-700" title="{{ $r['motivo'] }}">No rendido</span>
                                                @elseif ($r['conciliado'])<span class="rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-bold text-green-700">Rendido</span>
                                                @else<span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-700">Pendiente</span>@endif
                                            </td>
                                            <td class="px-4 py-2.5 text-right">
                                                @if (! $r['conciliado'] && ! $r['no_rendido'])
                                                    <button type="button" wire:click="pedirNoRendido({{ $r['id'] }})" class="inline-flex items-center gap-1 rounded-lg border border-red-200 px-2.5 py-1 text-[11px] font-bold text-red-700 hover:bg-red-50" title="El cobrador no entregó este efectivo (robo/pérdida)"><span class="material-symbols-outlined text-[14px]">report</span> No rendido</button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    @endif

                    {{-- Historial de rendiciones del día --}}
                    @if ($rendicion['rendiciones']->isNotEmpty())
                        <div class="border-t border-gray-100 bg-gray-50/60 px-4 py-3">
                            <p class="mb-2 text-[11px] font-bold uppercase tracking-wide text-muted">Rendiciones registradas</p>
                            @foreach ($rendicion['rendiciones'] as $rd)
                                <div class="flex flex-wrap items-center justify-between gap-2 py-1 text-xs">
                                    <span class="text-graphite">{{ $rd->created_at?->format('H:i') }} · {{ $rd->registrador?->name ?? '—' }} · esperado {{ $money2($rd->total_esperado) }} · recibido {{ $money2($rd->total_recibido) }}</span>
                                    <span class="font-bold {{ (float) $rd->diferencia < 0 ? 'text-red-600' : ((float) $rd->diferencia > 0 ? 'text-amber-600' : 'text-green-600') }}">
                                        {{ (float) $rd->diferencia < 0 ? 'Faltó ' . $money2(abs($rd->diferencia)) : ((float) $rd->diferencia > 0 ? 'Sobró ' . $money2($rd->diferencia) : 'Cuadró') }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-panel>

                {{-- ===== TRANSFERENCIAS y CHEQUES ===== --}}
                @foreach ([['transferencia', $tr, 'Transferencias', 'swap_horiz', 'Confirmar en banco'], ['cheque', $ch, 'Cheques', 'account_balance', 'Ingresar a cartera']] as [$medioKey, $data, $titulo, $icono, $accion])
                    <x-panel>
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-4">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-100 text-sky-700"><span class="material-symbols-outlined">{{ $icono }}</span></span>
                                <div><p class="text-base font-extrabold text-ink">{{ $titulo }}</p><p class="text-[11px] text-muted">{{ $data['cant'] }} · total {{ $money2($data['total']) }}</p></div>
                            </div>
                            <div class="flex items-center gap-4 text-right">
                                <div><p class="text-[11px] font-bold uppercase text-muted">Pendiente</p><p class="tabular text-lg font-extrabold text-amber-600">{{ $money2($data['pendiente']) }}</p></div>
                                @if ($data['pendiente'] > 0)
                                    <button type="button" wire:click="conciliarMedio('{{ $medioKey }}')" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-bold text-graphite hover:bg-gray-100">Conciliar todas</button>
                                @endif
                            </div>
                        </div>
                        @if (empty($data['filas']))
                            <p class="px-4 py-6 text-center text-sm text-muted">Sin {{ mb_strtolower($titulo) }} en la fecha.</p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead><tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-muted"><th class="px-4 py-2.5 font-bold">Cliente</th><th class="px-4 py-2.5 font-bold">Detalle</th><th class="px-4 py-2.5 text-right font-bold">Monto</th><th class="px-4 py-2.5 text-center font-bold">Estado</th></tr></thead>
                                    <tbody>
                                        @foreach ($data['filas'] as $r)
                                            <tr class="border-t border-gray-100 {{ $r['conciliado'] ? 'bg-green-50/40' : '' }}">
                                                <td class="px-4 py-2.5 font-semibold text-ink">{{ $r['cliente'] }}<p class="text-[11px] font-normal text-muted">{{ $r['cobrador'] }} · {{ $r['hora'] }}</p></td>
                                                <td class="px-4 py-2.5 text-graphite">
                                                    @if ($r['banco']){{ $r['banco'] }}@endif @if ($r['cheque_numero'])· N° {{ $r['cheque_numero'] }}@endif
                                                    @if ($r['comprobante'])<a href="{{ $r['comprobante'] }}" target="_blank" class="ml-1 inline-flex items-center gap-0.5 text-[11px] font-bold text-brand hover:underline"><span class="material-symbols-outlined text-[14px]">image</span> comprobante</a>@endif
                                                </td>
                                                <td class="px-4 py-2.5 text-right tabular font-bold text-ink">{{ $money2($r['monto']) }}</td>
                                                <td class="px-4 py-2.5 text-center">
                                                    @if ($r['conciliado'])
                                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold text-green-600"><span class="material-symbols-outlined text-[16px]">check_circle</span> Conciliado</span>
                                                    @else
                                                        <button type="button" wire:click="conciliarParte({{ $r['id'] }})" class="inline-flex items-center gap-1 rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-sky-700"><span class="material-symbols-outlined text-[14px]">done</span> {{ $accion }}</button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </x-panel>
                @endforeach
            @endif
        </div>
    @endif

    {{-- ================= INCOBRABLES ================= --}}
    @if ($tab === 'incobrables')
        <x-panel title="Créditos incobrables">
            <x-slot:actions>
                <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-700">{{ $incobrables->count() }} crédito(s)</span>
            </x-slot:actions>
            <p class="px-5 pt-4 text-xs text-muted">Créditos que superaron el <b>umbral de cuotas vencidas de su plan</b> (Configuración → Productos de crédito). <b>Ya no aparecen en la planilla del cobrador</b> (no se visitan más). El umbral es por tipo de plan.</p>
            <div class="overflow-x-auto p-5">
                <table class="w-full text-left text-sm">
                    <thead><tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-muted">
                        <th class="px-3 py-2.5 font-bold">Cliente</th>
                        <th class="px-3 py-2.5 font-bold">Crédito · Plan</th>
                        <th class="px-3 py-2.5 font-bold">Zona / Cobrador</th>
                        <th class="px-3 py-2.5 text-center font-bold">Cuotas vencidas</th>
                        <th class="px-3 py-2.5 text-right font-bold">Deuda</th>
                    </tr></thead>
                    <tbody>
                        @forelse ($incobrables as $r)
                            <tr class="border-t border-gray-100">
                                <td class="px-3 py-2.5">
                                    <p class="flex items-center gap-1.5 font-bold text-ink"><span class="h-2.5 w-2.5 rounded-full bg-red-500"></span> {{ $r['cliente'] }}</p>
                                    <p class="text-[11px] text-muted">{{ $r['domicilio'] ?: '—' }} @if ($r['telefono'])· {{ $r['telefono'] }}@endif</p>
                                </td>
                                <td class="px-3 py-2.5"><p class="font-semibold text-graphite">{{ $r['venta'] }}</p><p class="text-[11px] text-muted">{{ $r['plan'] }}</p></td>
                                <td class="px-3 py-2.5 text-graphite">{{ $r['zona'] }}<p class="text-[11px] text-muted">{{ $r['cobrador'] }}</p></td>
                                <td class="px-3 py-2.5 text-center"><span class="rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-bold text-red-700">{{ $r['vencidas'] }}</span> <span class="text-[11px] text-muted">/ {{ $r['umbral'] }}</span></td>
                                <td class="px-3 py-2.5 text-right tabular font-bold text-ink">{{ $money2($r['deuda']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-12 text-center text-sm text-muted"><span class="material-symbols-outlined mb-1 block text-3xl text-green-500">verified_user</span> No hay créditos incobrables con los filtros actuales.</td></tr>
                        @endforelse
                    </tbody>
                </table>
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

    {{-- ===== Modal "Cobro no rendido / robado" ===== --}}
    @if ($noRendMedioId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 class="flex items-center gap-2 text-base font-extrabold text-ink"><span class="material-symbols-outlined text-red-600">report</span> Cobro no rendido</h3>
                    <button wire:click="cerrarNoRendido" class="text-muted hover:text-ink"><span class="material-symbols-outlined">close</span></button>
                </div>
                <div class="space-y-3 p-5">
                    <p class="text-sm text-muted">Marcá este cobro como <b>no rendido</b> (el cobrador no entregó el efectivo: robo/pérdida). <b>El cliente no se afecta</b> (pagó, tiene recibo): se <b>revierte el ingreso en caja</b> y se le <b>carga el importe al cobrador</b> en su cuenta.</p>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-graphite">Motivo</label>
                        <input type="text" wire:model="noRendMotivo" placeholder="Ej: robo denunciado, extravío…" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
                        @error('noRendMotivo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-4">
                    <button wire:click="cerrarNoRendido" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-bold text-graphite hover:bg-gray-100">Cancelar</button>
                    <button wire:click="marcarNoRendido" class="inline-flex items-center gap-1 rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700"><span class="material-symbols-outlined text-[18px]">report</span> Marcar no rendido</button>
                </div>
            </div>
        </div>
    @endif

</div>
