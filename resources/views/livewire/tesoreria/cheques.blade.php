<div class="space-y-6">

    @php
        $money = fn ($n) => '$' . number_format((float) $n, 2, ',', '.');
        $money0 = fn ($n) => '$' . number_format((float) $n, 0, ',', '.');
        $dia = function ($f) use ($hoy) {
            $d = (int) $hoy->diffInDays($f, false);
            return $d < 0 ? ['vencido', 'text-red-700'] : ($d === 0 ? ['HOY', 'text-green-700'] : ($d === 1 ? ['MAÑANA', 'text-amber-700'] : ["en {$d} días", 'text-muted']));
        };
    @endphp

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-ink">Cheques</h1>
            <p class="text-sm text-muted">Cartera de cheques propios y de terceros · qué se deposita y qué se debita</p>
        </div>
        @puede('cargar_cheques')
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="nuevoTercero" class="flex items-center gap-1.5 rounded-lg bg-brand px-3.5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-dark">
                    <span class="material-symbols-outlined text-[18px]">call_received</span> Ingresar cheque recibido
                </button>
                <button type="button" wire:click="nuevoPropio" class="flex items-center gap-1.5 rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm font-bold text-graphite transition hover:bg-gray-50">
                    <span class="material-symbols-outlined text-[18px]">call_made</span> Emitir cheque propio
                </button>
            </div>
        @endpuede
    </div>

    @if ($mensaje)
        <div class="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm font-semibold text-green-700">
            <span class="material-symbols-outlined text-[18px]">check_circle</span> {{ $mensaje }}
        </div>
    @endif

    {{-- ===== KPIs: cartera + lo de HOY y MAÑANA ===== --}}
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi-card variant="brand" title="En cartera" :value="$money0($kpis['cartera_monto'])" icon="account_balance_wallet" :subtitle="$kpis['cartera_cant'] . ' cheque(s) de terceros'" />
        <x-kpi-card variant="blue" title="A depositar hoy" :value="$money0($kpis['depositar_hoy'])" icon="savings" :subtitle="$kpis['depositar_hoy_cant'] . ' cheque(s), incl. atrasados'" />
        <x-kpi-card variant="beige" title="Para mañana" :value="$money0($kpis['manana_ingreso'] - $kpis['manana_egreso'])" icon="event_upcoming" :subtitle="'Entra ' . $money0($kpis['manana_ingreso']) . ' · Sale ' . $money0($kpis['manana_egreso'])" />
        <x-kpi-card variant="red" title="Propios a debitar" :value="$money0($kpis['propios_monto'])" icon="call_made" :subtitle="$kpis['propios_cant'] . ' emitido(s) sin debitar'" />
    </div>

    {{-- ===== Alerta "cheques para mañana" (pedido explícito del cliente) ===== --}}
    @if ($kpis['manana_egreso_cant'] > 0 || $kpis['manana_ingreso_cant'] > 0)
        <div class="rounded-xl border-2 border-amber-200 bg-amber-50 p-4">
            <h3 class="mb-1 flex items-center gap-1.5 text-sm font-extrabold uppercase text-amber-700">
                <span class="material-symbols-outlined text-[18px]">schedule</span> Cheques para MAÑANA
            </h3>
            <p class="text-sm text-amber-800">
                @if ($kpis['manana_egreso_cant'] > 0)
                    <b>{{ $kpis['manana_egreso_cant'] }}</b> cheque(s) propio(s) se debitan por <b>{{ $money($kpis['manana_egreso']) }}</b>.
                @endif
                @if ($kpis['manana_ingreso_cant'] > 0)
                    <b>{{ $kpis['manana_ingreso_cant'] }}</b> cheque(s) de terceros se depositan por <b>{{ $money($kpis['manana_ingreso']) }}</b>.
                @endif
            </p>
        </div>
    @endif

    {{-- ===== Tabs ===== --}}
    <x-panel>
        <div class="flex flex-wrap items-center gap-1 border-b border-gray-100 px-3">
            @foreach (['cartera' => 'Cartera (terceros)', 'propios' => 'Propios (emitidos)', 'calendario' => 'Calendario'] as $t => $lbl)
                <button type="button" wire:click="setTab('{{ $t }}')" class="-mb-px border-b-2 px-4 py-3 text-sm font-bold transition {{ $tab === $t ? 'border-brand text-brand' : 'border-transparent text-graphite hover:text-brand' }}">{{ $lbl }}</button>
            @endforeach
        </div>

        @if ($tab !== 'calendario')
            <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 px-5 py-3">
                <div class="relative min-w-[220px] flex-1">
                    <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-[18px] text-muted">search</span>
                    <input type="text" wire:model.live.debounce.300ms="buscar" placeholder="Buscar por N°, banco o nombre..."
                           class="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                </div>
                <select wire:model.live="filtroEstado" class="rounded-lg border border-gray-200 px-2 py-2 text-sm outline-none focus:border-brand">
                    <option value="todos">Todos los estados</option>
                    @if ($tab === 'cartera')
                        @foreach (\App\Models\ChequeCliente::ESTADOS as $k => $lbl)
                            <option value="{{ $k }}">{{ $lbl }}</option>
                        @endforeach
                    @else
                        <option value="pendiente">Pendiente</option>
                        <option value="cobrado">Debitado</option>
                        <option value="rechazado">Rechazado</option>
                    @endif
                </select>
            </div>
        @endif

        {{-- ============ CARTERA DE TERCEROS ============ --}}
        @if ($tab === 'cartera')
            <div class="divide-y divide-gray-100">
                @forelse ($terceros as $ch)
                    @php
                        [$txtDia, $clsDia] = $dia($ch->fechaEfectiva() ?? $ch->fecha_vencimiento);
                        $estCls = match ($ch->estado) {
                            'acreditado' => 'bg-green-100 text-green-700',
                            'depositado' => 'bg-sky-100 text-sky-700',
                            'rechazado' => 'bg-red-100 text-red-700',
                            'endosado' => 'bg-purple-100 text-purple-700',
                            default => 'bg-amber-100 text-amber-700',
                        };
                        $pendienteEndoso = in_array($ch->id, $endosoEnCurso, true);
                    @endphp
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5" wire:key="t-{{ $ch->id }}">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-ink">N° {{ $ch->numero }} <span class="font-medium text-muted">· {{ $ch->banco ?: 'sin banco' }}</span></p>
                            <p class="text-xs text-graphite">
                                De {{ $ch->cliente?->nombre ?? '—' }} · Vence {{ $ch->fecha_vencimiento?->format('d/m/Y') }}
                                @if ($ch->fecha_deposito) · <span class="font-semibold">Depositar {{ $ch->fecha_deposito->format('d/m/Y') }}</span> @endif
                                @if ($ch->estado === 'pendiente') <span class="font-bold {{ $clsDia }}">· {{ $txtDia }}</span> @endif
                            </p>
                            @if ($ch->estado === 'rechazado' && $ch->motivo_rechazo)
                                <p class="text-xs font-semibold text-red-600">Motivo: {{ $ch->motivo_rechazo }}</p>
                            @endif
                            @if ($ch->estado === 'endosado')
                                <p class="text-xs font-semibold text-purple-700">Endosado a {{ $ch->endosadoA?->nombre ?? '—' }} el {{ $ch->endosado_at?->format('d/m/Y') }}</p>
                            @endif
                            @if ($pendienteEndoso && $ch->estado === 'pendiente')
                                <p class="text-xs font-semibold text-amber-700">Endoso pedido — esperando autorización</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="tabular text-sm font-extrabold text-ink">{{ $money($ch->monto) }}</span>
                            <span class="inline-block rounded-full px-2 py-0.5 text-[10px] font-bold uppercase {{ $estCls }}">{{ $ch->estadoLabel() }}</span>
                            @puede('cargar_cheques')
                                @if ($ch->estado === 'pendiente' && ! $pendienteEndoso)
                                    <button type="button" wire:click="depositar({{ $ch->id }})" class="rounded-lg bg-success px-3 py-1.5 text-xs font-bold text-white hover:brightness-95">Depositar</button>
                                    <button type="button" wire:click="pedirEndoso({{ $ch->id }})" class="rounded-lg border border-purple-200 px-3 py-1.5 text-xs font-bold text-purple-700 hover:bg-purple-50">Endosar</button>
                                    <button type="button" wire:click="pedirRechazo({{ $ch->id }})" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-bold text-graphite hover:bg-gray-50">Rechazar</button>
                                @elseif ($ch->estado === 'depositado')
                                    <button type="button" wire:click="acreditar({{ $ch->id }})" class="rounded-lg bg-success px-3 py-1.5 text-xs font-bold text-white hover:brightness-95">Acreditar</button>
                                    <button type="button" wire:click="pedirRechazo({{ $ch->id }})" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-bold text-graphite hover:bg-gray-50">Rebotó</button>
                                @endif
                            @endpuede
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-muted">No hay cheques de terceros con este filtro.</p>
                @endforelse
            </div>

        {{-- ============ CHEQUES PROPIOS ============ --}}
        @elseif ($tab === 'propios')
            <div class="divide-y divide-gray-100">
                @forelse ($propios as $ch)
                    @php
                        [$txtDia, $clsDia] = $dia($ch->fecha_vencimiento);
                        $estCls = match ($ch->estado) {
                            'cobrado' => 'bg-gray-200 text-graphite',
                            'rechazado' => 'bg-red-100 text-red-700',
                            default => 'bg-amber-100 text-amber-700',
                        };
                    @endphp
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 {{ $ch->estado === 'pendiente' && $ch->fecha_vencimiento?->isToday() ? 'bg-red-50' : ($ch->estado === 'pendiente' && $ch->fecha_vencimiento?->isTomorrow() ? 'bg-amber-50' : '') }}" wire:key="p-{{ $ch->id }}">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-ink">N° {{ $ch->numero }} <span class="font-medium text-muted">· {{ $ch->banco ?: 'sin banco' }}</span></p>
                            <p class="text-xs text-graphite">
                                A {{ $ch->proveedor?->nombre ?? '—' }} · Débito {{ $ch->fecha_vencimiento?->format('d/m/Y') }}
                                @if ($ch->estado === 'pendiente') <span class="font-bold {{ $clsDia }}">· {{ $txtDia }}</span> @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="tabular text-sm font-extrabold text-ink">{{ $money($ch->monto) }}</span>
                            <span class="inline-block rounded-full px-2 py-0.5 text-[10px] font-bold uppercase {{ $estCls }}">{{ $ch->estado === 'cobrado' ? 'debitado' : $ch->estado }}</span>
                            @puede('cargar_cheques')
                                @if ($ch->estado === 'pendiente')
                                    <button type="button" wire:click="debitar({{ $ch->id }})" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-bold text-graphite hover:bg-gray-50">Marcar debitado</button>
                                @endif
                            @endpuede
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-muted">No hay cheques propios con este filtro.</p>
                @endforelse
            </div>

        {{-- ============ CALENDARIO ============ --}}
        @else
            <div class="p-5">
                <p class="mb-4 text-xs text-muted">Próximos 30 días — solo los días con movimiento. Lo vencido/atrasado aparece en HOY.</p>
                <div class="space-y-3">
                    @forelse ($calendario as $d)
                        @php $esHoy = $d['fecha']->isToday(); $esManana = $d['fecha']->isTomorrow(); @endphp
                        <div class="overflow-hidden rounded-xl border {{ $esHoy ? 'border-brand bg-brand-soft/30' : ($esManana ? 'border-amber-200 bg-amber-50/50' : 'border-gray-100') }}">
                            <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5">
                                <p class="text-sm font-extrabold text-ink">
                                    {{ $esHoy ? 'HOY' : ($esManana ? 'MAÑANA' : ucfirst($d['fecha']->locale('es')->isoFormat('dddd D/MM'))) }}
                                    @if ($esHoy || $esManana)<span class="ml-1 text-xs font-semibold text-muted">{{ $d['fecha']->format('d/m/Y') }}</span>@endif
                                </p>
                                <p class="flex flex-wrap gap-3 text-xs font-bold">
                                    @if ($d['total_ingreso'] > 0)<span class="text-success">↓ entra {{ $money($d['total_ingreso']) }}</span>@endif
                                    @if ($d['total_egreso'] > 0)<span class="text-danger">↑ sale {{ $money($d['total_egreso']) }}</span>@endif
                                    <span class="text-graphite">neto {{ $money($d['total_ingreso'] - $d['total_egreso']) }}</span>
                                </p>
                            </div>
                            <div class="divide-y divide-gray-100 border-t border-gray-100 bg-white">
                                @foreach ($d['ingresos'] as $c)
                                    <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2 text-xs">
                                        <span class="text-graphite"><span class="font-bold text-success">↓</span> Depositar <b>N° {{ $c['numero'] }}</b> · {{ $c['banco'] ?: 's/banco' }} · de {{ $c['quien'] }} @if ($c['atrasado'])<span class="font-bold text-red-600">(atrasado)</span>@endif</span>
                                        <span class="tabular font-bold text-success">{{ $money($c['monto']) }}</span>
                                    </div>
                                @endforeach
                                @foreach ($d['egresos'] as $c)
                                    <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2 text-xs">
                                        <span class="text-graphite"><span class="font-bold text-danger">↑</span> Se debita <b>N° {{ $c['numero'] }}</b> · {{ $c['banco'] ?: 's/banco' }} · a {{ $c['quien'] }} @if ($c['atrasado'])<span class="font-bold text-red-600">(vencido)</span>@endif</span>
                                        <span class="tabular font-bold text-danger">{{ $money($c['monto']) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="py-10 text-center text-sm text-muted">No hay cheques con movimiento en los próximos 30 días.</p>
                    @endforelse
                </div>
            </div>
        @endif
    </x-panel>

    {{-- ===== Modal: ingresar cheque de terceros ===== --}}
    @if ($modalTercero)
        <div class="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto p-4 sm:items-center">
            <div class="absolute inset-0 bg-black/50" wire:click="$set('modalTercero', false)"></div>
            <div class="relative z-10 my-auto w-full max-w-md rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 class="text-base font-extrabold text-ink">Ingresar cheque recibido</h3>
                    <button type="button" wire:click="$set('modalTercero', false)" class="text-muted hover:text-danger"><span class="material-symbols-outlined">close</span></button>
                </div>
                <form wire:submit="guardarTercero" class="space-y-4 p-5">
                    <div>
                        <label class="mb-1 block text-xs font-bold uppercase text-muted">Cliente que lo entrega</label>
                        <select wire:model="tCliente" class="w-full rounded-lg border border-gray-200 px-2 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                            <option value="">— Elegir —</option>
                            @foreach ($clientes as $c)<option value="{{ $c->id }}">{{ $c->nombre }}</option>@endforeach
                        </select>
                        @error('tCliente') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">N° de cheque</label>
                            <input type="text" wire:model="tNumero" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                            @error('tNumero') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Banco</label>
                            <input type="text" wire:model="tBanco" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Monto</label>
                            <input type="number" step="0.01" min="0" wire:model="tMonto" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                            @error('tMonto') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Vencimiento</label>
                            <input type="date" wire:model="tVencimiento" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                            @error('tVencimiento') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <p class="text-xs text-muted">La fecha de depósito se calcula sola: vencimiento + 1 día hábil.</p>
                    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <button type="button" wire:click="$set('modalTercero', false)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold text-graphite hover:bg-gray-50">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-dark">Ingresar a cartera</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ===== Modal: emitir cheque propio ===== --}}
    @if ($modalPropio)
        <div class="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto p-4 sm:items-center">
            <div class="absolute inset-0 bg-black/50" wire:click="$set('modalPropio', false)"></div>
            <div class="relative z-10 my-auto w-full max-w-md rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 class="text-base font-extrabold text-ink">Emitir cheque propio</h3>
                    <button type="button" wire:click="$set('modalPropio', false)" class="text-muted hover:text-danger"><span class="material-symbols-outlined">close</span></button>
                </div>
                <form wire:submit="guardarPropio" class="space-y-4 p-5">
                    <div>
                        <label class="mb-1 block text-xs font-bold uppercase text-muted">Proveedor</label>
                        <select wire:model="pProveedor" class="w-full rounded-lg border border-gray-200 px-2 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                            <option value="">— Elegir —</option>
                            @foreach ($proveedores as $p)<option value="{{ $p->id }}">{{ $p->nombre }}</option>@endforeach
                        </select>
                        @error('pProveedor') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">N° de cheque</label>
                            <input type="text" wire:model="pNumero" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                            @error('pNumero') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Banco</label>
                            <input type="text" wire:model="pBanco" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Monto</label>
                            <input type="number" step="0.01" min="0" wire:model="pMonto" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                            @error('pMonto') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Emisión</label>
                            <input type="date" wire:model="pEmision" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                        </div>
                        <div class="col-span-2">
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Vencimiento (cuándo se debita)</label>
                            <input type="date" wire:model="pVencimiento" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                            @error('pVencimiento') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <button type="button" wire:click="$set('modalPropio', false)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold text-graphite hover:bg-gray-50">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-dark">Registrar cheque</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ===== Modal: rechazo ===== --}}
    @if ($rechazandoId)
        <div class="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto p-4 sm:items-center">
            <div class="absolute inset-0 bg-black/50" wire:click="$set('rechazandoId', null)"></div>
            <div class="relative z-10 my-auto w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl">
                <h3 class="mb-1 text-base font-extrabold text-ink">Rechazar cheque</h3>
                <p class="mb-3 text-xs text-muted">Si ya estaba depositado, se revierte el ingreso en caja.</p>
                <label class="mb-1 block text-xs font-bold uppercase text-muted">Motivo</label>
                <input type="text" wire:model="motivoRechazo" placeholder="Sin fondos" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="$set('rechazandoId', null)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold text-graphite hover:bg-gray-50">Cancelar</button>
                    <button type="button" wire:click="rechazar" class="rounded-lg bg-danger px-4 py-2 text-sm font-bold text-white hover:brightness-95">Marcar rechazado</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== Modal: endoso a proveedor ===== --}}
    @if ($endosandoId)
        <div class="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto p-4 sm:items-center">
            <div class="absolute inset-0 bg-black/50" wire:click="$set('endosandoId', null)"></div>
            <div class="relative z-10 my-auto w-full max-w-lg rounded-2xl bg-white p-5 shadow-2xl">
                <h3 class="mb-1 text-base font-extrabold text-ink">Endosar cheque a un proveedor</h3>
                <p class="mb-3 text-xs text-muted">
                    El cheque sale de la cartera y cancela la factura elegida. <b>No mueve la caja</b> (ese cheque nunca ingresó a caja).
                    Queda <b>pendiente de autorización</b>: lo autoriza el jefe y lo procesa el tesorero.
                </p>
                <label class="mb-1 block text-xs font-bold uppercase text-muted">Factura del proveedor a saldar</label>
                <select wire:model="eObligacion" class="w-full rounded-lg border border-gray-200 px-2 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                    <option value="">— Elegir factura impaga —</option>
                    @foreach ($this->obligaciones as $o)
                        <option value="{{ $o['id'] }}">{{ $o['proveedor'] }} · {{ $o['factura'] }} — saldo {{ $money($o['saldo']) }}</option>
                    @endforeach
                </select>
                @error('eObligacion') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                @if (empty($this->obligaciones))
                    <p class="mt-2 text-xs font-semibold text-amber-700">No hay facturas de proveedor impagas. Cargá la factura de una compra recibida desde Proveedores.</p>
                @endif
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="$set('endosandoId', null)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold text-graphite hover:bg-gray-50">Cancelar</button>
                    <button type="button" wire:click="endosar" class="rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-dark">Solicitar endoso</button>
                </div>
            </div>
        </div>
    @endif
</div>
