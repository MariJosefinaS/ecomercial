<div class="space-y-6">

    @php
        $money = fn ($n) => '$' . number_format((float) $n, 2, ',', '.');
        $money0 = fn ($n) => '$' . number_format((float) $n, 0, ',', '.');
        $pct = fn ($n) => rtrim(rtrim(number_format((float) $n, 1, ',', '.'), '0'), ',') . '%';
    @endphp

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-ink">Stock valorizado</h1>
            <p class="text-sm text-muted">Cuánta plata hay inmovilizada, a costo y a venta, con el margen potencial</p>
        </div>
        <button type="button" wire:click="exportarCsv" class="flex items-center gap-1.5 rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm font-bold text-graphite transition hover:bg-gray-50">
            <span class="material-symbols-outlined text-[18px]">download</span> Exportar Excel
        </button>
    </div>

    {{-- ===== KPIs ===== --}}
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi-card variant="brand" title="Valor a costo" :value="$money0($totales['valor_costo'])" icon="inventory_2"
                    :subtitle="number_format($totales['unidades'], 0, ',', '.') . ' unidades · ' . $totales['articulos'] . ' artículos'" />
        <x-kpi-card variant="blue" title="Valor a venta" :value="$money0($totales['valor_venta'])" icon="sell" subtitle="Si se vendiera todo" />
        <x-kpi-card variant="beige" title="Margen potencial" :value="$money0($totales['margen'])" icon="trending_up" :subtitle="$pct($totales['margen_pct']) . ' sobre la venta'" />
        <x-kpi-card variant="{{ $totales['sin_costo_articulos'] > 0 ? 'red' : 'white' }}" title="Sin costo cargado"
                    :value="$totales['sin_costo_articulos']" icon="help"
                    :subtitle="$totales['sin_costo_articulos'] > 0 ? 'No suman al costo: el margen queda inflado' : 'Todos los artículos tienen costo'" />
    </div>

    @if ($totales['sin_costo_articulos'] > 0)
        <div class="rounded-xl border-2 border-amber-200 bg-amber-50 p-4">
            <h3 class="mb-1 flex items-center gap-1.5 text-sm font-extrabold uppercase text-amber-700">
                <span class="material-symbols-outlined text-[18px]">warning</span> Hay artículos sin precio de compra
            </h3>
            <p class="text-sm text-amber-800">
                <b>{{ $totales['sin_costo_articulos'] }}</b> artículo(s) con stock no tienen costo cargado
                ({{ $money($totales['sin_costo_valor_venta']) }} valorizados a venta). Se cuentan con costo $0,
                así que el <b>margen real es menor</b> al que se muestra. Cargales el precio de compra en Stock.
            </p>
        </div>
    @endif

    <x-panel>
        <div class="flex flex-wrap items-center gap-1 border-b border-gray-100 px-3">
            @foreach (['detalle' => 'Detalle', 'proveedor' => 'Por proveedor', 'sucursal' => 'Por sucursal', 'categoria' => 'Por categoría'] as $t => $lbl)
                <button type="button" wire:click="setTab('{{ $t }}')" class="-mb-px border-b-2 px-4 py-3 text-sm font-bold transition {{ $tab === $t ? 'border-brand text-brand' : 'border-transparent text-graphite hover:text-brand' }}">{{ $lbl }}</button>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 px-5 py-3">
            <div class="relative min-w-[200px] flex-1">
                <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-[18px] text-muted">search</span>
                <input type="text" wire:model.live.debounce.300ms="buscar" placeholder="Buscar por nombre, código, SKU o marca..."
                       class="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
            </div>
            <select wire:model.live="filtroLocal" class="rounded-lg border border-gray-200 px-2 py-2 text-sm outline-none focus:border-brand">
                <option value="">Todas las sucursales</option>
                @foreach ($locales as $l)<option value="{{ $l->id }}">{{ $l->nombre }}</option>@endforeach
            </select>
            <select wire:model.live="filtroProveedor" class="rounded-lg border border-gray-200 px-2 py-2 text-sm outline-none focus:border-brand">
                <option value="">Todos los proveedores</option>
                @foreach ($proveedores as $p)<option value="{{ $p->id }}">{{ $p->nombre }}</option>@endforeach
            </select>
            <select wire:model.live="filtroCategoria" class="rounded-lg border border-gray-200 px-2 py-2 text-sm outline-none focus:border-brand">
                <option value="">Todas las categorías</option>
                @foreach ($categorias as $c)<option value="{{ $c->id }}">{{ $c->nombre }}</option>@endforeach
            </select>
            <label class="flex items-center gap-1.5 text-sm font-semibold text-graphite">
                <input type="checkbox" wire:model.live="soloConStock" class="h-4 w-4 rounded border-gray-300 text-brand focus:ring-brand" />
                Solo con stock
            </label>
        </div>

        {{-- ============ DETALLE producto × sucursal ============ --}}
        @if ($tab === 'detalle')
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="text-[11px] uppercase tracking-wide text-muted">
                        <th class="px-5 py-3 font-bold">Producto</th>
                        <th class="px-5 py-3 font-bold">Sucursal</th>
                        <th class="px-5 py-3 text-right font-bold">Cant.</th>
                        <th class="px-5 py-3 text-right font-bold">Costo u.</th>
                        <th class="px-5 py-3 text-right font-bold">Valor a costo</th>
                        <th class="px-5 py-3 text-right font-bold">Venta u.</th>
                        <th class="px-5 py-3 text-right font-bold">Valor a venta</th>
                        <th class="px-5 py-3 text-right font-bold">Margen</th>
                    </tr></thead>
                    <tbody class="tabular">
                        @forelse ($filas as $f)
                            <tr class="border-t border-gray-100 {{ $f['sin_costo'] ? 'bg-amber-50/60' : '' }}" wire:key="v-{{ $f['producto_id'] }}-{{ $f['local_id'] }}">
                                <td class="px-5 py-3">
                                    <p class="font-bold text-ink">{{ $f['nombre'] }}</p>
                                    <p class="text-[11px] text-muted">{{ $f['codigo'] }}@if ($f['marca']) · {{ $f['marca'] }}@endif · {{ $f['categoria'] }} · {{ $f['proveedor'] }}</p>
                                </td>
                                <td class="px-5 py-3 text-graphite">{{ $f['local'] }}</td>
                                <td class="px-5 py-3 text-right font-semibold text-ink">{{ number_format($f['cantidad'], 0, ',', '.') }}</td>
                                <td class="px-5 py-3 text-right {{ $f['sin_costo'] ? 'font-bold text-amber-700' : 'text-graphite' }}">
                                    {{ $f['sin_costo'] ? 'sin costo' : $money($f['costo_unit']) }}
                                </td>
                                <td class="px-5 py-3 text-right font-bold text-ink">{{ $money($f['valor_costo']) }}</td>
                                <td class="px-5 py-3 text-right text-graphite">{{ $money($f['venta_unit']) }}</td>
                                <td class="px-5 py-3 text-right font-bold text-ink">{{ $money($f['valor_venta']) }}</td>
                                <td class="px-5 py-3 text-right">
                                    <span class="font-bold {{ $f['margen'] >= 0 ? 'text-success' : 'text-danger' }}">{{ $money($f['margen']) }}</span>
                                    <span class="block text-[11px] text-muted">{{ $pct($f['margen_pct']) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-5 py-10 text-center text-sm text-muted">No hay stock con estos filtros.</td></tr>
                        @endforelse
                        @if (count($filas))
                            <tr class="border-t-2 border-gray-200 bg-gray-50 font-extrabold">
                                <td colspan="2" class="px-5 py-3 text-right uppercase text-muted">Totales</td>
                                <td class="px-5 py-3 text-right text-ink">{{ number_format($totales['unidades'], 0, ',', '.') }}</td>
                                <td></td>
                                <td class="px-5 py-3 text-right text-ink">{{ $money($totales['valor_costo']) }}</td>
                                <td></td>
                                <td class="px-5 py-3 text-right text-ink">{{ $money($totales['valor_venta']) }}</td>
                                <td class="px-5 py-3 text-right text-success">{{ $money($totales['margen']) }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>

        {{-- ============ AGRUPADO (proveedor / sucursal / categoría) ============ --}}
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="text-[11px] uppercase tracking-wide text-muted">
                        <th class="px-5 py-3 font-bold">{{ ['proveedor' => 'Proveedor', 'sucursal' => 'Sucursal', 'categoria' => 'Categoría'][$tab] }}</th>
                        <th class="px-5 py-3 text-right font-bold">Artículos</th>
                        <th class="px-5 py-3 text-right font-bold">Unidades</th>
                        <th class="px-5 py-3 text-right font-bold">Valor a costo</th>
                        <th class="px-5 py-3 text-right font-bold">Valor a venta</th>
                        <th class="px-5 py-3 text-right font-bold">Margen</th>
                        <th class="px-5 py-3 font-bold">Peso sobre el costo</th>
                    </tr></thead>
                    <tbody class="tabular">
                        @forelse ($grupos as $g)
                            @php $peso = $totales['valor_costo'] > 0 ? $g['valor_costo'] / $totales['valor_costo'] * 100 : 0; @endphp
                            <tr class="border-t border-gray-100" wire:key="g-{{ $tab }}-{{ $loop->index }}">
                                <td class="px-5 py-3 font-bold text-ink">{{ $g['nombre'] }}</td>
                                <td class="px-5 py-3 text-right text-graphite">{{ $g['articulos'] }}</td>
                                <td class="px-5 py-3 text-right text-graphite">{{ number_format($g['unidades'], 0, ',', '.') }}</td>
                                <td class="px-5 py-3 text-right font-bold text-ink">{{ $money($g['valor_costo']) }}</td>
                                <td class="px-5 py-3 text-right text-graphite">{{ $money($g['valor_venta']) }}</td>
                                <td class="px-5 py-3 text-right">
                                    <span class="font-bold {{ $g['margen'] >= 0 ? 'text-success' : 'text-danger' }}">{{ $money($g['margen']) }}</span>
                                    <span class="block text-[11px] text-muted">{{ $pct($g['margen_pct']) }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-28 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-brand" style="width: {{ min(100, $peso) }}%"></div></div>
                                        <span class="text-[11px] text-muted">{{ $pct($peso) }}</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-10 text-center text-sm text-muted">No hay stock con estos filtros.</td></tr>
                        @endforelse
                        @if (count($grupos))
                            <tr class="border-t-2 border-gray-200 bg-gray-50 font-extrabold">
                                <td class="px-5 py-3 text-right uppercase text-muted">Totales</td>
                                <td class="px-5 py-3 text-right text-ink">{{ $totales['articulos'] }}</td>
                                <td class="px-5 py-3 text-right text-ink">{{ number_format($totales['unidades'], 0, ',', '.') }}</td>
                                <td class="px-5 py-3 text-right text-ink">{{ $money($totales['valor_costo']) }}</td>
                                <td class="px-5 py-3 text-right text-ink">{{ $money($totales['valor_venta']) }}</td>
                                <td class="px-5 py-3 text-right text-success">{{ $money($totales['margen']) }}</td>
                                <td></td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        @endif
    </x-panel>
</div>
