<div class="space-y-6">

    @php
        $riesgoBadge = ['bajo' => 'bg-green-100 text-green-700', 'medio' => 'bg-amber-100 text-amber-700', 'alto' => 'bg-red-100 text-red-700'];
    @endphp

    @if ($mensaje)
        <div class="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm font-semibold text-green-700">
            <span class="material-symbols-outlined text-[18px]">check_circle</span> {{ $mensaje }}
        </div>
    @endif

    @if ($cliente)
        {{-- ====================== FICHA DEL CLIENTE ====================== --}}
        @php
            $util = $cliente['limite'] > 0 ? round($cliente['saldo'] / $cliente['limite'] * 100) : 0;
            $chRech = collect($cliente['cheques'])->where('estado', 'rechazado')->count();
            $devs = count($cliente['devoluciones']);
        @endphp

        <button wire:click="volver" class="flex items-center gap-1 text-sm font-bold text-graphite hover:text-brand">
            <span class="material-symbols-outlined text-[20px]">arrow_back</span> Volver a clientes
        </button>

        {{-- Encabezado ficha --}}
        <div class="flex flex-wrap items-start justify-between gap-4 rounded-xl border border-gray-100 bg-white p-5 shadow-card">
            <div class="flex items-center gap-4">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-soft text-xl font-extrabold text-brand">{{ mb_strtoupper(mb_substr($cliente['nombre'], 0, 2)) }}</span>
                <div>
                    <h1 class="text-xl font-extrabold text-ink">{{ $cliente['nombre'] }}</h1>
                    <p class="text-xs text-muted">
                        @if ($cliente['numero_cuenta'])<span class="font-bold text-graphite">Cuenta N° {{ $cliente['numero_cuenta'] }}</span> · @endif{{ $cliente['doc'] }} · {{ $cliente['tel'] }}
                    </p>
                    @php [$semDot, $semTxt, $semBg] = \App\Support\Semaforo::clases($cliente['semaforo']['estado']); @endphp
                    <div class="mt-1 flex flex-wrap items-center gap-1.5">
                        <span class="inline-block rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wide {{ $riesgoBadge[$cliente['riesgo']] }}">Riesgo {{ $cliente['riesgo'] }}</span>
                        <span class="inline-flex items-center gap-1 rounded-full {{ $semBg }} px-2.5 py-0.5 text-[11px] font-bold {{ $semTxt }}">
                            <span class="h-2 w-2 rounded-full {{ $semDot }}"></span> {{ \App\Support\Semaforo::label($cliente['semaforo']['estado']) }}@if ($cliente['semaforo']['vencidas'] > 0) · {{ $cliente['semaforo']['vencidas'] }} venc.@endif
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex gap-6">
                <div class="text-right"><p class="text-[11px] font-bold uppercase text-muted">Límite</p><p class="tabular text-lg font-extrabold text-ink">${{ number_format($cliente['limite'], 0, ',', '.') }}</p></div>
                <div class="text-right"><p class="text-[11px] font-bold uppercase text-muted">Saldo deudor</p><p class="tabular text-lg font-extrabold {{ $util >= 90 ? 'text-danger' : 'text-ink' }}">${{ number_format($cliente['saldo'], 0, ',', '.') }}</p></div>
            </div>
        </div>

        {{-- Análisis de riesgo --}}
        @puede('ver_riesgo_cliente')
        <x-panel title="Análisis de riesgo crediticio">
            <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-3">
                <div class="rounded-xl border border-gray-100 p-4">
                    <p class="text-xs font-bold uppercase text-muted">Uso del crédito</p>
                    <p class="tabular mt-1 text-2xl font-extrabold {{ $util >= 90 ? 'text-danger' : ($util >= 70 ? 'text-amber-600' : 'text-ink') }}">{{ $util }}%</p>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100"><div class="h-full {{ $util >= 90 ? 'bg-danger' : ($util >= 70 ? 'bg-amber-500' : 'bg-success') }}" style="width: {{ min($util, 100) }}%"></div></div>
                </div>
                <div class="rounded-xl border border-gray-100 p-4">
                    <p class="text-xs font-bold uppercase text-muted">Cheques rechazados</p>
                    <p class="mt-1 text-2xl font-extrabold {{ $chRech > 0 ? 'text-danger' : 'text-ink' }}">{{ $chRech }}</p>
                    <p class="mt-1 text-xs text-muted">Historial de pagos</p>
                </div>
                <div class="rounded-xl border border-gray-100 p-4">
                    <p class="text-xs font-bold uppercase text-muted">Devoluciones</p>
                    <p class="mt-1 text-2xl font-extrabold text-ink">{{ $devs }}</p>
                    <p class="mt-1 text-xs text-muted">Mercadería devuelta</p>
                </div>
            </div>
            @if ($cliente['riesgo'] === 'alto')
                <div class="mx-5 mb-5 flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-700">
                    <span class="material-symbols-outlined text-[20px]">warning</span> Cliente de RIESGO ALTO — revisar antes de aprobar ventas a crédito.
                </div>
            @endif
        </x-panel>
        @endpuede

        {{-- Semáforo por crédito (en vivo) --}}
        @if (! empty($cliente['semaforo']['creditos_detalle']))
            <x-panel title="Semáforo de créditos">
                <p class="px-5 pt-4 text-xs text-muted">Estado en vivo de cada crédito activo: 🟢 al día · 🟡 en atraso · 🔴 no paga (llegó al umbral de incobrable de su plan). La barra es el <b>% de avance</b> (cuotas pagadas).</p>
                <div class="divide-y divide-gray-100 p-2">
                    @foreach ($cliente['semaforo']['creditos_detalle'] as $cr)
                        @php [$dDot, $dTxt, $dBg] = \App\Support\Semaforo::clases($cr['estado']); @endphp
                        <div class="flex flex-wrap items-center gap-3 px-3 py-3">
                            <span class="h-3 w-3 shrink-0 rounded-full {{ $dDot }}"></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold text-ink">{{ $cr['venta'] }} <span class="font-normal text-muted">· {{ $cr['plan'] }}</span></p>
                                <div class="mt-1 flex items-center gap-2">
                                    <div class="h-1.5 w-32 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full {{ $dDot }}" style="width: {{ min(100, $cr['avance']) }}%"></div></div>
                                    <span class="text-[11px] text-muted">{{ $cr['pagadas'] }}/{{ $cr['total'] }} cuotas · {{ rtrim(rtrim(number_format($cr['avance'], 1, ',', '.'), '0'), ',') }}%</span>
                                </div>
                            </div>
                            <span class="shrink-0 rounded-full {{ $dBg }} px-2.5 py-0.5 text-[11px] font-bold {{ $dTxt }}">
                                {{ \App\Support\Semaforo::label($cr['estado']) }}@if ($cr['vencidas'] > 0) · {{ $cr['vencidas'] }}@if ($cr['umbral'] > 0)/{{ $cr['umbral'] }}@endif venc.@endif
                            </span>
                        </div>
                    @endforeach
                </div>
            </x-panel>
        @endif

        {{-- Tabs de la ficha --}}
        <x-panel>
            <div class="flex items-center gap-1 border-b border-gray-100 px-3">
                @foreach (['cuenta' => 'Cuenta corriente', 'creditos' => 'Créditos', 'cuotas' => 'Cuotas', 'domicilios' => 'Domicilios', 'compras' => 'Compras', 'pagos' => 'Pagos', 'cheques' => 'Cheques', 'devoluciones' => 'Devoluciones'] as $t => $lbl)
                    <button wire:click="setTab('{{ $t }}')" class="-mb-px border-b-2 px-4 py-3 text-sm font-bold transition {{ $tab === $t ? 'border-brand text-brand' : 'border-transparent text-graphite hover:text-brand' }}">{{ $lbl }}</button>
                @endforeach
            </div>

            @if ($tab === 'cuenta')
                <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="text-[11px] uppercase tracking-wide text-muted"><th class="px-5 py-3 font-bold">Fecha</th><th class="px-5 py-3 font-bold">Concepto</th><th class="px-5 py-3 text-right font-bold">Debe</th><th class="px-5 py-3 text-right font-bold">Haber</th></tr></thead>
                    <tbody class="tabular">
                        @foreach ($cliente['movimientos'] as $m)
                            <tr class="border-t border-gray-100">
                                <td class="px-5 py-3 text-graphite">{{ $m['fecha'] }}</td>
                                <td class="px-5 py-3 font-semibold text-ink">{{ $m['concepto'] }}</td>
                                <td class="px-5 py-3 text-right {{ $m['tipo'] === 'debe' ? 'font-bold text-danger' : 'text-gray-300' }}">{{ $m['tipo'] === 'debe' ? '$' . number_format($m['monto'], 2, ',', '.') : '—' }}</td>
                                <td class="px-5 py-3 text-right {{ $m['tipo'] === 'haber' ? 'font-bold text-success' : 'text-gray-300' }}">{{ $m['tipo'] === 'haber' ? '$' . number_format($m['monto'], 2, ',', '.') : '—' }}</td>
                            </tr>
                        @endforeach
                        <tr class="border-t-2 border-gray-200 bg-gray-50"><td colspan="3" class="px-5 py-3 text-right font-bold uppercase text-muted">Saldo</td><td class="px-5 py-3 text-right text-base font-extrabold text-ink">${{ number_format($cliente['saldo'], 2, ',', '.') }}</td></tr>
                    </tbody>
                </table>
                </div>

            @elseif ($tab === 'creditos')
                {{-- Cuenta corriente POR crédito (además del saldo único de la pestaña Cuenta corriente) --}}
                <p class="px-5 pt-4 text-xs text-muted">Cada <b>crédito</b> de la cuenta con su propio saldo (para saber qué producto/crédito paga). El <b>saldo único fiscal</b> está en la pestaña «Cuenta corriente».</p>
                <div class="space-y-4 p-5">
                    @forelse ($cliente['creditos'] as $cr)
                        @php $estBadge = ['aprobada'=>'bg-green-100 text-green-700','pendiente'=>'bg-amber-100 text-amber-700','rechazada'=>'bg-red-100 text-red-700','entregada'=>'bg-sky-100 text-sky-700'][$cr['estado']] ?? 'bg-gray-100 text-graphite'; @endphp
                        <div class="overflow-hidden rounded-xl border border-gray-100">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 bg-gray-50 px-4 py-3">
                                <div>
                                    <p class="text-sm font-extrabold text-ink">Crédito {{ $cr['barra'] }} <span class="ml-1 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase {{ $estBadge }}">{{ $cr['estado'] }}</span></p>
                                    <p class="text-[11px] text-muted">{{ $cr['numero'] }} · {{ $cr['plan'] }} · {{ $cr['fecha'] }} · {{ $cr['pagadas'] }}/{{ $cr['total_cuotas'] }} cuotas ({{ rtrim(rtrim(number_format($cr['avance'],1,',','.'),'0'),',') }}%)</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] font-bold uppercase text-muted">Saldo del crédito</p>
                                    <p class="tabular text-lg font-extrabold {{ $cr['saldo'] > 0 ? 'text-ink' : 'text-green-600' }}">${{ number_format($cr['saldo'], 2, ',', '.') }}</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-px bg-gray-100 text-center">
                                <div class="bg-white p-2"><p class="text-[10px] font-bold uppercase text-muted">Vencido</p><p class="tabular text-sm font-extrabold {{ $cr['vencido'] > 0 ? 'text-amber-700' : 'text-ink' }}">${{ number_format($cr['vencido'], 2, ',', '.') }}</p></div>
                                <div class="bg-white p-2"><p class="text-[10px] font-bold uppercase text-muted">A vencer</p><p class="tabular text-sm font-extrabold text-ink">${{ number_format($cr['a_vencer'], 2, ',', '.') }}</p></div>
                                <div class="bg-white p-2"><p class="text-[10px] font-bold uppercase text-muted">Mora</p><p class="tabular text-sm font-extrabold {{ $cr['mora'] > 0 ? 'text-red-600' : 'text-ink' }}">${{ number_format($cr['mora'], 2, ',', '.') }}</p></div>
                            </div>
                            @if ($cr['garante'])
                                <div class="flex items-center gap-1.5 border-t border-gray-100 px-4 py-2 text-[11px] text-graphite">
                                    <span class="material-symbols-outlined text-[15px] text-muted">handshake</span> <b>Garante:</b> {{ $cr['garante'] }}@if ($cr['garante_doc']) · {{ $cr['garante_doc'] }}@endif @if ($cr['garante_tel']) · {{ $cr['garante_tel'] }}@endif
                                </div>
                            @endif

                            {{-- Estado de cuenta del crédito (métricas completas, como CRED) --}}
                            <details class="group border-t border-gray-100">
                                <summary class="flex cursor-pointer list-none items-center gap-1.5 px-4 py-2 text-[11px] font-bold uppercase tracking-wide text-brand">
                                    <span class="material-symbols-outlined text-[16px] transition-transform group-open:rotate-90">chevron_right</span> Estado de cuenta del crédito
                                </summary>
                                <div class="grid grid-cols-2 gap-px bg-gray-100 text-center sm:grid-cols-4">
                                    <div class="bg-white p-2"><p class="text-[10px] font-bold uppercase text-muted">Total cobrado</p><p class="tabular text-sm font-extrabold text-green-600">${{ number_format($cr['total_cobrado'], 2, ',', '.') }}</p></div>
                                    <div class="bg-white p-2"><p class="text-[10px] font-bold uppercase text-muted">Debería haber pagado</p><p class="tabular text-sm font-extrabold text-ink">${{ number_format($cr['deberia_pagado'], 2, ',', '.') }}</p></div>
                                    <div class="bg-white p-2"><p class="text-[10px] font-bold uppercase text-muted">Atraso total</p><p class="tabular text-sm font-extrabold {{ $cr['atraso_total'] > 0 ? 'text-red-600' : 'text-ink' }}">${{ number_format($cr['atraso_total'], 2, ',', '.') }}</p></div>
                                    <div class="bg-white p-2"><p class="text-[10px] font-bold uppercase text-muted">% Avance</p><p class="tabular text-sm font-extrabold text-ink">{{ rtrim(rtrim(number_format($cr['avance'], 1, ',', '.'), '0'), ',') }}%</p></div>
                                    <div class="bg-white p-2"><p class="text-[10px] font-bold uppercase text-muted">Cuotas atrasadas</p><p class="tabular text-sm font-extrabold {{ $cr['cuotas_atrasadas'] > 0 ? 'text-amber-700' : 'text-ink' }}">{{ $cr['cuotas_atrasadas'] }}@if ($cr['dias_atraso'] > 0) <span class="text-[10px] font-normal text-muted">({{ $cr['dias_atraso'] }}d)</span>@endif</p></div>
                                    <div class="bg-white p-2"><p class="text-[10px] font-bold uppercase text-muted">Cobro promedio</p><p class="tabular text-sm font-extrabold text-ink">${{ number_format($cr['cobro_promedio'], 2, ',', '.') }}</p></div>
                                    <div class="bg-white p-2"><p class="text-[10px] font-bold uppercase text-muted">Último cobro</p><p class="text-sm font-extrabold text-ink">{{ $cr['ultimo_cobro_fecha'] ?? '—' }}@if ($cr['ultimo_cobro_fecha']) <span class="block text-[10px] font-normal text-muted">${{ number_format($cr['ultimo_cobro_monto'], 0, ',', '.') }}</span>@endif</p></div>
                                    <div class="bg-white p-2"><p class="text-[10px] font-bold uppercase text-muted">Fin de acuerdo</p><p class="text-sm font-extrabold text-ink">{{ $cr['fin_acuerdo'] ?? '—' }}</p></div>
                                </div>
                                <div class="flex flex-wrap gap-x-4 gap-y-1 px-4 py-2 text-[11px] text-muted">
                                    <span>Solicitado: <b class="text-graphite">{{ $cr['fecha_solicitud'] ?? '—' }}</b></span>
                                    <span>1ª cuota: <b class="text-graphite">{{ $cr['primera_cuota'] ?? '—' }}</b></span>
                                    <span>Cuota: <b class="text-graphite">${{ number_format($cr['cuota'], 2, ',', '.') }}</b> ({{ ucfirst((string) $cr['modalidad']) }})</span>
                                    <span>Anticipo: <b class="text-graphite">${{ number_format($cr['anticipo'], 2, ',', '.') }}</b></span>
                                    <span>Vendedor: <b class="text-graphite">{{ $cr['vendedor'] }}</b></span>
                                </div>
                                @if (! empty($cr['productos']))
                                    <div class="px-4 pb-2 text-[11px] text-graphite">
                                        <b>Productos:</b>
                                        @foreach ($cr['productos'] as $pr){{ $pr['nombre'] }} (x{{ $pr['cantidad'] }})@if (! $loop->last), @endif @endforeach
                                    </div>
                                @endif
                            </details>

                            @if (! empty($cr['movimientos']))
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-sm">
                                        <thead><tr class="text-[11px] uppercase tracking-wide text-muted"><th class="px-4 py-2 font-bold">Fecha</th><th class="px-4 py-2 font-bold">Concepto</th><th class="px-4 py-2 text-right font-bold">Debe</th><th class="px-4 py-2 text-right font-bold">Haber</th></tr></thead>
                                        <tbody class="tabular">
                                            @foreach ($cr['movimientos'] as $m)
                                                <tr class="border-t border-gray-100">
                                                    <td class="px-4 py-2 text-graphite">{{ $m['fecha'] }}</td>
                                                    <td class="px-4 py-2 font-semibold text-ink">{{ $m['concepto'] }}</td>
                                                    <td class="px-4 py-2 text-right {{ $m['tipo'] === 'debe' ? 'font-bold text-danger' : 'text-gray-300' }}">{{ $m['tipo'] === 'debe' ? '$' . number_format($m['monto'], 2, ',', '.') : '—' }}</td>
                                                    <td class="px-4 py-2 text-right {{ $m['tipo'] === 'haber' ? 'font-bold text-success' : 'text-gray-300' }}">{{ $m['tipo'] === 'haber' ? '$' . number_format($m['monto'], 2, ',', '.') : '—' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="py-8 text-center text-sm text-muted">Este cliente no tiene créditos.</p>
                    @endforelse
                </div>

            @elseif ($tab === 'cuotas')
                @php
                    $cr = $cliente['credito'] ?? ['a_vencer' => 0, 'vencido' => 0, 'mora' => 0, 'total' => 0];
                @endphp
                <div class="grid grid-cols-2 gap-3 p-5 sm:grid-cols-4">
                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-3"><p class="text-[11px] font-bold uppercase text-muted">A vencer</p><p class="tabular text-lg font-extrabold text-ink">${{ number_format($cr['a_vencer'], 2, ',', '.') }}</p></div>
                    <div class="rounded-xl border border-amber-100 bg-amber-50 p-3"><p class="text-[11px] font-bold uppercase text-amber-700">Vencido</p><p class="tabular text-lg font-extrabold text-amber-700">${{ number_format($cr['vencido'], 2, ',', '.') }}</p></div>
                    <div class="rounded-xl border border-red-100 bg-red-50 p-3"><p class="text-[11px] font-bold uppercase text-danger">Mora acumulada</p><p class="tabular text-lg font-extrabold text-danger">${{ number_format($cr['mora'], 2, ',', '.') }}</p></div>
                    <div class="rounded-xl border border-brand-soft bg-brand-soft/40 p-3"><p class="text-[11px] font-bold uppercase text-brand">Total a cobrar</p><p class="tabular text-lg font-extrabold text-brand">${{ number_format($cr['total'], 2, ',', '.') }}</p></div>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="text-[11px] uppercase tracking-wide text-muted"><th class="px-5 py-3 font-bold">Cuota</th><th class="px-5 py-3 font-bold">Venta</th><th class="px-5 py-3 font-bold">Vence</th><th class="px-5 py-3 text-right font-bold">Monto</th><th class="px-5 py-3 font-bold">Estado</th><th class="px-5 py-3 text-right font-bold">A cobrar</th><th class="px-5 py-3"></th></tr></thead>
                    <tbody class="tabular">
                        @forelse ($cliente['cuotas'] as $q)
                            <tr class="border-t border-gray-100 {{ $q['dias_atraso'] > 0 ? 'bg-amber-50/40' : '' }}">
                                <td class="px-5 py-3 font-bold text-ink">#{{ $q['numero'] }}</td>
                                <td class="px-5 py-3 text-graphite">{{ $q['venta'] }}</td>
                                <td class="px-5 py-3 text-graphite">{{ $q['venc'] }}</td>
                                <td class="px-5 py-3 text-right text-ink">${{ number_format($q['monto'], 2, ',', '.') }}</td>
                                <td class="px-5 py-3">
                                    @if ($q['estado'] === 'cobrada')
                                        <span class="rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-bold text-success">Cobrada</span>
                                    @elseif ($q['dias_atraso'] > 0)
                                        <span class="rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-bold text-danger">Vencida · {{ $q['dias_atraso'] }} d (mora ${{ number_format($q['mora'], 2, ',', '.') }})</span>
                                    @else
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-bold text-graphite">Pendiente</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right font-bold text-ink">{{ $q['estado'] === 'cobrada' ? '—' : '$' . number_format($q['total_cobrar'], 2, ',', '.') }}</td>
                                <td class="px-5 py-3 text-right">
                                    @if ($q['estado'] !== 'cobrada')
                                        <button wire:click="registrarCobroCuota({{ $q['id'] }})" wire:confirm="¿Registrar el cobro de la cuota #{{ $q['numero'] }} por ${{ number_format($q['total_cobrar'], 2, ',', '.') }}?" class="rounded-lg bg-brand px-3 py-1 text-xs font-bold text-white hover:bg-brand-dark">Cobrar</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-8 text-center text-sm text-muted">Este cliente no tiene cuotas de crédito.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>

            @elseif ($tab === 'compras')
                <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="text-[11px] uppercase tracking-wide text-muted"><th class="px-5 py-3 font-bold">Fecha</th><th class="px-5 py-3 font-bold">Comprobante</th><th class="px-5 py-3 font-bold">Forma de pago</th><th class="px-5 py-3 text-right font-bold">Monto</th></tr></thead>
                    <tbody class="tabular">
                        @foreach ($cliente['compras'] as $c)
                            <tr class="border-t border-gray-100">
                                <td class="px-5 py-3 text-graphite">{{ $c['fecha'] }}</td>
                                <td class="px-5 py-3 font-bold text-ink">{{ $c['comp'] }}</td>
                                <td class="px-5 py-3"><span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-bold text-graphite">{{ $c['pago'] }}</span></td>
                                <td class="px-5 py-3 text-right font-bold text-ink">${{ number_format($c['monto'], 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>

            @elseif ($tab === 'pagos')
                <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="text-[11px] uppercase tracking-wide text-muted"><th class="px-5 py-3 font-bold">Fecha</th><th class="px-5 py-3 font-bold">Concepto / Medio</th><th class="px-5 py-3 text-right font-bold">Monto</th></tr></thead>
                    <tbody class="tabular">
                        @forelse (collect($cliente['movimientos'])->where('tipo', 'haber') as $m)
                            <tr class="border-t border-gray-100">
                                <td class="px-5 py-3 text-graphite">{{ $m['fecha'] }}</td>
                                <td class="px-5 py-3 font-semibold text-ink">{{ $m['concepto'] }}</td>
                                <td class="px-5 py-3 text-right font-bold text-success">${{ number_format($m['monto'], 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-5 py-8 text-center text-sm text-muted">Sin pagos registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>

            @elseif ($tab === 'domicilios')
                {{-- Domicilios MÚLTIPLES: casa, negocio, casa de un familiar… (entrega y/o cobro) --}}
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3">
                    <p class="text-xs text-muted">Dónde se <b>entrega</b> la mercadería y dónde se <b>cobra</b> la cuota. El domicilio <b>principal</b> es el que sale por defecto.</p>
                    @puede('gestionar_clientes')
                        <button type="button" wire:click="nuevoDomicilio" class="flex items-center gap-1.5 rounded-lg bg-brand px-3 py-2 text-xs font-bold text-white transition hover:bg-brand-dark">
                            <span class="material-symbols-outlined text-[18px]">add_location_alt</span> Agregar domicilio
                        </button>
                    @endpuede
                </div>
                <div class="divide-y divide-gray-100">
                    @forelse ($cliente['domicilios'] as $d)
                        @php
                            $usoCls = match ($d['uso']) {
                                'entrega' => 'bg-sky-100 text-sky-700',
                                'cobro' => 'bg-purple-100 text-purple-700',
                                default => 'bg-gray-100 text-graphite',
                            };
                        @endphp
                        <div class="flex flex-wrap items-start justify-between gap-3 px-5 py-4 {{ $d['activo'] ? '' : 'opacity-60' }}" wire:key="dom-{{ $d['id'] }}">
                            <div class="min-w-0 flex-1">
                                <p class="flex flex-wrap items-center gap-2 text-sm font-bold text-ink">
                                    <span class="material-symbols-outlined text-[18px] text-brand">{{ $d['principal'] ? 'home_pin' : 'location_on' }}</span>
                                    {{ $d['etiqueta'] }}
                                    @if ($d['principal'])<span class="rounded-full bg-brand-soft px-2 py-0.5 text-[10px] font-extrabold uppercase text-brand">Principal</span>@endif
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase {{ $usoCls }}">{{ $d['uso_label'] }}</span>
                                    @if (! $d['activo'])<span class="rounded-full bg-gray-200 px-2 py-0.5 text-[10px] font-bold uppercase text-graphite">Baja</span>@endif
                                </p>
                                <p class="mt-0.5 text-sm text-graphite">{{ $d['completa'] }}</p>
                                @if ($d['referencia'])<p class="text-xs text-muted">Referencia: {{ $d['referencia'] }}</p>@endif
                                <p class="mt-0.5 text-xs text-muted">
                                    @if ($d['contacto'])Recibe: <b>{{ $d['contacto'] }}</b> · @endif
                                    @if ($d['telefono']){{ $d['telefono'] }} · @endif
                                    @if ($d['zona'])Zona {{ $d['zona'] }}@else <span class="text-amber-600">Sin zona</span>@endif
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-wrap items-center gap-2">
                                <a href="{{ $d['maps'] }}" target="_blank" rel="noopener" class="flex items-center gap-1 rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs font-bold text-graphite hover:bg-gray-50">
                                    <span class="material-symbols-outlined text-[16px]">map</span> {{ $d['geo'] ? 'Ver en mapa' : 'Buscar en mapa' }}
                                </a>
                                @puede('gestionar_clientes')
                                    @if (! $d['principal'] && $d['activo'])
                                        <button type="button" wire:click="marcarPrincipal({{ $d['id'] }})" class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs font-bold text-graphite hover:bg-gray-50">Hacer principal</button>
                                    @endif
                                    <button type="button" wire:click="editarDomicilio({{ $d['id'] }})" class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs font-bold text-graphite hover:bg-gray-50">Editar</button>
                                    <button type="button" wire:click="eliminarDomicilio({{ $d['id'] }})" class="rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-bold text-red-600 hover:bg-red-50">Eliminar</button>
                                @endpuede
                            </div>
                        </div>
                    @empty
                        <p class="px-5 py-8 text-center text-sm text-muted">Sin domicilios cargados. Agregá el de la casa, el del negocio o el de un familiar.</p>
                    @endforelse
                </div>

            @elseif ($tab === 'cheques')
                <div class="divide-y divide-gray-100">
                    @forelse ($cliente['cheques'] as $i => $ch)
                        @php
                            $estCls = match ($ch['estado']) {
                                'acreditado' => 'bg-green-100 text-green-700',
                                'depositado' => 'bg-sky-100 text-sky-700',
                                'rechazado' => 'bg-red-100 text-red-700',
                                default => 'bg-amber-100 text-amber-700',
                            };
                            $deposito = \App\Models\ChequeCliente::calcularDeposito($ch['venc']);
                        @endphp
                        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5" wire:key="ch-{{ $i }}">
                            <div>
                                <p class="text-sm font-bold text-ink">N° {{ $ch['num'] }} <span class="font-medium text-muted">· {{ $ch['banco'] }}</span></p>
                                <p class="text-xs text-graphite">Vence {{ \Carbon\Carbon::parse($ch['venc'])->format('d/m/Y') }} · <span class="font-semibold">Depósito {{ $deposito->format('d/m/Y') }}</span> (venc. + 1 día hábil)</p>
                                @if (! empty($ch['motivo']))
                                    <p class="text-xs font-semibold text-red-600">Motivo: {{ $ch['motivo'] }}</p>
                                @endif
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="tabular text-sm font-extrabold text-ink">${{ number_format($ch['monto'], 2, ',', '.') }}</span>
                                <span class="inline-block rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $estCls }}">{{ $ch['estado'] }}</span>
                                @if ($ch['estado'] === 'pendiente')
                                    <button wire:click="depositarCheque({{ $ch['id'] }})" class="rounded-lg bg-success px-3 py-1.5 text-xs font-bold text-white hover:brightness-95">Depositar</button>
                                    <button wire:click="rechazarCheque({{ $ch['id'] }})" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-bold text-graphite hover:bg-gray-50">Rechazar</button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="px-5 py-8 text-center text-sm text-muted">Sin cheques registrados.</p>
                    @endforelse
                </div>

            @else
                <div class="divide-y divide-gray-100">
                    @forelse ($cliente['devoluciones'] as $d)
                        <div class="flex items-center justify-between px-5 py-3.5">
                            <div>
                                <p class="text-sm font-bold text-ink">{{ $d['producto'] }}</p>
                                <p class="text-xs text-graphite">{{ $d['fecha'] }} · <span class="font-semibold text-red-600">Motivo: {{ $d['motivo'] }}</span></p>
                            </div>
                            <div class="text-right">
                                <p class="tabular text-sm font-extrabold text-ink">${{ number_format($d['monto'], 2, ',', '.') }}</p>
                                <span class="inline-block rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold uppercase text-graphite">{{ $d['estado'] }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="px-5 py-8 text-center text-sm text-muted">Sin devoluciones registradas.</p>
                    @endforelse
                </div>
            @endif
        </x-panel>

    @else
        {{-- ====================== LISTA (ABM) ====================== --}}
        <div class="flex items-center justify-between">
            <div><h1 class="text-2xl font-extrabold text-ink">Clientes</h1><p class="text-sm text-muted">Cuentas, riesgo crediticio y devoluciones</p></div>
            @puede('gestionar_clientes')
                <button wire:click="nuevoCliente" class="flex items-center gap-2 rounded-lg bg-brand px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-dark">
                    <span class="material-symbols-outlined text-[20px]">person_add</span> Nuevo cliente
                </button>
            @endpuede
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <x-kpi-card variant="white" title="Clientes" :value="$stats['total']" icon="groups" subtitle="Registrados" />
            <x-kpi-card variant="red"   title="Riesgo Alto" :value="$stats['riesgo_alto']" icon="warning" subtitle="Requieren atención" />
            <x-kpi-card variant="brand" title="Deuda Total" :value="'$' . number_format($stats['deuda_total'], 0, ',', '.')" icon="account_balance_wallet" />
        </div>

        <x-panel title="Listado de clientes">
            <x-slot:actions><span class="text-xs font-semibold text-muted">{{ count($filas) }} resultado(s)</span></x-slot:actions>
            <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-5">
                <div class="relative min-w-[240px] flex-1">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[20px] text-muted">search</span>
                    <input type="text" wire:model.live.debounce.300ms="buscar" placeholder="Buscar por nombre o documento..." class="w-full rounded-full border border-gray-200 bg-gray-50 py-2 pl-10 pr-4 text-sm font-medium outline-none transition focus:border-brand focus:bg-white focus:ring-2 focus:ring-brand/20" />
                </div>
                <div class="flex overflow-hidden rounded-lg border border-gray-200 text-sm font-bold">
                    @foreach (['todos' => 'Todos', 'bajo' => 'Bajo', 'medio' => 'Medio', 'alto' => 'Alto'] as $val => $lbl)
                        <button wire:click="$set('riesgo', '{{ $val }}')" class="px-3 py-2 transition {{ $riesgo === $val ? 'bg-brand text-white' : 'text-graphite hover:bg-gray-50' }}">{{ $lbl }}</button>
                    @endforeach
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="text-[11px] uppercase tracking-wide text-muted"><th class="px-5 py-3 font-bold">Cliente</th><th class="px-5 py-3 font-bold">Riesgo</th><th class="px-5 py-3 font-bold">Semáforo</th><th class="px-5 py-3 text-right font-bold">Límite</th><th class="px-5 py-3 text-right font-bold">Saldo</th><th class="px-5 py-3 text-center font-bold">Uso</th><th class="px-5 py-3 text-right font-bold">Acciones</th></tr></thead>
                    <tbody class="tabular">
                        @forelse ($filas as $i => $c)
                            @php $u = $c['limite'] > 0 ? round($c['saldo'] / $c['limite'] * 100) : 0; @endphp
                            <tr class="border-t border-gray-100 {{ $i % 2 ? 'bg-gray-50/50' : '' }} hover:bg-brand-soft/40" wire:key="cli-{{ $c['id'] }}">
                                <td class="px-5 py-3">
                                    @puede('ver_cuenta_cliente')
                                        <button wire:click="abrir({{ $c['id'] }})" class="text-left font-bold text-ink hover:text-brand hover:underline">{{ $c['nombre'] }}</button>
                                    @else
                                        <span class="font-bold text-ink">{{ $c['nombre'] }}</span>
                                    @endpuede
                                    <p class="text-xs text-muted">{{ $c['doc'] }}</p>
                                </td>
                                <td class="px-5 py-3"><span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wide {{ $riesgoBadge[$c['riesgo']] }}">{{ $c['riesgo'] }}</span></td>
                                <td class="px-5 py-3">
                                    @php [$lDot, $lTxt, $lBg] = \App\Support\Semaforo::clases($c['semaforo']['estado']); @endphp
                                    <span class="inline-flex items-center gap-1.5 rounded-full {{ $lBg }} px-2 py-0.5 text-[11px] font-bold {{ $lTxt }}" title="{{ \App\Support\Semaforo::label($c['semaforo']['estado']) }}">
                                        <span class="h-2 w-2 rounded-full {{ $lDot }}"></span> {{ \App\Support\Semaforo::label($c['semaforo']['estado']) }}@if ($c['semaforo']['vencidas'] > 0) · {{ $c['semaforo']['vencidas'] }}@endif
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right text-graphite">${{ number_format($c['limite'], 0, ',', '.') }}</td>
                                <td class="px-5 py-3 text-right font-bold text-ink">${{ number_format($c['saldo'], 0, ',', '.') }}</td>
                                <td class="px-5 py-3 text-center {{ $u >= 90 ? 'font-bold text-danger' : 'text-graphite' }}">{{ $u }}%</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        @puede('ver_cuenta_cliente')
                                            <button wire:click="abrir({{ $c['id'] }})" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-graphite transition hover:bg-gray-100 hover:text-brand" title="Ver ficha"><span class="material-symbols-outlined text-[20px]">visibility</span></button>
                                        @endpuede
                                        @puede('gestionar_clientes')
                                            <button wire:click="editarCliente({{ $c['id'] }})" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-graphite transition hover:bg-gray-100 hover:text-brand" title="Editar"><span class="material-symbols-outlined text-[20px]">edit</span></button>
                                        @endpuede
                                        @puede('ver_cuenta_cliente') @else
                                            <span class="text-[11px] font-bold text-muted">Sin acceso</span>
                                        @endpuede
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-10 text-center text-sm text-muted"><span class="material-symbols-outlined mb-1 block text-3xl">groups</span>No hay clientes con esos filtros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-panel>
    @endif

    {{-- ===== Modal alta / edición de cliente ===== --}}
    @if ($modal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" wire:click="$set('modal', false)"></div>
            <div class="relative z-10 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 class="text-base font-extrabold text-ink">{{ $editId ? 'Editar cliente' : 'Nuevo cliente' }}</h3>
                    <button wire:click="$set('modal', false)" class="text-muted hover:text-danger"><span class="material-symbols-outlined">close</span></button>
                </div>

                <form wire:submit="guardarCliente" class="p-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div class="sm:col-span-4">
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Nombre y apellido / Razón social</label>
                            <input type="text" wire:model="fNombre" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                            @error('fNombre') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Tipo doc</label>
                            <select wire:model="fTipoDoc" class="w-full rounded-lg border border-gray-200 px-2 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                                <option>CUIT</option><option>CUIL</option><option>DNI</option>
                            </select>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Documento</label>
                            <input type="text" wire:model="fDoc" placeholder="30-00000000-0" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Teléfono</label>
                            <input type="text" wire:model="fTel" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Email</label>
                            <input type="email" wire:model="fEmail" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                            @error('fEmail') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-4">
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Dirección</label>
                            <input type="text" wire:model="fDir" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Límite de crédito</label>
                            <input type="number" step="0.01" min="0" wire:model="fLimite" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                            @error('fLimite') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Riesgo crediticio</label>
                            <select wire:model="fRiesgo" class="w-full rounded-lg border border-gray-200 px-2 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                                <option value="bajo">Bajo</option><option value="medio">Medio</option><option value="alto">Alto</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <button type="button" wire:click="$set('modal', false)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold text-graphite hover:bg-gray-50">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-dark">{{ $editId ? 'Guardar cambios' : 'Crear cliente' }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ===== Modal alta / edición de DOMICILIO ===== --}}
    @if ($modalDom)
        <div class="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto p-4 sm:items-center">
            <div class="absolute inset-0 bg-black/50" wire:click="$set('modalDom', false)"></div>
            <div class="relative z-10 my-auto max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 class="text-base font-extrabold text-ink">{{ $domId ? 'Editar domicilio' : 'Nuevo domicilio' }}</h3>
                    <button type="button" wire:click="$set('modalDom', false)" class="text-muted hover:text-danger"><span class="material-symbols-outlined">close</span></button>
                </div>

                <form wire:submit="guardarDomicilio" class="p-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Etiqueta</label>
                            <input type="text" wire:model="dEtiqueta" list="etiquetas-dom" placeholder="Casa · Negocio · Casa de la hija" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                            <datalist id="etiquetas-dom"><option value="Casa"></option><option value="Negocio"></option><option value="Trabajo"></option><option value="Casa de un familiar"></option><option value="Depósito"></option></datalist>
                            @error('dEtiqueta') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Uso</label>
                            <select wire:model="dUso" class="w-full rounded-lg border border-gray-200 px-2 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                                @foreach (\App\Models\DomicilioCliente::USOS as $k => $lbl)
                                    <option value="{{ $k }}">{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-4">
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Dirección</label>
                            <input type="text" wire:model="dDireccion" placeholder="Calle y número" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                            @error('dDireccion') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Localidad</label>
                            <input type="text" wire:model="dLocalidad" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Provincia</label>
                            <input type="text" wire:model="dProvincia" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                        </div>
                        <div class="sm:col-span-4">
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Referencia (cómo llegar)</label>
                            <input type="text" wire:model="dReferencia" placeholder="Entre calles, color de la casa, portón…" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Quién recibe</label>
                            <input type="text" wire:model="dContacto" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Teléfono del domicilio</label>
                            <input type="text" wire:model="dTelefono" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                        </div>
                        <div class="sm:col-span-4">
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Zona de cobranza</label>
                            <select wire:model="dZonaId" class="w-full rounded-lg border border-gray-200 px-2 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                                <option value="">— Sin zona —</option>
                                @foreach ($zonas as $z)
                                    <option value="{{ $z->id }}">{{ $z->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Latitud <span class="font-normal normal-case">(opcional)</span></label>
                            <input type="text" wire:model="dLatitud" placeholder="-29.1622" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                            @error('dLatitud') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Longitud <span class="font-normal normal-case">(opcional)</span></label>
                            <input type="text" wire:model="dLongitud" placeholder="-67.4966" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                            @error('dLongitud') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-4">
                            <label class="flex items-center gap-2 text-sm font-semibold text-graphite">
                                <input type="checkbox" wire:model="dPrincipal" class="h-4 w-4 rounded border-gray-300 text-brand focus:ring-brand" />
                                Domicilio principal <span class="text-xs font-normal text-muted">(se usa por defecto y actualiza la dirección del cliente)</span>
                            </label>
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <button type="button" wire:click="$set('modalDom', false)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold text-graphite hover:bg-gray-50">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-dark">{{ $domId ? 'Guardar cambios' : 'Agregar domicilio' }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
