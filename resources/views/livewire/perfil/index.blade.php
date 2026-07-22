<div class="space-y-6">
    @php
        $money = fn ($n) => '$' . number_format((float) $n, 2, ',', '.');
        $iniciales = collect(explode(' ', $u->name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
        $avatar = $u->avatar ? asset('storage/' . $u->avatar) : null;
    @endphp

    {{-- ===== Hero ===== --}}
    <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft">
        <div class="h-20 bg-gradient-to-r from-anthracite to-graphite sm:h-24"></div>
        <div class="px-5 pb-5">
            <div class="-mt-10 flex flex-col gap-4 sm:-mt-12 sm:flex-row sm:items-end sm:justify-between">
                <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-end">
                    @if ($avatar)
                        <img src="{{ $avatar }}" alt="{{ $u->name }}" class="h-20 w-20 rounded-2xl border-4 border-white object-cover shadow-md sm:h-24 sm:w-24" />
                    @else
                        <div class="flex h-20 w-20 items-center justify-center rounded-2xl border-4 border-white bg-brand text-2xl font-extrabold text-white shadow-md sm:h-24 sm:w-24">{{ $iniciales ?: 'U' }}</div>
                    @endif
                    <div class="pb-1">
                        <h1 class="text-2xl font-extrabold text-ink">{{ $u->name }}</h1>
                        <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-brand-soft px-3 py-0.5 text-xs font-bold uppercase tracking-wide text-brand">
                            <span class="material-symbols-outlined text-[15px]">badge</span> {{ $rolLabel }}
                        </span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($esCobrador)
                        <a href="{{ route('cobranza.planilla') }}" wire:navigate class="inline-flex items-center gap-1.5 rounded-xl bg-brand px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-dark">
                            <span class="material-symbols-outlined text-[18px]">receipt_long</span> Mi planilla
                        </a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-bold text-danger transition hover:bg-red-100">
                            <span class="material-symbols-outlined text-[18px]">logout</span> Cerrar sesión
                        </button>
                    </form>
                </div>
            </div>

            {{-- Datos de contacto --}}
            <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="flex items-center gap-2.5 rounded-xl border border-gray-100 bg-gray-50 px-3 py-2.5">
                    <span class="material-symbols-outlined text-[20px] text-muted">mail</span>
                    <div class="min-w-0"><p class="text-[11px] font-bold uppercase text-muted">Email</p><p class="truncate text-sm font-semibold text-ink">{{ $u->email ?: '—' }}</p></div>
                </div>
                <div class="flex items-center gap-2.5 rounded-xl border border-gray-100 bg-gray-50 px-3 py-2.5">
                    <span class="material-symbols-outlined text-[20px] text-muted">call</span>
                    <div class="min-w-0"><p class="text-[11px] font-bold uppercase text-muted">Teléfono</p><p class="truncate text-sm font-semibold text-ink">{{ $u->telefono ?: '—' }}</p></div>
                </div>
                <div class="flex items-center gap-2.5 rounded-xl border border-gray-100 bg-gray-50 px-3 py-2.5">
                    <span class="material-symbols-outlined text-[20px] text-muted">schedule</span>
                    <div class="min-w-0">
                        <p class="text-[11px] font-bold uppercase text-muted">Acceso anterior</p>
                        <p class="truncate text-sm font-semibold text-ink">{{ $u->acceso_previo ? \Illuminate\Support\Carbon::parse($u->acceso_previo)->format('d/m/Y H:i') : 'Primer ingreso' }}</p>
                        @if ($u->ultimo_acceso)<p class="truncate text-[11px] text-muted">Sesión actual: {{ \Illuminate\Support\Carbon::parse($u->ultimo_acceso)->format('d/m/Y H:i') }}</p>@endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Estadísticas de cobranza (solo cobradores) ===== --}}
    @if ($esCobrador && $stats)
        <div>
            <h2 class="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-muted">
                <span class="material-symbols-outlined text-[18px] text-brand">insights</span> Mi cobranza — {{ ucfirst(\Illuminate\Support\Carbon::now()->locale('es')->isoFormat('MMMM YYYY')) }}
            </h2>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                {{-- Eficacia (destacada) --}}
                <div class="col-span-2 rounded-2xl border border-gray-100 bg-white p-4 shadow-soft sm:col-span-1">
                    <p class="text-[11px] font-bold uppercase text-muted">Eficacia del mes</p>
                    @if ($stats['eficacia'] === null)
                        <p class="mt-1 text-2xl font-extrabold text-muted">—</p>
                        <p class="text-[11px] text-muted">Sin planillas cerradas</p>
                    @else
                        <p class="mt-1 text-3xl font-extrabold {{ $stats['eficacia'] >= 90 ? 'text-green-600' : ($stats['eficacia'] >= 85 ? 'text-amber-600' : 'text-red-600') }}">{{ number_format($stats['eficacia'], 1, ',', '.') }}%</p>
                        <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full {{ $stats['eficacia'] >= 90 ? 'bg-green-500' : ($stats['eficacia'] >= 85 ? 'bg-amber-500' : 'bg-red-500') }}" style="width: {{ min(100, $stats['eficacia']) }}%"></div>
                        </div>
                    @endif
                </div>

                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-soft">
                    <p class="text-[11px] font-bold uppercase text-muted">Confirmado hoy</p>
                    <p class="tabular mt-1 text-lg font-extrabold text-ink">{{ $money($stats['confirmadoHoy']) }}</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-soft">
                    <p class="text-[11px] font-bold uppercase text-muted">Confirmado en el mes</p>
                    <p class="tabular mt-1 text-lg font-extrabold text-green-600">{{ $money($stats['confirmadoMes']) }}</p>
                    <p class="text-[11px] text-muted">Validado por Tesorería</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-soft">
                    <p class="text-[11px] font-bold uppercase text-muted">Pendiente de confirmar</p>
                    <p class="tabular mt-1 text-lg font-extrabold {{ $stats['pendienteMes'] > 0 ? 'text-amber-600' : 'text-muted' }}">{{ $money($stats['pendienteMes']) }}</p>
                    <p class="text-[11px] text-muted">Aún no cuenta</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-soft">
                    <p class="text-[11px] font-bold uppercase text-muted">Clientes en cartera</p>
                    <p class="tabular mt-1 text-lg font-extrabold text-ink">{{ $stats['clientes'] }}</p>
                </div>
                {{-- Saldo a cobrar (de tu cuenta): comisión devengada − pagos recibidos --}}
                <div class="col-span-2 rounded-2xl border border-brand/30 bg-brand-soft/40 p-4 shadow-soft sm:col-span-1">
                    <p class="text-[11px] font-bold uppercase text-brand">Saldo a cobrar</p>
                    <p class="tabular mt-1 text-2xl font-extrabold {{ $cuenta['saldo'] >= 0 ? 'text-brand' : 'text-red-600' }}">{{ $money($cuenta['saldo']) }}</p>
                    <p class="text-[11px] text-muted">
                        Comisión {{ rtrim(rtrim(number_format($stats['comisionPct'], 2, ',', '.'), '0'), ',') }}%
                        <span class="font-bold {{ $stats['comisionPropia'] ? 'text-brand' : 'text-muted' }}">· {{ $stats['comisionPropia'] ? 'tuyo' : 'general' }}</span>
                    </p>
                </div>
            </div>

            <p class="mt-2 text-[11px] text-muted"><span class="material-symbols-outlined align-middle text-[14px]">info</span> Tus cobros generan comisión <b>recién cuando Tesorería confirma la rendición</b>. Lo pendiente de confirmar todavía no suma.</p>

            {{-- Zonas asignadas --}}
            <div class="mt-4 rounded-2xl border border-gray-100 bg-white p-4 shadow-soft">
                <p class="mb-2 flex items-center gap-2 text-[11px] font-bold uppercase tracking-wide text-muted">
                    <span class="material-symbols-outlined text-[16px]">pin_drop</span> Zonas de cobranza asignadas
                </p>
                @if ($stats['zonas']->isEmpty())
                    <p class="text-sm text-muted">Sin zonas asignadas.</p>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach ($stats['zonas'] as $z)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 text-xs font-bold text-graphite">
                                <span class="material-symbols-outlined text-[15px] text-brand">location_on</span>
                                {{ $z->nombre }}@if ($z->local) <span class="font-normal text-muted">· {{ $z->local->nombre }}</span>@endif
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- ===== Mi cuenta / ganancias ===== --}}
        @if ($cuenta)
            <div>
                <h2 class="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-muted">
                    <span class="material-symbols-outlined text-[18px] text-brand">account_balance_wallet</span> Mi cuenta — ganancias y pagos
                </h2>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl border p-4 shadow-soft {{ $cuenta['saldo'] >= 0 ? 'border-green-100 bg-green-50/50' : 'border-red-100 bg-red-50/50' }}">
                        <p class="text-[11px] font-bold uppercase text-muted">Saldo {{ $cuenta['saldo'] < 0 ? '(pagado de más)' : 'a cobrar' }}</p>
                        <p class="tabular mt-1 text-2xl font-extrabold {{ $cuenta['saldo'] >= 0 ? 'text-green-700' : 'text-red-600' }}">{{ $money($cuenta['saldo']) }}</p>
                    </div>
                    <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-soft"><p class="text-[11px] font-bold uppercase text-muted">Comisión devengada (total)</p><p class="tabular mt-1 text-lg font-extrabold text-ink">{{ $money($cuenta['devengado_total']) }}</p><p class="text-[11px] text-muted">Este mes {{ $money($cuenta['devengado_mes']) }}</p></div>
                    <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-soft"><p class="text-[11px] font-bold uppercase text-muted">Pagos recibidos (total)</p><p class="tabular mt-1 text-lg font-extrabold text-ink">{{ $money($cuenta['pagado_total']) }}</p></div>
                </div>

                {{-- Solicitar adelanto --}}
                <div class="mt-4 rounded-2xl border border-gray-100 bg-white p-4 shadow-soft">
                    <p class="mb-2 flex items-center gap-2 text-[11px] font-bold uppercase tracking-wide text-muted"><span class="material-symbols-outlined text-[16px]">savings</span> Solicitar adelanto de sueldo</p>
                    <div class="flex flex-wrap items-end gap-2">
                        <div>
                            <label class="mb-1 block text-[11px] font-semibold text-graphite">Monto</label>
                            <div class="relative"><span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted">$</span>
                                <input type="number" step="0.01" min="0" wire:model="adMonto" class="w-36 rounded-lg border border-gray-200 py-2 pl-7 pr-3 text-sm outline-none focus:border-brand" /></div>
                        </div>
                        <div class="min-w-[180px] flex-1">
                            <label class="mb-1 block text-[11px] font-semibold text-graphite">Motivo (opcional)</label>
                            <input type="text" wire:model="adMotivo" placeholder="Ej: gasto imprevisto" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
                        </div>
                        <button type="button" wire:click="solicitarAdelanto" class="rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-dark">Solicitar</button>
                    </div>
                    @error('adMonto') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    <p class="mt-1 text-[11px] text-muted">Queda <b>pendiente de aprobación del super administrador</b>. Una vez aprobado, Tesorería lo abona.</p>

                    @if ($cuenta['adelantos']->isNotEmpty())
                        <div class="mt-3 space-y-1.5">
                            @foreach ($cuenta['adelantos'] as $ad)
                                @php $badge = ['pendiente'=>'bg-amber-100 text-amber-700','aprobado'=>'bg-sky-100 text-sky-700','rechazado'=>'bg-red-100 text-red-700','pagado'=>'bg-green-100 text-green-700'][$ad->estado] ?? 'bg-gray-100 text-graphite'; @endphp
                                <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                                    <span class="text-graphite">{{ $ad->created_at?->format('d/m/Y') }} · {{ $money($ad->monto) }} @if ($ad->motivo)· {{ $ad->motivo }}@endif</span>
                                    <span class="rounded-full px-2 py-0.5 font-bold {{ $badge }}">{{ $ad->estadoLabel() }}@if ($ad->estado === 'rechazado' && $ad->motivo_rechazo) · {{ $ad->motivo_rechazo }}@endif</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Movimientos de la cuenta --}}
                <div class="mt-4 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft">
                    <p class="border-b border-gray-100 px-4 py-3 text-[11px] font-bold uppercase tracking-wide text-muted">Movimientos (comisiones y pagos)</p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead><tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-muted"><th class="px-4 py-2.5 font-bold">Fecha</th><th class="px-4 py-2.5 font-bold">Concepto</th><th class="px-4 py-2.5 text-right font-bold">Ganado (+)</th><th class="px-4 py-2.5 text-right font-bold">Pagado (−)</th></tr></thead>
                            <tbody class="tabular">
                                @forelse ($cuenta['movimientos'] as $m)
                                    <tr class="border-t border-gray-100">
                                        <td class="px-4 py-2.5 text-graphite">{{ $m->fecha?->format('d/m/Y H:i') }}</td>
                                        <td class="px-4 py-2.5 font-semibold text-ink">{{ $m->concepto }}</td>
                                        <td class="px-4 py-2.5 text-right {{ $m->tipo === 'haber' ? 'font-bold text-green-600' : 'text-gray-300' }}">{{ $m->tipo === 'haber' ? $money($m->monto) : '—' }}</td>
                                        <td class="px-4 py-2.5 text-right {{ $m->tipo === 'debe' ? 'font-bold text-red-600' : 'text-gray-300' }}">{{ $m->tipo === 'debe' ? $money($m->monto) : '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-muted">Todavía no tenés movimientos. Cobrá y esperá la confirmación de Tesorería.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
