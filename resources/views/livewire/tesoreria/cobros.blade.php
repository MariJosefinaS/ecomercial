<div class="space-y-6">
    @php $money = fn ($n) => '$' . number_format((float) $n, 2, ',', '.'); @endphp

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-ink">Cobros y rendición</h1>
            <p class="text-sm text-muted">Lo que cada cobrador indicó que cobró, separado por cobrador. Confirmá la recepción y rendí el efectivo.</p>
        </div>
        <div class="flex items-end gap-3">
            <div>
                <label class="mb-1 block text-xs font-bold uppercase text-muted">Fecha</label>
                <input type="date" wire:model.live="fecha" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
            </div>
            <a href="{{ route('tesoreria') }}" wire:navigate class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-3 py-2 text-sm font-bold text-graphite hover:bg-gray-50"><span class="material-symbols-outlined text-[18px]">account_balance</span> Tesorería</a>
        </div>
    </div>

    @if ($mensaje)
        <div class="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm font-semibold text-green-700">
            <span class="material-symbols-outlined text-[18px]">check_circle</span> {{ $mensaje }}
        </div>
    @endif

    @forelse ($grupos as $g)
        @php $cob = $g['cobros']; $ef = $g['efectivo']; @endphp
        <x-panel>
            {{-- Cabecera del cobrador (planilla reducida) --}}
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-anthracite text-white"><span class="material-symbols-outlined">person</span></span>
                    <div>
                        <p class="text-base font-extrabold text-ink">{{ $g['cobrador']->name }}</p>
                        <p class="text-[11px] text-muted">{{ $cob['cant'] }} cobro(s) · total {{ $money($cob['total']) }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-4 text-right">
                    <div><p class="text-[10px] font-bold uppercase text-muted">Efectivo</p><p class="tabular text-sm font-extrabold text-green-700">{{ $money($cob['efectivo']) }}</p></div>
                    <div><p class="text-[10px] font-bold uppercase text-muted">Transfer.</p><p class="tabular text-sm font-extrabold text-sky-700">{{ $money($cob['transferencia']) }}</p></div>
                    <div><p class="text-[10px] font-bold uppercase text-muted">Cheques</p><p class="tabular text-sm font-extrabold text-amber-700">{{ $money($cob['cheque']) }}</p></div>
                    @if (($ef['esperado_pendiente'] ?? 0) > 0)
                        <button wire:click="pedirRendir({{ $g['cobrador']->id }})" class="inline-flex items-center gap-1 rounded-lg bg-brand px-3 py-1.5 text-xs font-bold text-white hover:bg-brand-dark"><span class="material-symbols-outlined text-[16px]">payments</span> Rendir efectivo ({{ $money($ef['esperado_pendiente']) }})</button>
                    @elseif (($ef['ya_rendido'] ?? 0) > 0)
                        <span class="rounded-full bg-green-100 px-2.5 py-0.5 text-[11px] font-bold text-green-700">Efectivo rendido</span>
                    @endif
                </div>
            </div>

            {{-- Cobros indicados (reducido) --}}
            <div class="divide-y divide-gray-100">
                @foreach ($cob['filas'] as $c)
                    <div class="flex flex-wrap items-start justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-ink">{{ $c['cliente'] }} <span class="font-normal text-muted">· {{ $money($c['total']) }}</span> @if ($c['dividido'])<span class="ml-1 rounded-full bg-brand-soft px-2 py-0.5 text-[10px] font-bold text-brand">Dividido</span>@endif</p>
                            <p class="text-[11px] text-muted">{{ $c['hora'] }} · Venta {{ $c['credito'] }}@if ($c['cuota']) · cuota #{{ $c['cuota'] }}@endif</p>
                            <div class="mt-1 flex flex-wrap gap-1.5">
                                @foreach ($c['medios'] as $m)
                                    @php $mc = $m['medio'] === 'efectivo' ? 'bg-green-50 text-green-700 border-green-200' : ($m['medio'] === 'transferencia' ? 'bg-sky-50 text-sky-700 border-sky-200' : 'bg-amber-50 text-amber-700 border-amber-200'); @endphp
                                    <span class="inline-flex items-center gap-1 rounded-lg border px-2 py-0.5 text-[11px] font-bold {{ $mc }}">
                                        {{ $m['medio_label'] }} {{ $money($m['monto']) }}
                                        @if ($m['banco'])<span class="font-normal">· {{ $m['banco'] }}</span>@endif
                                        @if ($m['cheque_numero'])<span class="font-normal">· N° {{ $m['cheque_numero'] }}</span>@endif
                                        @if ($m['comprobante'])<a href="{{ $m['comprobante'] }}" target="_blank" class="inline-flex items-center underline"><span class="material-symbols-outlined text-[13px]">image</span></a>@endif
                                        @if ($m['no_rendido'])<span class="material-symbols-outlined text-[13px] text-red-600" title="No rendido">report</span>@endif
                                    </span>
                                @endforeach
                            </div>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-1.5">
                            @if ($c['confirmado'])
                                <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-[11px] font-bold text-green-700"><span class="material-symbols-outlined text-[15px]">check_circle</span> Confirmado</span>
                            @else
                                <button wire:click="confirmarCobro({{ $c['id'] }})" wire:confirm="¿Confirmar que recibiste este cobro ({{ $money($c['total']) }})?" class="inline-flex items-center gap-1 rounded-lg bg-brand px-3 py-1.5 text-xs font-bold text-white hover:bg-brand-dark"><span class="material-symbols-outlined text-[15px]">verified</span> Confirmar</button>
                                @foreach ($c['medios'] as $m)
                                    @if ($m['medio'] === 'efectivo' && ! $m['conciliado'] && ! $m['no_rendido'])
                                        <button wire:click="pedirNoRendido({{ $m['id'] }})" class="text-[11px] font-bold text-red-600 hover:underline">No rendido</button>
                                    @endif
                                @endforeach
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-panel>
    @empty
        <x-panel><p class="px-4 py-12 text-center text-sm text-muted"><span class="material-symbols-outlined mb-1 block text-3xl">receipt_long</span> No hay cobros registrados en esta fecha.</p></x-panel>
    @endforelse

    {{-- Modal rendir efectivo --}}
    @if ($rendirCobradorId)
        @php $rec = $rendRecibido !== '' ? (float) $rendRecibido : null; $dif = $rec === null ? null : round($rec - $rendirEsperado, 2); @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4"><h3 class="flex items-center gap-2 text-base font-extrabold text-ink"><span class="material-symbols-outlined text-brand">payments</span> Rendir efectivo — {{ $rendirCobradorNombre }}</h3><button wire:click="cerrarRendir" class="text-muted hover:text-ink"><span class="material-symbols-outlined">close</span></button></div>
                <div class="space-y-3 p-5">
                    <div class="flex items-center justify-between text-sm"><span class="text-muted">Esperado a rendir</span><span class="tabular font-extrabold text-ink">{{ $money($rendirEsperado) }}</span></div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-graphite">Efectivo recibido</label>
                        <div class="relative"><span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted">$</span>
                            <input type="number" step="0.01" min="0" wire:model.live.debounce.300ms="rendRecibido" class="w-full rounded-lg border border-gray-200 py-2 pl-7 pr-3 text-sm outline-none focus:border-brand" /></div>
                        @error('rendRecibido') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="rounded-lg border px-3 py-2 text-sm font-bold {{ $dif === null ? 'border-gray-200 text-muted' : ($dif < 0 ? 'border-red-200 bg-red-50 text-red-700' : ($dif > 0 ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-green-200 bg-green-50 text-green-700')) }}">
                        {{ $dif === null ? 'Diferencia —' : ($dif < 0 ? 'Falta ' . $money(abs($dif)) : ($dif > 0 ? 'Sobra ' . $money($dif) : 'Cuadra ✔')) }}
                    </div>
                    <input type="text" wire:model="rendNota" placeholder="Nota (opcional)…" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-4">
                    <button wire:click="cerrarRendir" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-bold text-graphite hover:bg-gray-100">Cancelar</button>
                    <button wire:click="rendir" wire:confirm="¿Registrar la rendición? Se ajusta la caja por la diferencia." class="rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-dark">Rendir</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal no rendido --}}
    @if ($noRendMedioId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4"><h3 class="flex items-center gap-2 text-base font-extrabold text-ink"><span class="material-symbols-outlined text-red-600">report</span> Cobro no rendido</h3><button wire:click="cerrarNoRendido" class="text-muted hover:text-ink"><span class="material-symbols-outlined">close</span></button></div>
                <div class="space-y-3 p-5">
                    <p class="text-sm text-muted">El cobrador no entregó este efectivo. El cliente no se afecta; se revierte el ingreso en caja y se le carga al cobrador.</p>
                    <input type="text" wire:model="noRendMotivo" placeholder="Motivo (ej. robo, extravío)…" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
                    @error('noRendMotivo') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-4">
                    <button wire:click="cerrarNoRendido" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-bold text-graphite hover:bg-gray-100">Cancelar</button>
                    <button wire:click="marcarNoRendido" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700">Marcar no rendido</button>
                </div>
            </div>
        </div>
    @endif
</div>
