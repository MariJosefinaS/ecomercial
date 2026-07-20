<div class="space-y-6">

    {{-- Encabezado --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-ink">Reportes</h1>
            <p class="text-sm text-muted">Ranking de vendedores y desempeño de ventas</p>
        </div>
        <div class="flex items-center gap-2">
            <div class="flex overflow-hidden rounded-lg border border-gray-200 text-sm font-bold">
                @foreach (['mes' => 'Este mes', 'trimestre' => 'Trimestre', 'anio' => 'Año'] as $val => $lbl)
                    <button wire:click="$set('periodo', '{{ $val }}')"
                            class="px-3 py-2 transition {{ $periodo === $val ? 'bg-brand text-white' : 'text-graphite hover:bg-gray-50' }}">{{ $lbl }}</button>
                @endforeach
            </div>
            <a href="#" class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm font-bold text-graphite transition hover:border-brand hover:text-brand">
                <span class="material-symbols-outlined text-[18px]">download</span> Exportar
            </a>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi-card variant="brand" title="Ventas Totales" :value="'$' . number_format($stats['monto'], 0, ',', '.')" icon="payments" />
        <x-kpi-card variant="white" title="Unidades" :value="number_format($stats['unidades'], 0, ',', '.')" icon="inventory_2" subtitle="Vendidas" />
        <x-kpi-card variant="blue"  title="Operaciones" :value="$stats['operaciones']" icon="receipt_long" subtitle="Ventas cerradas" />
        <x-kpi-card variant="beige" title="Ticket Promedio" :value="'$' . number_format($stats['ticket'], 0, ',', '.')" icon="sell" />
    </div>

    {{-- ===== Ranking de vendedores ===== --}}
    @if ($sub === 'ranking')
        <x-panel title="Ranking de Vendedores">
            <div class="space-y-4 p-5">
                @foreach ($ranking as $idx => $v)
                    <div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-extrabold {{ $idx === 0 ? 'bg-brand text-white' : 'bg-gray-100 text-graphite' }}">{{ $idx + 1 }}</span>
                                <x-avatar :initials="$v['ini']" :variant="$v['vv']" size="sm" />
                                <span class="text-sm font-bold text-ink">{{ $v['nom'] }}</span>
                            </div>
                            <div class="text-right leading-tight">
                                <p class="tabular text-sm font-extrabold text-ink">${{ number_format($v['total'], 0, ',', '.') }}</p>
                                <p class="text-[11px] font-medium text-muted">{{ $v['uni'] }} unidades</p>
                            </div>
                        </div>
                        <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full {{ $idx === 0 ? 'bg-brand' : 'bg-graphite' }}" style="width: {{ round($v['total'] / $maxRank * 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-panel>
    @endif

    {{-- ===== Ventas por local ===== --}}
    @if ($sub === 'locales')
        <x-panel title="Ventas por Local">
            @php $totLoc = $porLocal['A'] + $porLocal['B']; @endphp
            <div class="space-y-5 p-5">
                @foreach (['A' => 'Local A', 'B' => 'Local B'] as $k => $lbl)
                    @php $pct = round($porLocal[$k] / $totLoc * 100); @endphp
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-bold text-ink">{{ $lbl }}</span>
                            <span class="tabular font-extrabold text-ink">${{ number_format($porLocal[$k], 0, ',', '.') }} <span class="text-xs font-medium text-muted">({{ $pct }}%)</span></span>
                        </div>
                        <div class="mt-1.5 h-3 w-full overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full {{ $k === 'A' ? 'bg-brand' : 'bg-graphite' }}" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                @endforeach
                <div class="rounded-xl bg-gray-50 p-4 text-center">
                    <p class="text-xs font-bold uppercase tracking-wide text-muted">Total combinado</p>
                    <p class="tabular mt-1 text-2xl font-extrabold text-ink">${{ number_format($totLoc, 0, ',', '.') }}</p>
                </div>
            </div>
        </x-panel>
    @endif

    {{-- ===== Tendencia de ventas (bar chart CSS) ===== --}}
    @if ($sub === 'tendencia')
        <x-panel title="Tendencia de Ventas">
            <div class="p-5">
                <div class="flex h-48 items-end justify-between gap-3">
                    @foreach ($tendencia as $t)
                        <div class="flex flex-1 flex-col items-center gap-2">
                            <div class="flex w-full flex-1 items-end">
                                <div class="group relative w-full rounded-t-lg bg-brand/80 transition hover:bg-brand" style="height: {{ round($t['v'] / $maxTend * 100) }}%">
                                    <span class="absolute -top-6 left-1/2 hidden -translate-x-1/2 whitespace-nowrap rounded bg-ink px-1.5 py-0.5 text-[10px] font-bold text-white group-hover:block">${{ number_format($t['v'], 0, ',', '.') }}</span>
                                </div>
                            </div>
                            <span class="text-[11px] font-bold text-muted">{{ $t['m'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-panel>
    @endif

    {{-- ===== Productos más vendidos ===== --}}
    @if ($sub === 'productos')
        <x-panel title="Productos Más Vendidos">
            <div class="divide-y divide-gray-100">
                @foreach ($topProductos as $idx => $p)
                    <div class="flex items-center gap-3 px-5 py-3">
                        <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-gray-100 text-graphite">
                            <span class="material-symbols-outlined text-[20px]">{{ $p['icon'] }}</span>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-ink">{{ $p['nom'] }}</p>
                            <p class="text-xs text-muted">{{ $p['uni'] }} unidades</p>
                        </div>
                        <span class="tabular text-sm font-extrabold text-ink">${{ number_format($p['total'], 0, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>
        </x-panel>
    @endif
</div>
