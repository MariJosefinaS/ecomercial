<div class="space-y-6">

    @php $hoy = \Carbon\Carbon::today(); @endphp

    <div>
        <h1 class="text-2xl font-extrabold text-ink">Tesorería</h1>
        <p class="text-sm text-muted">Caja, flujo de fondos y cheques (ingresos y egresos)</p>
    </div>

    @if ($mensaje)
        <div class="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm font-semibold text-green-700">
            <span class="material-symbols-outlined text-[18px]">check_circle</span> {{ $mensaje }}
        </div>
    @endif

    {{-- ===== Resumen: KPIs + avisos del día ===== --}}
    @if ($tab === 'resumen')
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <x-kpi-card variant="brand" title="Saldo de Caja" :value="'$' . number_format($kpis['saldo'], 0, ',', '.')" icon="account_balance" />
            <x-kpi-card variant="blue"  title="Ingresos Hoy" :value="'$' . number_format($kpis['ingresos_hoy'], 0, ',', '.')" icon="trending_up" subtitle="A ingresar" />
            <x-kpi-card variant="red"   title="Egresos Hoy" :value="'$' . number_format($kpis['egresos_hoy'], 0, ',', '.')" icon="trending_down" subtitle="A debitar" />
            <x-kpi-card variant="beige" title="Neto Hoy" :value="'$' . number_format($kpis['ingresos_hoy'] - $kpis['egresos_hoy'], 0, ',', '.')" icon="balance" />
        </div>

        @if (! empty($avisos['debitar_hoy']) || ! empty($avisos['debitar_manana']) || ! empty($avisos['depositar_hoy']))
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                @if (! empty($avisos['debitar_hoy']))
                    <div class="rounded-xl border-2 border-red-200 bg-red-50 p-4">
                        <h3 class="mb-2 flex items-center gap-1.5 text-sm font-extrabold uppercase text-red-700"><span class="material-symbols-outlined text-[18px]">priority_high</span> Se debitan HOY</h3>
                        @foreach ($avisos['debitar_hoy'] as $c)
                            <p class="text-sm"><b>{{ $c['num'] }}</b> · {{ $c['proveedor'] }} — <span class="font-bold text-red-700">${{ number_format($c['monto'], 0, ',', '.') }}</span></p>
                        @endforeach
                    </div>
                @endif
                @if (! empty($avisos['debitar_manana']))
                    <div class="rounded-xl border-2 border-amber-200 bg-amber-50 p-4">
                        <h3 class="mb-2 flex items-center gap-1.5 text-sm font-extrabold uppercase text-amber-700"><span class="material-symbols-outlined text-[18px]">schedule</span> Se debitan MAÑANA</h3>
                        @foreach ($avisos['debitar_manana'] as $c)
                            <p class="text-sm"><b>{{ $c['num'] }}</b> · {{ $c['proveedor'] }} — <span class="font-bold text-amber-700">${{ number_format($c['monto'], 0, ',', '.') }}</span></p>
                        @endforeach
                    </div>
                @endif
                @if (! empty($avisos['depositar_hoy']))
                    <div class="rounded-xl border-2 border-green-200 bg-green-50 p-4">
                        <h3 class="mb-2 flex items-center gap-1.5 text-sm font-extrabold uppercase text-green-700"><span class="material-symbols-outlined text-[18px]">savings</span> A depositar HOY</h3>
                        @foreach ($avisos['depositar_hoy'] as $c)
                            <p class="text-sm"><b>{{ $c['num'] }}</b> · {{ $c['cliente'] }} — <span class="font-bold text-green-700">${{ number_format($c['monto'], 0, ',', '.') }}</span></p>
                        @endforeach
                    </div>
                @endif
            </div>
        @else
            <x-panel>
                <p class="p-5 text-sm text-muted">No hay cheques que se debiten o deban depositarse en las próximas 48 horas.</p>
            </x-panel>
        @endif
    @endif

    {{-- ===== Movimientos de caja ===== --}}
    @if ($tab === 'caja')
        <x-panel title="Movimientos de caja">
            <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr class="text-[11px] uppercase tracking-wide text-muted"><th class="px-5 py-3 font-bold">Fecha</th><th class="px-5 py-3 font-bold">Concepto</th><th class="px-5 py-3 font-bold">Medio</th><th class="px-5 py-3 text-right font-bold">Ingreso</th><th class="px-5 py-3 text-right font-bold">Egreso</th></tr></thead>
                <tbody class="tabular">
                    @foreach ($movimientos as $m)
                        <tr class="border-t border-gray-100">
                            <td class="px-5 py-3 text-graphite">{{ \Carbon\Carbon::parse($m['fecha'])->format('d/m/Y') }}</td>
                            <td class="px-5 py-3 font-semibold text-ink">{{ $m['concepto'] }}</td>
                            <td class="px-5 py-3"><span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-bold text-graphite">{{ $m['medio'] }}</span></td>
                            <td class="px-5 py-3 text-right {{ $m['tipo'] === 'ingreso' ? 'font-bold text-success' : 'text-gray-300' }}">{{ $m['tipo'] === 'ingreso' ? '$' . number_format($m['monto'], 2, ',', '.') : '—' }}</td>
                            <td class="px-5 py-3 text-right {{ $m['tipo'] === 'egreso' ? 'font-bold text-danger' : 'text-gray-300' }}">{{ $m['tipo'] === 'egreso' ? '$' . number_format($m['monto'], 2, ',', '.') : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </x-panel>
    @endif

    {{-- ===== Cheques a depositar (clientes) ===== --}}
    @if ($tab === 'depositar')
        <x-panel title="Cheques a depositar">
            <div class="divide-y divide-gray-100">
                @foreach ($chequesDepositar as $i => $c)
                    @php $d = \Carbon\Carbon::parse($c['deposito']); $dias = (int) $hoy->diffInDays($d, false); @endphp
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5">
                        <div>
                            <p class="text-sm font-bold text-ink">N° {{ $c['num'] }} <span class="font-medium text-muted">· {{ $c['banco'] }}</span></p>
                            <p class="text-xs text-graphite">{{ $c['cliente'] }} · Depositar {{ $d->format('d/m/Y') }}
                                <span class="font-bold {{ $dias === 0 ? 'text-green-700' : 'text-muted' }}">· {{ $dias < 0 ? 'vencido' : ($dias === 0 ? 'HOY' : 'en ' . $dias . ' días') }}</span></p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="tabular text-sm font-extrabold text-ink">${{ number_format($c['monto'], 2, ',', '.') }}</span>
                            <span class="inline-block rounded-full px-2 py-0.5 text-[10px] font-bold uppercase {{ $c['estado'] === 'depositado' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">{{ $c['estado'] }}</span>
                            @if ($c['estado'] === 'pendiente')
                                @puede('cargar_cheques')
                                <button wire:click="marcarDepositado({{ $c['id'] }})" class="rounded-lg bg-success px-3 py-1.5 text-xs font-bold text-white hover:brightness-95">Marcar depositado</button>
                                @endpuede
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-panel>
    @endif

    {{-- ===== Cheques a debitar (proveedores) ===== --}}
    @if ($tab === 'debitar')
        <x-panel title="Cheques a debitar">
            <div class="divide-y divide-gray-100">
                @foreach ($chequesDebitar as $i => $c)
                    @php $d = \Carbon\Carbon::parse($c['debito']); $dias = (int) $hoy->diffInDays($d, false); @endphp
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 {{ $dias === 0 ? 'bg-red-50' : ($dias === 1 ? 'bg-amber-50' : '') }}">
                        <div>
                            <p class="text-sm font-bold text-ink">N° {{ $c['num'] }} <span class="font-medium text-muted">· {{ $c['banco'] }}</span></p>
                            <p class="text-xs text-graphite">{{ $c['proveedor'] }} · Débito {{ $d->format('d/m/Y') }}
                                <span class="font-bold {{ $dias === 0 ? 'text-red-700' : ($dias === 1 ? 'text-amber-700' : 'text-muted') }}">· {{ $dias < 0 ? 'debitado' : ($dias === 0 ? 'HOY' : ($dias === 1 ? 'MAÑANA' : 'en ' . $dias . ' días')) }}</span></p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="tabular text-sm font-extrabold text-ink">${{ number_format($c['monto'], 2, ',', '.') }}</span>
                            <span class="inline-block rounded-full px-2 py-0.5 text-[10px] font-bold uppercase {{ $c['estado'] === 'debitado' ? 'bg-gray-200 text-graphite' : 'bg-amber-100 text-amber-700' }}">{{ $c['estado'] }}</span>
                            @if ($c['estado'] === 'pendiente')
                                @puede('cargar_cheques')
                                <button wire:click="marcarDebitado({{ $c['id'] }})" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-bold text-graphite hover:bg-gray-50">Marcar debitado</button>
                                @endpuede
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-panel>
    @endif

    {{-- ===== Proyección diaria ===== --}}
    @if ($tab === 'proyeccion')
        <x-panel title="Proyección de fondos (7 días)">
            <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr class="text-[11px] uppercase tracking-wide text-muted"><th class="px-5 py-3 font-bold">Día</th><th class="px-5 py-3 text-right font-bold">Ingresos</th><th class="px-5 py-3 text-right font-bold">Egresos</th><th class="px-5 py-3 text-right font-bold">Neto</th><th class="px-5 py-3 text-right font-bold">Saldo proyectado</th></tr></thead>
                <tbody class="tabular">
                    @foreach ($proyeccion as $p)
                        <tr class="border-t border-gray-100 {{ $p['fecha']->isToday() ? 'bg-brand-soft/40' : '' }}">
                            <td class="px-5 py-3 font-semibold text-ink">{{ $p['fecha']->isToday() ? 'Hoy' : ucfirst($p['fecha']->locale('es')->isoFormat('ddd D/MM')) }}</td>
                            <td class="px-5 py-3 text-right font-bold text-success">${{ number_format($p['in'], 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right font-bold text-danger">${{ number_format($p['eg'], 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right font-bold {{ $p['neto'] >= 0 ? 'text-ink' : 'text-danger' }}">${{ number_format($p['neto'], 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right text-base font-extrabold {{ $p['saldo'] < 0 ? 'text-danger' : 'text-ink' }}">${{ number_format($p['saldo'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </x-panel>
    @endif
</div>
