<div class="space-y-6">
    @php $money = fn ($n) => '$' . number_format((float) $n, 2, ',', '.'); @endphp

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-ink">Autorización de pagos</h1>
            <p class="text-sm text-muted">Pedido → autoriza (jefe) → procesa (tesorero). El egreso en caja ocurre al procesar.</p>
        </div>
        <a href="{{ route('tesoreria') }}" wire:navigate class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-3 py-2 text-sm font-bold text-graphite hover:bg-gray-50"><span class="material-symbols-outlined text-[18px]">account_balance</span> Volver a Tesorería</a>
    </div>

    @if ($mensaje)
        <div class="flex flex-wrap items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm font-semibold text-green-700">
            <span class="material-symbols-outlined text-[18px]">check_circle</span>
            <span class="min-w-0 flex-1">{{ $mensaje }}</span>
            @if ($ultimoReciboPagoId)
                <a href="{{ route('tesoreria.pago.recibo', $ultimoReciboPagoId) }}" target="_blank" class="inline-flex shrink-0 items-center gap-1 rounded-lg border border-green-300 bg-white px-3 py-1.5 text-xs font-bold text-green-700 hover:bg-green-100"><span class="material-symbols-outlined text-[16px]">receipt_long</span> Recibo</a>
            @endif
        </div>
    @endif

    {{-- Totales --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-amber-100 bg-amber-50/50 p-4 shadow-soft"><p class="text-[11px] font-bold uppercase text-muted">Total pendiente</p><p class="tabular mt-1 text-xl font-extrabold text-amber-700">{{ $money($totPendiente) }}</p></div>
        <div class="rounded-2xl border border-sky-100 bg-sky-50/50 p-4 shadow-soft"><p class="text-[11px] font-bold uppercase text-muted">Total autorizado (a pagar)</p><p class="tabular mt-1 text-xl font-extrabold text-sky-700">{{ $money($totAutorizado) }}</p></div>
        <div class="rounded-2xl border border-green-100 bg-green-50/50 p-4 shadow-soft"><p class="text-[11px] font-bold uppercase text-muted">Total pagado</p><p class="tabular mt-1 text-xl font-extrabold text-green-700">{{ $money($totPagado) }}</p></div>
    </div>

    {{-- Pendientes de autorización --}}
    <x-panel title="Pendientes de autorización">
        <x-slot:actions><span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-700">{{ $pendientes->count() }}</span></x-slot:actions>
        <div class="divide-y divide-gray-100">
            @forelse ($pendientes as $p)
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-ink"><span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold uppercase text-graphite">{{ $p->tipoLabel() }}</span> {{ $p->beneficiario }} · {{ $money($p->importe) }}</p>
                        <p class="text-[11px] text-muted">{{ $p->concepto }} · {{ $p->medioLabel() }} · pidió {{ $p->solicitante?->name ?? '—' }} el {{ $p->created_at?->format('d/m/Y H:i') }}</p>
                    </div>
                    <div class="flex shrink-0 gap-1.5">
                        @if ($puedeAutorizar)
                            <button wire:click="autorizar_({{ $p->id }})" wire:confirm="¿Autorizar el pago de {{ $money($p->importe) }} a {{ $p->beneficiario }}?" class="inline-flex items-center gap-1 rounded-lg bg-green-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-green-700"><span class="material-symbols-outlined text-[14px]">check</span> Autorizar</button>
                            <button wire:click="pedirRechazo({{ $p->id }})" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-bold text-graphite hover:bg-gray-100">Rechazar</button>
                            <button wire:click="anular({{ $p->id }})" wire:confirm="¿Anular este pedido?" class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-bold text-muted hover:bg-gray-100" title="Anular"><span class="material-symbols-outlined text-[16px]">block</span></button>
                        @else
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-700">Esperando autorización</span>
                        @endif
                    </div>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-sm text-muted">No hay pedidos pendientes de autorización.</p>
            @endforelse
        </div>
    </x-panel>

    {{-- Autorizados: a procesar --}}
    <x-panel title="Autorizados · listos para procesar">
        <x-slot:actions><span class="rounded-full bg-sky-100 px-3 py-1 text-xs font-bold text-sky-700">{{ $autorizados->count() }}</span></x-slot:actions>
        <div class="divide-y divide-gray-100">
            @forelse ($autorizados as $p)
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-ink"><span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold uppercase text-graphite">{{ $p->tipoLabel() }}</span> {{ $p->beneficiario }} · {{ $money($p->importe) }}</p>
                        <p class="text-[11px] text-muted">{{ $p->concepto }} · {{ $p->medioLabel() }} · autorizó {{ $p->autorizador?->name ?? '—' }}</p>
                    </div>
                    <div class="flex shrink-0 gap-1.5">
                        @if ($puedeProcesar)
                            <button wire:click="procesar({{ $p->id }})" wire:confirm="¿Procesar el pago de {{ $money($p->importe) }}? Se registra el egreso en caja." class="inline-flex items-center gap-1 rounded-lg bg-brand px-3 py-1.5 text-xs font-bold text-white hover:bg-brand-dark"><span class="material-symbols-outlined text-[14px]">payments</span> Procesar pago</button>
                        @else
                            <span class="rounded-full bg-sky-100 px-2 py-0.5 text-[11px] font-bold text-sky-700">Autorizado</span>
                        @endif
                        @if ($puedeAutorizar)
                            <button wire:click="anular({{ $p->id }})" wire:confirm="¿Anular este pedido autorizado?" class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-bold text-muted hover:bg-gray-100" title="Anular"><span class="material-symbols-outlined text-[16px]">block</span></button>
                        @endif
                    </div>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-sm text-muted">No hay pagos autorizados pendientes de procesar.</p>
            @endforelse
        </div>
    </x-panel>

    {{-- Historial --}}
    <x-panel title="Historial reciente">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-muted"><th class="px-4 py-2.5 font-bold">Beneficiario</th><th class="px-4 py-2.5 font-bold">Concepto</th><th class="px-4 py-2.5 text-right font-bold">Importe</th><th class="px-4 py-2.5 text-center font-bold">Estado</th><th class="px-4 py-2.5 font-bold">Quién</th></tr></thead>
                <tbody>
                    @forelse ($historial as $p)
                        @php $badge = ['pagado'=>'bg-green-100 text-green-700','rechazado'=>'bg-red-100 text-red-700','anulado'=>'bg-gray-200 text-graphite'][$p->estado] ?? 'bg-gray-100 text-graphite'; @endphp
                        <tr class="border-t border-gray-100">
                            <td class="px-4 py-2.5"><span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold uppercase text-graphite">{{ $p->tipoLabel() }}</span> <span class="font-semibold text-ink">{{ $p->beneficiario }}</span></td>
                            <td class="px-4 py-2.5 text-graphite">{{ $p->concepto }}@if ($p->estado === 'rechazado' && $p->motivo_rechazo)<span class="block text-[11px] text-red-600">{{ $p->motivo_rechazo }}</span>@endif</td>
                            <td class="px-4 py-2.5 text-right tabular font-bold text-ink">{{ $money($p->importe) }}</td>
                            <td class="px-4 py-2.5 text-center"><span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $badge }}">{{ $p->estadoLabel() }}</span></td>
                            <td class="px-4 py-2.5 text-[11px] text-muted">{{ $p->estado === 'pagado' ? ($p->procesador?->name ?? '—') : ($p->autorizador?->name ?? '—') }}<br>{{ $p->updated_at?->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-muted">Sin historial todavía.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-panel>

    {{-- Modal rechazo --}}
    @if ($rechazandoId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4"><h3 class="text-base font-extrabold text-ink">Rechazar pedido de pago</h3><button wire:click="$set('rechazandoId', null)" class="text-muted hover:text-ink"><span class="material-symbols-outlined">close</span></button></div>
                <div class="p-5">
                    <label class="mb-1 block text-xs font-semibold text-graphite">Motivo (opcional)</label>
                    <input type="text" wire:model="motivoRechazo" placeholder="Motivo del rechazo…" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-4">
                    <button wire:click="$set('rechazandoId', null)" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-bold text-graphite hover:bg-gray-100">Cancelar</button>
                    <button wire:click="rechazar" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700">Rechazar</button>
                </div>
            </div>
        </div>
    @endif
</div>
