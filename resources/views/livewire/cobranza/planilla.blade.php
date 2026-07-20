<div class="space-y-6">

    @php
        $money = fn ($n) => '$' . number_format((float) $n, 2, ',', '.');
        $estadoBadge = [
            'sin_abrir' => 'bg-gray-100 text-graphite',
            'en_confeccion' => 'bg-amber-100 text-amber-700',
            'pend_auditoria' => 'bg-sky-100 text-sky-700',
            'cerrada' => 'bg-green-100 text-green-700',
        ];
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
            <h1 class="text-2xl font-extrabold text-ink">Mi planilla de cobranza</h1>
            <p class="text-sm text-muted">Cuotas del día por modalidad · el moroso reaparece en la planilla de su plan</p>
            <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-brand-soft px-2.5 py-0.5 text-[11px] font-bold text-brand">
                <span class="material-symbols-outlined text-[14px]">person_pin_circle</span> {{ $cobrador?->name ?? '—' }}
            </span>
        </div>
        <div class="flex items-end gap-2">
            @if ($esAdmin)
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase text-muted">Cobrador</label>
                    <select wire:model.live="cobradorId" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand">
                        @foreach ($cobradores as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label class="mb-1 block text-[11px] font-bold uppercase text-muted">Fecha</label>
                <input type="date" wire:model.live="fecha" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
            </div>
            @puede('reportar_no_visita')
                <button type="button" wire:click="abrirNoVisita" class="flex items-center gap-1 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-bold text-amber-700 hover:bg-amber-100" title="Reportar que no cobraste este día">
                    <span class="material-symbols-outlined text-[18px]">event_busy</span> No cobré
                </button>
            @endpuede
        </div>
    </div>

    @if ($mensaje)
        <div class="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm font-semibold text-green-700">
            <span class="material-symbols-outlined text-[18px]">check_circle</span> {{ $mensaje }}
        </div>
    @endif

    @forelse ($grupos as $g)
        <div wire:key="grupo-{{ $g['modalidad'] }}">
        <x-panel>
            {{-- Cabecera del grupo (modalidad + estado + apertura/cierre + acciones) --}}
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-anthracite text-white">
                        <span class="material-symbols-outlined">receipt_long</span>
                    </span>
                    <div>
                        <p class="text-base font-extrabold text-ink">Planilla {{ $g['label'] }}</p>
                        <div class="flex flex-wrap items-center gap-2 text-[11px] text-muted">
                            <span class="rounded-full px-2 py-0.5 font-bold {{ $estadoBadge[$g['estado']] ?? 'bg-gray-100 text-graphite' }}">{{ $g['estado_label'] }}</span>
                            @if ($g['apertura'])<span>· Apertura {{ $g['apertura'] }}</span>@endif
                            @if ($g['cierre'])<span>· Cierre {{ $g['cierre'] }}</span>@endif
                            @if ($g['auditor'])<span>· Auditó {{ $g['auditor'] }}</span>@endif
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-1.5">
                    @if ($g['estado'] === 'sin_abrir')
                        <button type="button" wire:click="abrir('{{ $g['modalidad'] }}')" class="inline-flex items-center gap-1 rounded-lg bg-brand px-3 py-1.5 text-xs font-bold text-white hover:bg-brand-dark"><span class="material-symbols-outlined text-[16px]">lock_open</span> Abrir</button>
                    @elseif ($g['estado'] === 'en_confeccion')
                        <button type="button" wire:click="cerrar('{{ $g['modalidad'] }}')" wire:confirm="¿Cerrar la planilla {{ $g['label'] }}? Queda pendiente de auditoría." class="inline-flex items-center gap-1 rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-sky-700"><span class="material-symbols-outlined text-[16px]">lock</span> Cerrar jornada</button>
                    @elseif ($g['estado'] === 'pend_auditoria' && $puedeAuditar)
                        <button type="button" wire:click="auditar('{{ $g['modalidad'] }}')" class="inline-flex items-center gap-1 rounded-lg bg-green-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-green-700"><span class="material-symbols-outlined text-[16px]">fact_check</span> Aprobar auditoría</button>
                    @endif
                    <a href="{{ route('cobranza.planilla.imprimir', ['cob' => $cobrador?->id, 'fecha' => $fecha, 'modalidad' => $g['modalidad']]) }}" target="_blank" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-bold text-graphite hover:bg-gray-100"><span class="material-symbols-outlined text-[16px]">print</span> Imprimir</a>
                    <button type="button" wire:click="exportarCsv('{{ $g['modalidad'] }}')" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-bold text-graphite hover:bg-gray-100"><span class="material-symbols-outlined text-[16px]">download</span> Exportar</button>
                </div>
            </div>

            {{-- Líneas --}}
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-muted">
                        <th class="px-4 py-2.5 font-bold">Cliente</th>
                        <th class="px-4 py-2.5 font-bold">Crédito · Plan</th>
                        <th class="px-4 py-2.5 text-center font-bold">Cuota</th>
                        <th class="px-4 py-2.5 text-center font-bold">Atraso</th>
                        <th class="px-4 py-2.5 text-right font-bold">Saldo</th>
                        <th class="px-4 py-2.5 text-right font-bold">Total a cobrar</th>
                        <th class="px-4 py-2.5"></th>
                    </tr></thead>
                    <tbody>
                        @foreach ($g['filas'] as $r)
                            @php [$dcls, $dlbl] = $diasBadge($r['dias']); @endphp
                            <tr class="border-t border-gray-100 {{ $r['cobrada'] ? 'bg-green-50/40' : '' }}" wire:key="cuota-{{ $r['id'] }}">
                                <td class="px-4 py-2.5">
                                    <p class="font-bold text-ink">{{ $r['cliente'] }}</p>
                                    <p class="text-[11px] text-muted">{{ $r['domicilio'] ?: '—' }} @if ($r['telefono']) · {{ $r['telefono'] }} @endif</p>
                                </td>
                                <td class="px-4 py-2.5"><p class="font-semibold text-graphite">{{ $r['credito'] }}</p><p class="text-[11px] text-muted">{{ $r['plan'] }}</p></td>
                                <td class="px-4 py-2.5 text-center tabular">#{{ $r['numero'] }}<p class="text-[11px] text-muted">{{ $r['vence'] }}</p></td>
                                <td class="px-4 py-2.5 text-center"><span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $dcls }}">{{ $dlbl }}</span></td>
                                <td class="px-4 py-2.5 text-right tabular">{{ $money($r['saldo']) }}</td>
                                <td class="px-4 py-2.5 text-right tabular font-bold text-ink">{{ $money($r['total']) }}@if ($r['mora'] > 0)<p class="text-[11px] font-normal text-red-600">+{{ $money($r['mora']) }} mora</p>@endif</td>
                                <td class="px-4 py-2.5 text-right">
                                    @if ($r['cobrada'])
                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold text-green-600"><span class="material-symbols-outlined text-[16px]">check_circle</span> Cobrada</span>
                                    @else
                                        @puede('registrar_cobro')
                                        <button type="button" wire:click="abrirCobro({{ $r['id'] }})" class="inline-flex items-center gap-1 rounded-lg bg-brand px-3 py-1.5 text-xs font-bold text-white hover:bg-brand-dark"><span class="material-symbols-outlined text-[16px]">payments</span> Cobrar</button>
                                        @endpuede
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Resumen de la jornada (abajo, separado de la info de cobranza) --}}
            <div class="mt-1 border-t-2 border-gray-100 bg-gray-50/70 px-4 py-3">
                <p class="mb-2 text-[11px] font-bold uppercase tracking-wide text-muted">Resumen de la jornada</p>
                <div class="grid grid-cols-3 gap-3">
                    <div class="rounded-xl border border-gray-100 bg-white p-3 text-center shadow-soft">
                        <p class="text-[11px] font-bold uppercase text-muted">Esperado</p>
                        <p class="tabular text-lg font-extrabold text-ink">{{ $money($g['esperado']) }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-white p-3 text-center shadow-soft">
                        <p class="text-[11px] font-bold uppercase text-muted">Cobrado</p>
                        <p class="tabular text-lg font-extrabold text-green-600">{{ $money($g['cobrado']) }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-white p-3 text-center shadow-soft">
                        <p class="text-[11px] font-bold uppercase text-muted">Eficacia</p>
                        <p class="tabular text-lg font-extrabold {{ $g['eficacia'] >= 90 ? 'text-green-600' : ($g['eficacia'] >= 85 ? 'text-amber-600' : 'text-red-600') }}">{{ number_format($g['eficacia'], 1, ',', '.') }}%</p>
                    </div>
                </div>
            </div>
        </x-panel>
        </div>
    @empty
        <x-panel>
            <div class="px-4 py-12 text-center text-sm text-muted">
                <span class="material-symbols-outlined mb-1 block text-3xl">event_available</span>
                No hay cuotas para cobrar en esta fecha.
            </div>
        </x-panel>
    @endforelse

    {{-- ===== Modal de cobro (monto libre + medio + comprobante) ===== --}}
    @if ($modalCobro)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" wire:key="modal-cobro">
            <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 class="flex items-center gap-2 text-base font-extrabold text-ink">
                        <span class="material-symbols-outlined text-brand">payments</span> Registrar cobro
                    </h3>
                    <button type="button" wire:click="cerrarCobro" class="text-muted hover:text-ink"><span class="material-symbols-outlined">close</span></button>
                </div>

                <div class="space-y-3 p-5">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-muted">Cliente: <span class="font-bold text-ink">{{ $cobroCliente }}</span></span>
                        <span class="text-muted">Total a cobrar: <span class="font-bold text-ink">${{ number_format($cobroSugerido, 2, ',', '.') }}</span></span>
                    </div>
                    <p class="text-[11px] text-muted">Repartí lo que paga entre los medios (puede ser <b>dividido</b>: parte efectivo + parte transferencia + parte cheque). El total es la suma.</p>

                    {{-- Efectivo --}}
                    <div>
                        <label class="mb-1 flex items-center justify-between text-xs font-semibold text-graphite">
                            <span>Efectivo</span>
                            <button type="button" wire:click="$set('cobroEfectivo', '{{ $cobroSugerido }}')" class="text-[11px] font-bold text-brand hover:underline">Poner el total acá</button>
                        </label>
                        <div class="relative"><span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted">$</span>
                            <input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="cobroEfectivo" class="w-full rounded-lg border border-gray-200 py-2 pl-7 pr-3 text-sm outline-none focus:border-brand" /></div>
                        @error('cobroEfectivo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Transferencia --}}
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-graphite">Transferencia</label>
                        <div class="relative"><span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted">$</span>
                            <input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="cobroTransferencia" class="w-full rounded-lg border border-gray-200 py-2 pl-7 pr-3 text-sm outline-none focus:border-brand" /></div>
                        @if ((float) $cobroTransferencia > 0)
                            <div class="mt-2 space-y-2 rounded-xl border border-gray-100 bg-gray-50 p-3">
                                <label class="block text-xs font-semibold text-graphite">Comprobante de la transferencia</label>
                                <input type="file" wire:model="cobroComprobante" accept="image/*" class="block w-full text-xs text-graphite file:mr-3 file:rounded-lg file:border-0 file:bg-brand file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-white" />
                                <div wire:loading wire:target="cobroComprobante" class="text-[11px] text-brand">Subiendo…</div>
                                @error('cobroComprobante') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                @if ($cobroComprobante)<p class="text-[11px] text-green-600">✓ Comprobante cargado</p>@endif
                                <input type="text" wire:model="cobroBanco" placeholder="Banco (opcional)" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
                            </div>
                        @endif
                    </div>

                    {{-- Cheque --}}
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-graphite">Cheque</label>
                        <div class="relative"><span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted">$</span>
                            <input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="cobroCheque" class="w-full rounded-lg border border-gray-200 py-2 pl-7 pr-3 text-sm outline-none focus:border-brand" /></div>
                        @if ((float) $cobroCheque > 0)
                            <div class="mt-2 grid grid-cols-2 gap-2 rounded-xl border border-gray-100 bg-gray-50 p-3">
                                <input type="text" wire:model="cobroChequeNumero" placeholder="N° de cheque" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
                                <input type="text" wire:model="cobroBanco" placeholder="Banco" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
                                @error('cobroChequeNumero') <p class="col-span-2 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endif
                    </div>

                    {{-- Total del pago --}}
                    <div class="flex items-center justify-between rounded-xl bg-anthracite px-4 py-2.5 text-white">
                        <span class="text-sm font-semibold">Total del pago</span>
                        <span class="tabular text-lg font-extrabold">${{ number_format($this->cobroTotal, 2, ',', '.') }}</span>
                    </div>
                    <p class="text-[11px] text-muted">Si supera la cuota, el excedente <b>adelanta la próxima</b>; si es menor, queda <b>saldo</b> (sigue la mora).</p>
                </div>

                <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-4">
                    <button type="button" wire:click="cerrarCobro" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-bold text-graphite hover:bg-gray-100">Cancelar</button>
                    <button type="button" wire:click="registrarCobro" wire:loading.attr="disabled" wire:target="registrarCobro,cobroComprobante" class="inline-flex items-center gap-1 rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-dark disabled:opacity-60">
                        <span class="material-symbols-outlined text-[18px]">check</span> Registrar cobro
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== Modal "No cobré este día" (queda pendiente de aprobación del supervisor) ===== --}}
    @if ($modalNoVisita)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" wire:key="modal-novisita">
            <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 class="flex items-center gap-2 text-base font-extrabold text-ink">
                        <span class="material-symbols-outlined text-amber-600">event_busy</span> No cobré este día
                    </h3>
                    <button type="button" wire:click="cerrarNoVisita" class="text-muted hover:text-ink"><span class="material-symbols-outlined">close</span></button>
                </div>
                <div class="space-y-4 p-5">
                    <p class="text-sm text-muted">Vas a reportar que <b>no pasaste a cobrar</b> el <b>{{ \Illuminate\Support\Carbon::parse($fecha)->format('d/m/Y') }}</b>. Queda <b>pendiente de aprobación</b>; recién cuando el supervisor lo apruebe, la mora de ese día no le corre a los clientes.</p>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-graphite">Motivo</label>
                        <select wire:model="nvMotivo" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand">
                            @foreach (\App\Models\NoVisita::MOTIVOS as $val => $lbl)
                                <option value="{{ $val }}">{{ $lbl }}</option>
                            @endforeach
                        </select>
                        @error('nvMotivo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-graphite">Nota (opcional)</label>
                        <input type="text" wire:model="nvNota" placeholder="Ej: descompuesto, no llegué a la zona…" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-4">
                    <button type="button" wire:click="cerrarNoVisita" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-bold text-graphite hover:bg-gray-100">Cancelar</button>
                    <button type="button" wire:click="reportarNoVisita" class="inline-flex items-center gap-1 rounded-lg bg-amber-600 px-4 py-2 text-sm font-bold text-white hover:bg-amber-700">
                        <span class="material-symbols-outlined text-[18px]">send</span> Enviar reporte
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
