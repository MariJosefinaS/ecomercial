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
                    <div class="min-w-0"><p class="text-[11px] font-bold uppercase text-muted">Último acceso</p><p class="truncate text-sm font-semibold text-ink">{{ $u->ultimo_acceso ? \Illuminate\Support\Carbon::parse($u->ultimo_acceso)->format('d/m/Y H:i') : '—' }}</p></div>
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
                    <p class="text-[11px] font-bold uppercase text-muted">Cobrado hoy</p>
                    <p class="tabular mt-1 text-lg font-extrabold text-ink">{{ $money($stats['cobradoHoy']) }}</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-soft">
                    <p class="text-[11px] font-bold uppercase text-muted">Cobrado en el mes</p>
                    <p class="tabular mt-1 text-lg font-extrabold text-green-600">{{ $money($stats['cobradoMes']) }}</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-soft">
                    <p class="text-[11px] font-bold uppercase text-muted">Pagos del mes</p>
                    <p class="tabular mt-1 text-lg font-extrabold text-ink">{{ $stats['pagosMes'] }}</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-soft">
                    <p class="text-[11px] font-bold uppercase text-muted">Clientes en cartera</p>
                    <p class="tabular mt-1 text-lg font-extrabold text-ink">{{ $stats['clientes'] }}</p>
                </div>
                <div class="rounded-2xl border border-brand/20 bg-brand-soft/40 p-4 shadow-soft">
                    <p class="text-[11px] font-bold uppercase text-brand">Comisión estimada</p>
                    @if ($stats['comisionPct'] === null)
                        <p class="mt-1 text-lg font-extrabold text-muted">—</p>
                    @else
                        <p class="tabular mt-1 text-lg font-extrabold text-brand">{{ $stats['comisionPct'] }}%</p>
                        <p class="text-[11px] text-muted">≈ {{ $money($stats['cobradoMes'] * $stats['comisionPct'] / 100) }}</p>
                    @endif
                </div>
            </div>

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
    @endif
</div>
