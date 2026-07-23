<div class="space-y-6">
    @php $money = fn ($n) => '$' . number_format((float) $n, 2, ',', '.'); @endphp

    {{-- Encabezado --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-ink">Pago a empleados</h1>
            <p class="text-sm text-muted">Cuenta corriente de cada cobrador: comisiones devengadas, pagos y adelantos.</p>
        </div>
        <a href="{{ route('tesoreria') }}" wire:navigate class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-3 py-2 text-sm font-bold text-graphite hover:bg-gray-50"><span class="material-symbols-outlined text-[18px]">account_balance</span> Volver a Tesorería</a>
    </div>

    @if ($mensaje)
        <div class="flex flex-wrap items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm font-semibold text-green-700">
            <span class="material-symbols-outlined text-[18px]">check_circle</span>
            <span class="min-w-0 flex-1">{{ $mensaje }}</span>
            @if ($ultimoPagoId)
                <a href="{{ route('tesoreria.pago.recibo', $ultimoPagoId) }}" target="_blank" class="inline-flex shrink-0 items-center gap-1 rounded-lg border border-green-300 bg-white px-3 py-1.5 text-xs font-bold text-green-700 hover:bg-green-100"><span class="material-symbols-outlined text-[16px]">receipt_long</span> Recibo para firmar</a>
            @endif
        </div>
    @endif

    {{-- ===== Adelantos de sueldo ===== --}}
    @if ($adelantosPend->isNotEmpty() || $adelantosAprob->isNotEmpty())
        <x-panel title="Adelantos de sueldo">
            @if ($adelantosPend->isNotEmpty())
                <div class="border-b border-gray-100 p-4">
                    <p class="mb-2 text-[11px] font-bold uppercase tracking-wide text-amber-600">Pendientes de aprobación @if (! $esSuper)<span class="font-normal text-muted">· solo el super admin aprueba</span>@endif</p>
                    <div class="space-y-2">
                        @foreach ($adelantosPend as $ad)
                            <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-amber-100 bg-amber-50/60 px-3 py-2.5">
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-ink">{{ $ad->empleado?->name ?? '—' }} · {{ $money($ad->monto) }}</p>
                                    <p class="text-[11px] text-muted">{{ $ad->motivo ?: 'Sin motivo' }} · pedido {{ $ad->created_at?->format('d/m/Y H:i') }}</p>
                                </div>
                                @if ($esSuper)
                                    <div class="flex shrink-0 gap-1.5">
                                        <button wire:click="aprobarAdelanto({{ $ad->id }})" wire:confirm="¿Aprobar el adelanto de {{ $money($ad->monto) }} para {{ $ad->empleado?->name }}?" class="inline-flex items-center gap-1 rounded-lg bg-green-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-green-700"><span class="material-symbols-outlined text-[14px]">check</span> Aprobar</button>
                                        <button wire:click="pedirRechazo({{ $ad->id }})" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-bold text-graphite hover:bg-gray-100">Rechazar</button>
                                    </div>
                                @else
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-700">Esperando super admin</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            @if ($adelantosAprob->isNotEmpty())
                <div class="p-4">
                    <p class="mb-2 text-[11px] font-bold uppercase tracking-wide text-green-600">Aprobados · listos para pagar</p>
                    <div class="space-y-2">
                        @foreach ($adelantosAprob as $ad)
                            <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-green-100 bg-green-50/60 px-3 py-2.5">
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-ink">{{ $ad->empleado?->name ?? '—' }} · {{ $money($ad->monto) }}</p>
                                    <p class="text-[11px] text-muted">Aprobado por {{ $ad->aprobador?->name ?? '—' }} · {{ $ad->aprobado_at?->format('d/m/Y') }}</p>
                                </div>
                                <button wire:click="pagarAdelanto({{ $ad->id }})" class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-brand px-3 py-1.5 text-xs font-bold text-white hover:bg-brand-dark"><span class="material-symbols-outlined text-[14px]">payments</span> Pagar adelanto</button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </x-panel>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Lista de empleados --}}
        <x-panel title="Empleados" class="lg:col-span-1">
            <div class="divide-y divide-gray-100">
                @forelse ($empleados as $e)
                    <button type="button" wire:click="seleccionar({{ $e['id'] }})" class="flex w-full items-center justify-between gap-2 px-4 py-3 text-left transition hover:bg-gray-50 {{ $sel && $sel->id === $e['id'] ? 'bg-brand-soft/40' : '' }}">
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-bold text-ink">{{ $e['name'] }} <span class="ml-1 rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-graphite">{{ $e['rol'] }}</span></span>
                            <span class="block text-[11px] text-muted">{{ $e['es_cobrador'] ? 'Devengado ' . $money($e['devengado']) . ' · ' : '' }}Pagado {{ $money($e['pagado']) }}</span>
                        </span>
                        <span class="shrink-0 text-right">
                            <span class="tabular block text-sm font-extrabold {{ $e['saldo'] > 0 ? 'text-green-600' : ($e['saldo'] < 0 ? 'text-red-600' : 'text-graphite') }}">{{ $money($e['saldo']) }}</span>
                            <span class="block text-[10px] font-bold uppercase text-muted">saldo</span>
                        </span>
                    </button>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-muted">No hay empleados activos.</p>
                @endforelse
            </div>
        </x-panel>

        {{-- Cuenta del empleado seleccionado --}}
        <div class="space-y-6 lg:col-span-2">
            @if (! $sel)
                <x-panel><p class="px-4 py-12 text-center text-sm text-muted"><span class="material-symbols-outlined mb-1 block text-3xl">badge</span> Elegí un cobrador para ver su cuenta y registrarle un pago.</p></x-panel>
            @else
                {{-- Saldo --}}
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl border p-4 shadow-soft {{ $saldo >= 0 ? 'border-green-100 bg-green-50/50' : 'border-red-100 bg-red-50/50' }}">
                        <p class="text-[11px] font-bold uppercase text-muted">Saldo {{ $saldo < 0 ? '(a favor de la empresa)' : 'a favor del empleado' }}</p>
                        <p class="tabular mt-1 text-2xl font-extrabold {{ $saldo >= 0 ? 'text-green-700' : 'text-red-600' }}">{{ $money($saldo) }}</p>
                    </div>
                    <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-soft"><p class="text-[11px] font-bold uppercase text-muted">Comisión devengada</p><p class="tabular mt-1 text-lg font-extrabold text-ink">{{ $money($devengado) }}</p></div>
                    <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-soft"><p class="text-[11px] font-bold uppercase text-muted">Total pagado</p><p class="tabular mt-1 text-lg font-extrabold text-ink">{{ $money($pagado) }}</p></div>
                </div>

                {{-- Form de pago --}}
                <x-panel :title="'Registrar pago a ' . $sel->name">
                    @if ($adelantoPagandoId)
                        <div class="mx-5 mt-4 rounded-lg border border-brand/30 bg-brand-soft/40 px-3 py-2 text-xs font-bold text-brand">Estás pagando un adelanto aprobado. Elegí el medio y confirmá.</div>
                    @endif
                    <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-graphite">Importe a pagar</label>
                            <div class="relative"><span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted">$</span>
                                <input type="number" step="0.01" min="0" wire:model="pagoMonto" class="w-full rounded-lg border border-gray-200 py-2 pl-7 pr-3 text-sm outline-none focus:border-brand" /></div>
                            @error('pagoMonto') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-graphite">Medio de pago</label>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach (['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia'] as $val => $lbl)
                                    <button type="button" wire:click="$set('pagoMedio', '{{ $val }}')" class="rounded-lg border px-2 py-2 text-xs font-bold transition {{ $pagoMedio === $val ? 'border-brand bg-brand-soft text-brand' : 'border-gray-200 text-graphite hover:bg-gray-50' }}">{{ $lbl }}</button>
                                @endforeach
                            </div>
                        </div>
                        @if ($pagoMedio === 'transferencia')
                            <div class="sm:col-span-2 space-y-2 rounded-xl border border-gray-100 bg-gray-50 p-3">
                                <label class="block text-xs font-semibold text-graphite">Comprobante de la transferencia</label>
                                <input type="file" wire:model="pagoComprobante" accept="image/*" class="block w-full text-xs text-graphite file:mr-3 file:rounded-lg file:border-0 file:bg-brand file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-white" />
                                <div wire:loading wire:target="pagoComprobante" class="text-[11px] text-brand">Subiendo…</div>
                                @error('pagoComprobante') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                @if ($pagoComprobante)<p class="text-[11px] text-green-600">✓ Comprobante cargado</p>@endif
                                <input type="text" wire:model="pagoBanco" placeholder="Banco (opcional)" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
                            </div>
                        @endif
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-semibold text-graphite">Nota (opcional)</label>
                            <input type="text" wire:model="pagoNota" placeholder="Ej: pago de comisiones de julio" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
                        </div>
                    </div>
                    <div class="flex justify-end border-t border-gray-100 px-5 py-4">
                        <button type="button" wire:click="registrarPago" wire:loading.attr="disabled" wire:target="registrarPago,pagoComprobante" class="inline-flex items-center gap-1 rounded-lg bg-brand px-5 py-2.5 text-sm font-bold text-white hover:bg-brand-dark disabled:opacity-60"><span class="material-symbols-outlined text-[18px]">payments</span> Registrar pago (egreso en caja)</button>
                    </div>
                </x-panel>

                {{-- Movimientos de la cuenta --}}
                <x-panel title="Movimientos de la cuenta">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead><tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-muted"><th class="px-4 py-2.5 font-bold">Fecha</th><th class="px-4 py-2.5 font-bold">Concepto</th><th class="px-4 py-2.5 text-right font-bold">Comisión (+)</th><th class="px-4 py-2.5 text-right font-bold">Pago (−)</th></tr></thead>
                            <tbody class="tabular">
                                @forelse ($movimientos as $m)
                                    <tr class="border-t border-gray-100">
                                        <td class="px-4 py-2.5 text-graphite">{{ $m->fecha?->format('d/m/Y H:i') }}</td>
                                        <td class="px-4 py-2.5 font-semibold text-ink">{{ $m->concepto }}</td>
                                        <td class="px-4 py-2.5 text-right {{ $m->tipo === 'haber' ? 'font-bold text-green-600' : 'text-gray-300' }}">{{ $m->tipo === 'haber' ? $money($m->monto) : '—' }}</td>
                                        <td class="px-4 py-2.5 text-right {{ $m->tipo === 'debe' ? 'font-bold text-red-600' : 'text-gray-300' }}">{{ $m->tipo === 'debe' ? $money($m->monto) : '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-muted">Sin movimientos todavía. La comisión se devenga cuando confirmás su cobranza (rendición).</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-panel>
            @endif
        </div>
    </div>

    {{-- Modal rechazo de adelanto --}}
    @if ($rechazandoId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4"><h3 class="text-base font-extrabold text-ink">Rechazar adelanto</h3><button wire:click="$set('rechazandoId', null)" class="text-muted hover:text-ink"><span class="material-symbols-outlined">close</span></button></div>
                <div class="p-5">
                    <label class="mb-1 block text-xs font-semibold text-graphite">Motivo (opcional)</label>
                    <input type="text" wire:model="motivoRechazo" placeholder="Motivo del rechazo…" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-4">
                    <button wire:click="$set('rechazandoId', null)" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-bold text-graphite hover:bg-gray-100">Cancelar</button>
                    <button wire:click="rechazarAdelanto" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700">Rechazar</button>
                </div>
            </div>
        </div>
    @endif
</div>
