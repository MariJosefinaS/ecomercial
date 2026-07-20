<div class="space-y-6">

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-ink">Reposición — Lote óptimo (EOQ)</h1>
            <p class="text-sm text-muted">Cuánto y cuándo reponer cada producto, calculado con tu demanda real de los últimos {{ $spanMeses }} mes(es).</p>
        </div>
        <div class="flex items-center gap-2">
            <label class="text-xs font-bold uppercase tracking-wide text-muted">Histórico</label>
            <select wire:model.live="meses" class="rounded-lg border border-gray-200 py-2 pl-3 pr-8 text-sm font-medium text-ink outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                <option value="3">Últimos 3 meses</option>
                <option value="6">Últimos 6 meses</option>
                <option value="12">Últimos 12 meses</option>
                <option value="todo">Todo el histórico</option>
            </select>
        </div>
    </div>

    @if ($mensaje)
        <div class="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm font-semibold text-green-700">
            <span class="material-symbols-outlined text-[18px]">check_circle</span> {{ $mensaje }}
        </div>
    @endif

    {{-- KPIs --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-kpi-card variant="brand" title="Productos a reponer" :value="$stats['a_reponer']" icon="notification_important" />
        <x-kpi-card variant="blue" title="Unidades sugeridas (lotes)" :value="number_format($stats['unidades_sugeridas'], 0, ',', '.')" icon="inventory" />
        <x-kpi-card variant="beige" title="Costo anual estimado de la política" :value="'$' . number_format($stats['costo_total'], 0, ',', '.')" icon="savings" />
    </div>

    {{-- Parámetros del modelo --}}
    <x-panel title="Parámetros del modelo">
        <x-slot:actions>
            <button wire:click="guardarParametros" class="flex items-center gap-1.5 rounded-lg bg-brand px-3 py-1.5 text-xs font-bold text-white transition hover:bg-brand-dark">
                <span class="material-symbols-outlined text-[16px]">save</span> Guardar
            </button>
        </x-slot:actions>
        <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-muted">Costo de emitir un pedido (S)</label>
                <p class="mb-2 text-xs text-muted">Flete + administrativo por orden de compra.</p>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted">$</span>
                    <input type="number" min="0" step="100" wire:model.live.debounce.500ms="costoPedido" class="w-full rounded-lg border border-gray-200 py-2 pl-7 pr-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-muted">Tasa de mantenimiento anual (i)</label>
                <p class="mb-2 text-xs text-muted">% del valor inmovilizado (capital, depósito, seguro).</p>
                <div class="relative">
                    <input type="number" min="0" step="1" wire:model.live.debounce.500ms="tasaMantenimiento" class="w-full rounded-lg border border-gray-200 py-2 pl-3 pr-7 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-muted">%</span>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-muted">Nivel de servicio</label>
                <p class="mb-2 text-xs text-muted">Probabilidad de no quedar sin stock (define el stock de seguridad).</p>
                <select wire:model.live="nivelServicio" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                    <option value="90">90%</option>
                    <option value="95">95%</option>
                    <option value="97">97%</option>
                    <option value="99">99%</option>
                </select>
            </div>
        </div>
        <p class="border-t border-gray-100 px-5 py-3 text-xs text-muted">
            <b>EOQ = √(2·D·S / H)</b>, con D = demanda anual (de tus ventas), H = i · costo de compra. El lead time sale de los <b>días de entrega</b> de cada proveedor.
        </p>
    </x-panel>

    {{-- Tabla EOQ por producto --}}
    <x-panel title="Lote óptimo por producto">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-[11px] uppercase tracking-wide text-muted">
                        <th class="px-5 py-3 font-bold">Producto</th>
                        <th class="px-3 py-3 text-right font-bold" title="Demanda anual estimada">Demanda/año</th>
                        <th class="px-3 py-3 text-right font-bold">Stock</th>
                        <th class="px-3 py-3 text-right font-bold" title="Lote económico de pedido">EOQ</th>
                        <th class="px-3 py-3 text-right font-bold" title="Punto de reposición">Reponer en</th>
                        <th class="px-3 py-3 text-right font-bold" title="Stock de seguridad">SS</th>
                        <th class="px-3 py-3 text-right font-bold" title="Frecuencia de pedido">Cada</th>
                        <th class="px-3 py-3 text-right font-bold" title="Costo total anual de la política">Costo/año</th>
                        <th class="px-5 py-3 text-right font-bold">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($filas as $f)
                        <tr class="border-t border-gray-100 hover:bg-brand-soft/30" wire:key="eoq-{{ $f['id'] }}">
                            <td class="px-5 py-3">
                                <p class="font-bold text-ink">{{ $f['nom'] }}</p>
                                <p class="text-xs text-muted">{{ $f['cod'] }} · {{ $f['prov'] }} · entrega {{ $f['lead'] }}d</p>
                            </td>
                            @if ($f['sin_demanda'])
                                <td colspan="6" class="px-3 py-3 text-center text-xs italic text-muted">Sin ventas en el período — no se puede calcular EOQ</td>
                            @else
                                <td class="px-3 py-3 text-right tabular-nums text-graphite">{{ number_format($f['demanda_anual'], 0, ',', '.') }}</td>
                                <td class="px-3 py-3 text-right tabular-nums font-semibold {{ $f['reponer_ahora'] ? 'text-danger' : 'text-ink' }}">{{ $f['stock_actual'] }}</td>
                                <td class="px-3 py-3 text-right tabular-nums font-extrabold text-brand">{{ $f['eoq'] }}</td>
                                <td class="px-3 py-3 text-right tabular-nums text-graphite">{{ $f['punto_reposicion'] }}</td>
                                <td class="px-3 py-3 text-right tabular-nums text-graphite">{{ $f['stock_seguridad'] }}</td>
                                <td class="px-3 py-3 text-right tabular-nums text-graphite">{{ $f['dias_entre_pedidos'] }}d</td>
                                <td class="px-3 py-3 text-right tabular-nums text-graphite">${{ number_format($f['costo_total_anual'], 0, ',', '.') }}</td>
                            @endif
                            <td class="px-5 py-3 text-right">
                                @if ($f['reponer_ahora'])
                                    <button wire:click="solicitar({{ $f['id'] }}, {{ $f['sugerido_pedir'] }})"
                                            class="inline-flex items-center gap-1 rounded-lg bg-brand px-3 py-1.5 text-xs font-bold text-white transition hover:bg-brand-dark">
                                        <span class="material-symbols-outlined text-[16px]">add_shopping_cart</span> Pedir {{ $f['sugerido_pedir'] }}
                                    </button>
                                @elseif (! $f['sin_demanda'])
                                    <span class="inline-flex items-center gap-1 text-xs font-bold text-green-700"><span class="material-symbols-outlined text-[16px]">check_circle</span> OK</span>
                                @else
                                    <span class="text-xs text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-5 py-6 text-center text-sm text-muted">No hay productos activos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="border-t border-gray-100 px-5 py-3 text-xs text-muted">
            <b>Reponer en</b> = punto de reposición (stock que dispara un nuevo pedido). Cuando el stock cae a ese nivel, conviene pedir un lote de <b>EOQ</b> unidades. <b>SS</b> = stock de seguridad para el nivel de servicio elegido.
        </p>
    </x-panel>
</div>
