<div class="space-y-6">

    @php $money = fn ($n) => '$' . number_format((float) $n, 2, ',', '.'); @endphp

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-ink">Comprobantes</h1>
            <p class="text-sm text-muted">Facturas, notas de crédito, recibos y órdenes de pago</p>
        </div>
        <p class="text-xs text-muted">
            Empresa: <b class="text-graphite">{{ $condicionEmpresa }}</b> ·
            Punto de venta <b class="text-graphite">{{ str_pad($puntoVenta, 4, '0', STR_PAD_LEFT) }}</b> ·
            IVA <b class="text-graphite">{{ rtrim(rtrim(number_format($ivaPct, 2, ',', '.'), '0'), ',') }}%</b>
        </p>
    </div>

    @if ($mensaje)
        <div class="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm font-semibold text-green-700">
            <span class="material-symbols-outlined text-[18px]">check_circle</span> {{ $mensaje }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi-card variant="brand" title="Facturado" :value="$money($totales['facturado'])" icon="receipt_long" :subtitle="$totales['cantidad'] . ' comprobante(s)'" />
        <x-kpi-card variant="white" title="Neto gravado" :value="$money($totales['neto'])" icon="functions" subtitle="Sin IVA" />
        <x-kpi-card variant="blue" title="IVA" :value="$money($totales['iva'])" icon="percent" subtitle="Del período" />
        <x-kpi-card variant="red" title="Notas de crédito" :value="$money($totales['notas_credito'])" icon="undo" subtitle="Restan del facturado" />
    </div>

    <x-panel>
        <div class="flex flex-wrap items-center gap-1 border-b border-gray-100 px-3">
            @foreach (['todos' => 'Todos', 'factura' => 'Facturas', 'nota_credito' => 'Notas de crédito', 'recibo' => 'Recibos', 'orden_pago' => 'Órdenes de pago'] as $t => $lbl)
                <button type="button" wire:click="setTab('{{ $t }}')" class="-mb-px border-b-2 px-4 py-3 text-sm font-bold transition {{ $tab === $t ? 'border-brand text-brand' : 'border-transparent text-graphite hover:text-brand' }}">{{ $lbl }}</button>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 px-5 py-3">
            <div class="relative min-w-[200px] flex-1">
                <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-[18px] text-muted">search</span>
                <input type="text" wire:model.live.debounce.300ms="buscar" placeholder="Buscar por número, concepto o nombre..."
                       class="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
            </div>
            <label class="flex items-center gap-1.5 text-xs font-bold uppercase text-muted">Desde
                <input type="date" wire:model.live="desde" class="rounded-lg border border-gray-200 px-2 py-1.5 text-sm font-normal normal-case text-ink outline-none focus:border-brand" />
            </label>
            <label class="flex items-center gap-1.5 text-xs font-bold uppercase text-muted">Hasta
                <input type="date" wire:model.live="hasta" class="rounded-lg border border-gray-200 px-2 py-1.5 text-sm font-normal normal-case text-ink outline-none focus:border-brand" />
            </label>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr class="text-[11px] uppercase tracking-wide text-muted">
                    <th class="px-5 py-3 font-bold">Comprobante</th>
                    <th class="px-5 py-3 font-bold">Fecha</th>
                    <th class="px-5 py-3 font-bold">A nombre de</th>
                    <th class="px-5 py-3 font-bold">Concepto</th>
                    <th class="px-5 py-3 text-right font-bold">Neto</th>
                    <th class="px-5 py-3 text-right font-bold">IVA</th>
                    <th class="px-5 py-3 text-right font-bold">Total</th>
                    <th class="px-5 py-3"></th>
                </tr></thead>
                <tbody class="tabular">
                    @forelse ($filas as $c)
                        <tr class="border-t border-gray-100 {{ $c->estaAnulado() ? 'opacity-50' : '' }}" wire:key="cmp-{{ $c->id }}">
                            <td class="px-5 py-3">
                                <p class="font-bold text-ink">{{ $c->etiqueta() }}</p>
                                @if ($c->estaAnulado())
                                    <span class="rounded bg-gray-200 px-1.5 py-0.5 text-[10px] font-bold uppercase text-graphite">anulado</span>
                                    @if ($c->motivo_anulacion)<span class="block text-[11px] text-muted">{{ $c->motivo_anulacion }}</span>@endif
                                @endif
                            </td>
                            <td class="px-5 py-3 text-graphite">{{ $c->fecha?->format('d/m/Y') }}
                                @if ($c->fecha_vencimiento)<span class="block text-[11px] text-muted">vto. {{ $c->fecha_vencimiento->format('d/m/Y') }}</span>@endif
                            </td>
                            <td class="px-5 py-3 font-semibold text-graphite">{{ $c->cliente?->nombre ?? $c->proveedor?->nombre ?? '—' }}</td>
                            <td class="px-5 py-3 text-graphite">{{ $c->concepto }}</td>
                            <td class="px-5 py-3 text-right text-graphite">{{ $money($c->neto) }}</td>
                            <td class="px-5 py-3 text-right text-graphite">{{ $c->iva > 0 ? $money($c->iva) : '—' }}</td>
                            <td class="px-5 py-3 text-right font-extrabold text-ink">{{ $money($c->total) }}</td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex justify-end gap-1.5">
                                    <a href="{{ route('comprobantes.pdf', $c->id) }}" target="_blank" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-bold text-graphite hover:bg-gray-50"><span class="material-symbols-outlined text-[16px]">picture_as_pdf</span> PDF</a>
                                    @puede('emitir_comprobantes')
                                        @if (! $c->estaAnulado())
                                            <button type="button" wire:click="pedirAnulacion({{ $c->id }})" class="rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-bold text-red-600 hover:bg-red-50">Anular</button>
                                        @endif
                                    @endpuede
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-10 text-center text-sm text-muted">No hay comprobantes en este período.</td></tr>
                    @endforelse
                    @if (count($filas))
                        <tr class="border-t-2 border-gray-200 bg-gray-50 font-extrabold">
                            <td colspan="4" class="px-5 py-3 text-right uppercase text-muted">Totales del período (sin anulados)</td>
                            <td class="px-5 py-3 text-right text-ink">{{ $money($totales['neto']) }}</td>
                            <td class="px-5 py-3 text-right text-ink">{{ $money($totales['iva']) }}</td>
                            <td class="px-5 py-3 text-right text-base text-ink">{{ $money($totales['total']) }}</td>
                            <td></td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </x-panel>

    {{-- ===== Modal: anular ===== --}}
    @if ($anulandoId)
        <div class="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto p-4 sm:items-center">
            <div class="absolute inset-0 bg-black/50" wire:click="$set('anulandoId', null)"></div>
            <div class="relative z-10 my-auto w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl">
                <h3 class="mb-1 text-base font-extrabold text-ink">Anular comprobante</h3>
                <p class="mb-3 text-xs text-muted">El número queda usado (no se reutiliza, como corresponde). Si el comprobante estaba imputado en una cuenta corriente, el movimiento se desvincula pero <b>no</b> se borra.</p>
                <label class="mb-1 block text-xs font-bold uppercase text-muted">Motivo</label>
                <input type="text" wire:model="motivoAnulacion" placeholder="Error de carga" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="$set('anulandoId', null)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold text-graphite hover:bg-gray-50">Cancelar</button>
                    <button type="button" wire:click="anular" class="rounded-lg bg-danger px-4 py-2 text-sm font-bold text-white hover:brightness-95">Anular</button>
                </div>
            </div>
        </div>
    @endif
</div>
