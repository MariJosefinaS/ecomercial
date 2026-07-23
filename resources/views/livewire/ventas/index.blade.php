<div class="space-y-6">

    {{-- Encabezado --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-ink">{{ $verTodas ? 'Todas las ventas' : 'Mis ventas' }}</h1>
            <p class="text-sm text-muted">{{ $verTodas ? 'Todas las ventas del sistema y aprobaciones' : 'Las ventas que cargaste y su estado' }}</p>
        </div>
        @puede('crear_venta')
            <a href="{{ route('ventas.nueva') }}" wire:navigate class="flex items-center gap-2 rounded-lg bg-brand px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-dark">
                <span class="material-symbols-outlined text-[20px]">add</span> Nueva venta
            </a>
        @endpuede
    </div>

    {{-- Mensaje de acción --}}
    @if ($mensaje || session('ventaMsg'))
        <div class="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm font-semibold text-green-700">
            <span class="material-symbols-outlined text-[18px]">check_circle</span> {{ $mensaje ?? session('ventaMsg') }}
        </div>
    @endif

    {{-- Stats --}}
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
        <x-kpi-card variant="white" title="Pendientes" :value="$stats['pendientes']" icon="pending_actions" subtitle="A aprobar" />
        <x-kpi-card variant="blue"  title="Aprobadas" :value="$stats['aprobadas']" icon="task_alt" subtitle="Este período" />
        <x-kpi-card variant="brand" title="Monto Aprobado" :value="'$' . number_format($stats['monto_aprobado'], 0, ',', '.')" icon="payments" />
    </div>

    {{-- Tabla + filtros --}}
    <x-panel :title="$verTodas ? 'Todas las ventas' : 'Mis ventas'">
        <x-slot:actions>
            <span class="text-xs font-semibold text-muted">{{ count($filas) }} resultado(s)</span>
        </x-slot:actions>

        <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-5">
            <div class="relative min-w-[240px] flex-1">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[20px] text-muted">search</span>
                <input type="text" wire:model.live.debounce.300ms="buscar" placeholder="Buscar por N°, vendedor o cliente..."
                       class="w-full rounded-full border border-gray-200 bg-gray-50 py-2 pl-10 pr-4 text-sm font-medium outline-none transition focus:border-brand focus:bg-white focus:ring-2 focus:ring-brand/20" />
            </div>

            {{-- Estado --}}
            <div class="flex overflow-hidden rounded-lg border border-gray-200 text-sm font-bold">
                @foreach (['todos' => 'Todos', 'pendiente' => 'Pendientes', 'aprobada' => 'Aprobadas', 'rechazada' => 'Rechazadas'] as $val => $lbl)
                    <button wire:click="$set('estado', '{{ $val }}')"
                            class="px-3 py-2 transition {{ $estado === $val ? 'bg-brand text-white' : 'text-graphite hover:bg-gray-50' }}">{{ $lbl }}</button>
                @endforeach
            </div>

            {{-- Local --}}
            <select wire:model.live="local" class="rounded-lg border border-gray-200 py-2 pl-3 pr-8 text-sm font-medium text-ink outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                <option value="todos">Todos los locales</option>
                @foreach ($locales as $loc)
                    <option value="{{ $loc }}">{{ $loc }}</option>
                @endforeach
            </select>

            <button wire:click="limpiar" class="ml-auto flex items-center gap-1 text-xs font-bold text-muted hover:text-brand">
                <span class="material-symbols-outlined text-[16px]">close</span> Limpiar
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wide text-muted">
                        <th class="px-5 py-3 font-bold">N°</th>
                        <th class="px-5 py-3 font-bold">Fecha</th>
                        <th class="px-5 py-3 font-bold">Vendedor</th>
                        <th class="px-5 py-3 font-bold">Cliente</th>
                        <th class="px-5 py-3 font-bold">Local</th>
                        <th class="px-5 py-3 text-center font-bold">Ítems</th>
                        <th class="px-5 py-3 text-right font-bold">Total</th>
                        <th class="px-5 py-3 font-bold">Pago</th>
                        <th class="px-5 py-3 text-center font-bold">Estado</th>
                        <th class="px-5 py-3 text-right font-bold">Acciones</th>
                    </tr>
                </thead>
                <tbody class="tabular">
                    @forelse ($filas as $i => $v)
                        @php
                            $badge = match ($v['estado']) {
                                'aprobada' => 'bg-green-100 text-green-700',
                                'rechazada' => 'bg-red-100 text-red-700',
                                default => 'bg-amber-100 text-amber-700',
                            };
                        @endphp
                        <tr class="border-t border-gray-100 {{ $i % 2 ? 'bg-gray-50/50' : '' }} hover:bg-brand-soft/40 {{ $highlight === $v['num'] ? 'bg-brand-soft ring-2 ring-inset ring-brand' : '' }}" wire:key="venta-{{ $v['num'] }}" @if ($highlight === $v['num']) x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'center' })" @endif>
                            <td class="px-5 py-3"><a href="#" class="font-bold text-ink hover:text-brand hover:underline">{{ $v['num'] }}</a></td>
                            <td class="px-5 py-3 text-graphite">{{ $v['fecha'] }}</td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2.5">
                                    <x-avatar :initials="$v['ini']" :variant="$v['vv']" size="sm" />
                                    <span class="font-semibold text-ink">{{ $v['vend'] }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                @php $rg = $v['cliente_riesgo'] ?? $this->riesgoCliente($v['cliente']); @endphp
                                <div class="flex flex-col gap-0.5">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="font-semibold text-ink">{{ $v['cliente'] }}</span>
                                        @if ($v['cliente_nuevo'] ?? false)
                                            <span class="inline-flex items-center gap-0.5 rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-700" title="Cliente nuevo — debe aprobarlo el administrador antes de concretar la venta">
                                                <span class="material-symbols-outlined text-[13px]">person_add</span> Cliente nuevo
                                            </span>
                                        @endif
                                        @if ($rg !== 'bajo')
                                            <span class="inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-[10px] font-bold {{ $rg === 'alto' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}" title="Cliente de riesgo {{ $rg }} — revisar antes de aprobar">
                                                <span class="material-symbols-outlined text-[14px]">warning</span> Riesgo {{ $rg }}
                                            </span>
                                        @endif
                                    </div>
                                    @if (! empty($v['cliente_doc']))
                                        <span class="text-[11px] text-muted">{{ $v['cliente_doc'] }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-3 text-graphite">{{ $v['local'] }}</td>
                            <td class="px-5 py-3 text-center">
                                <button wire:click="verDetalle({{ $v['id'] }})" class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-graphite transition hover:bg-gray-100 hover:text-brand" title="Ver productos cargados">
                                    {{ $v['items'] }} <span class="material-symbols-outlined text-[16px]">visibility</span>
                                </button>
                            </td>
                            <td class="px-5 py-3 text-right font-bold text-ink">${{ number_format($v['total'], 2, ',', '.') }}</td>
                            <td class="px-5 py-3">
                                <div class="flex flex-col gap-0.5">
                                    <span class="text-graphite">{{ $v['medio'] ?? '—' }}</span>
                                    @if ($v['credito'] ?? false)
                                        <span class="inline-flex w-fit items-center gap-0.5 rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-700" title="Venta a crédito — requiere aprobación">
                                            <span class="material-symbols-outlined text-[13px]">credit_score</span> Crédito
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <span class="inline-block rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wide {{ $badge }}">{{ $v['estado'] }}</span>
                                @if ($v['estado'] === 'rechazada' && ! empty($v['motivo_rechazo']))
                                    <p class="mt-1 max-w-[160px] text-[11px] text-muted" title="{{ $v['motivo_rechazo'] }}">Motivo: {{ \Illuminate\Support\Str::limit($v['motivo_rechazo'], 40) }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    @if ($v['estado'] === 'pendiente')
                                        @puede('aprobar_ventas')
                                            <button wire:click="verClienteVenta({{ $v['id'] }})" class="flex items-center gap-1 rounded-lg border border-brand/30 bg-brand-soft/50 px-3 py-1.5 text-xs font-bold text-brand transition hover:bg-brand-soft" title="Ver situación del cliente antes de aprobar"><span class="material-symbols-outlined text-[16px]">badge</span> Ver cliente</button>
                                            <button wire:click="aprobar({{ $v['id'] }})" class="rounded-lg bg-success px-3 py-1.5 text-xs font-bold text-white transition hover:brightness-95">Aprobar</button>
                                            <button wire:click="rechazar({{ $v['id'] }})" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-bold text-graphite transition hover:bg-gray-50">Rechazar</button>
                                        @else
                                            <span class="text-xs text-muted">Pendiente</span>
                                        @endpuede
                                    @else
                                        @if ($v['estado'] === 'aprobada' && ! $v['entregado'])
                                            @puede('entregar_venta')
                                                <button wire:click="entregar({{ $v['id'] }})" class="flex items-center gap-1 rounded-lg bg-brand px-3 py-1.5 text-xs font-bold text-white transition hover:bg-brand-dark" title="Entregar mercadería y cargar códigos">
                                                    <span class="material-symbols-outlined text-[16px]">local_shipping</span> Entregar
                                                </button>
                                            @endpuede
                                        @elseif ($v['entregado'])
                                            <span class="inline-flex items-center gap-0.5 rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-bold text-green-700" title="Mercadería entregada con trazabilidad"><span class="material-symbols-outlined text-[13px]">check_circle</span> Entregada</span>
                                        @endif
                                        <button wire:click="verDetalle({{ $v['id'] }})" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-graphite transition hover:bg-gray-100 hover:text-brand" title="Ver productos">
                                            <span class="material-symbols-outlined text-[20px]">visibility</span>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-5 py-10 text-center text-sm text-muted">
                                <span class="material-symbols-outlined mb-1 block text-3xl">receipt_long</span>
                                No hay ventas con esos filtros.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-panel>

    {{-- ===== Modal nueva venta ===== --}}
    @if ($modal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" wire:click="$set('modal', false)"></div>
            <div class="relative z-10 max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 class="text-base font-extrabold text-ink">Nueva venta</h3>
                    <button wire:click="$set('modal', false)" class="text-muted hover:text-danger"><span class="material-symbols-outlined">close</span></button>
                </div>

                <form wire:submit="guardarVenta" class="p-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2" x-data @click.outside="$wire.buscandoCliente && $wire.set('buscandoCliente', false)">
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Cliente</label>

                            @php $rcol = ['alto' => 'text-red-600', 'medio' => 'text-amber-600', 'bajo' => 'text-green-600']; @endphp

                            @if ($vClienteId)
                                {{-- Cliente seleccionado --}}
                                <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5">
                                    <div>
                                        <p class="flex items-center gap-2 text-sm font-bold text-ink">
                                            {{ $vClienteNombre }}
                                            @if ($vClienteNuevo)
                                                <span class="rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-700">NUEVO · pend. aprobación</span>
                                            @endif
                                        </p>
                                        <p class="text-xs text-muted">
                                            <span class="font-semibold text-graphite">{{ $vClienteDoc ?: 'Sin documento' }}</span>
                                            @if (! $vClienteNuevo)
                                                · riesgo <span class="font-bold {{ $rcol[$vClienteRiesgo] ?? 'text-graphite' }}">{{ $vClienteRiesgo }}</span>
                                            @endif
                                        </p>
                                    </div>
                                    <button type="button" wire:click="$set('vClienteId', null)" class="text-muted hover:text-danger" title="Cambiar cliente">
                                        <span class="material-symbols-outlined">close</span>
                                    </button>
                                </div>
                            @else
                                {{-- Buscador de cliente --}}
                                <div class="relative">
                                    <input type="text" autocomplete="off" wire:model.live.debounce.300ms="vClienteBuscar"
                                           placeholder="Buscar cliente por nombre, apellido o CUIT/CUIL…"
                                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />

                                    @if ($buscandoCliente && mb_strlen(trim($vClienteBuscar)) >= 2)
                                        <div class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-xl">
                                            <p class="border-b border-gray-100 bg-gray-50 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wide text-muted">Clientes — busca por nombre/apellido o documento</p>
                                            @forelse ($this->clientesEncontrados as $c)
                                                <button type="button" wire:click="elegirCliente({{ $c['id'] }})"
                                                        class="flex w-full items-center justify-between gap-2 border-b border-gray-50 px-3 py-2 text-left hover:bg-brand-soft">
                                                    <span>
                                                        <span class="text-sm font-semibold text-ink">{{ $c['nombre'] }}</span>
                                                        <span class="block text-[11px] text-muted">{{ $c['doc'] ?: 'Sin documento' }}</span>
                                                    </span>
                                                    <span class="text-[11px] font-bold {{ $rcol[$c['riesgo']] ?? 'text-graphite' }}">riesgo {{ $c['riesgo'] }}</span>
                                                </button>
                                            @empty
                                                <div class="px-3 py-2 text-xs text-muted">No existe ningún cliente con «{{ $vClienteBuscar }}».</div>
                                            @endforelse
                                            <button type="button" wire:click="mostrarAltaCliente"
                                                    class="flex w-full items-center gap-1.5 border-t border-gray-100 bg-gray-50 px-3 py-2 text-left text-sm font-bold text-brand hover:bg-brand-soft">
                                                <span class="material-symbols-outlined text-[18px]">person_add</span> Dar de alta cliente nuevo
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            @endif
                            @error('vClienteId') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Local</label>
                            <select wire:model.live="vLocal" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                                @foreach ($locales as $loc)
                                    <option value="{{ $loc }}">{{ $loc }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-[11px] text-muted">El precio sugerido sale del stock de este local.</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Medio de pago</label>
                            <select wire:model.live="vMedioPago" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                                @foreach (\App\Livewire\Ventas\Index::MEDIOS as $m)
                                    <option value="{{ $m }}">{{ $m }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Alta de cliente nuevo (requiere aprobación del administrador) --}}
                    @if ($altaCliente)
                        <div class="mt-3 rounded-xl border border-brand/30 bg-brand-soft/30 p-4">
                            <div class="mb-2 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-brand">
                                <span class="material-symbols-outlined text-[18px]">person_add</span> Nuevo cliente — requiere aprobación del administrador
                            </div>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                                <div class="sm:col-span-4">
                                    <label class="mb-1 block text-[11px] font-bold uppercase text-muted">Nombre y apellido / Razón social</label>
                                    <input type="text" wire:model="ncNombre" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                                    @error('ncNombre') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-bold uppercase text-muted">Tipo doc</label>
                                    <select wire:model="ncTipoDoc" class="w-full rounded-lg border border-gray-200 px-2 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                                        <option>CUIT</option><option>CUIL</option><option>DNI</option>
                                    </select>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="mb-1 block text-[11px] font-bold uppercase text-muted">Número (CUIT/CUIL/DNI)</label>
                                    <input type="text" wire:model="ncDoc" placeholder="30-00000000-0" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                                    @error('ncDoc') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-bold uppercase text-muted">Teléfono</label>
                                    <input type="text" wire:model="ncTel" class="w-full rounded-lg border border-gray-200 px-2 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                                </div>
                            </div>
                            <div class="mt-3 flex justify-end gap-2">
                                <button type="button" wire:click="$set('altaCliente', false)" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-bold text-graphite hover:bg-gray-50">Cancelar</button>
                                <button type="button" wire:click="guardarClienteNuevo" class="rounded-lg bg-brand px-3 py-1.5 text-sm font-bold text-white hover:bg-brand-dark">Guardar cliente</button>
                            </div>
                        </div>
                    @endif

                    @if (in_array($vMedioPago, \App\Livewire\Ventas\Index::MEDIOS_CREDITO, true))
                        <div class="mt-3 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700">
                            <span class="material-symbols-outlined text-[18px]">credit_score</span>
                            <span>Se cobra con <b>{{ $vMedioPago }}</b> (crédito propio). La venta queda <b>pendiente de aprobación</b>; el aprobador verá el aviso de crédito y el riesgo del cliente.</span>
                        </div>
                    @endif

                    {{-- Renglones de la venta --}}
                    <div class="mt-4">
                        <div class="mb-2 flex items-center justify-between">
                            <p class="text-xs font-bold uppercase tracking-wide text-muted">Productos</p>
                            <button type="button" wire:click="agregarItem" class="inline-flex items-center gap-1 text-xs font-bold text-brand hover:text-brand-dark">
                                <span class="material-symbols-outlined text-[18px]">add</span> Agregar renglón
                            </button>
                        </div>
                        @error('vItems') <p class="mb-2 text-xs font-semibold text-danger">{{ $message }}</p> @enderror

                        <div class="space-y-2" x-data @click.outside="$wire.buscandoEn !== null && $wire.cerrarBusqueda()">
                            @foreach ($vItems as $i => $it)
                                <div class="flex items-start gap-2" wire:key="vitem-{{ $i }}">
                                    {{-- Buscador de producto con dropdown --}}
                                    <div class="relative flex-1">
                                        <input type="text" autocomplete="off" wire:model.live.debounce.300ms="vItems.{{ $i }}.desc"
                                               placeholder="Buscar producto por nombre, código o proveedor…"
                                               class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                                        @error("vItems.$i.producto_id") <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror

                                        @if ($buscandoEn === $i && count($this->resultados))
                                            <div class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-xl">
                                                <p class="border-b border-gray-100 bg-gray-50 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wide text-muted">Resultados — busca por nombre · código · proveedor</p>
                                                @foreach ($this->resultados as $r)
                                                    <button type="button" wire:click="elegirProducto({{ $i }}, {{ $r['id'] }})"
                                                            class="flex w-full items-center justify-between gap-2 border-b border-gray-50 px-3 py-2 text-left hover:bg-brand-soft">
                                                        <span>
                                                            <span class="text-sm font-semibold text-ink">{{ $r['nom'] }}</span>
                                                            <span class="block text-[11px] text-muted">{{ $r['cod'] }} · {{ $r['prov'] }}</span>
                                                        </span>
                                                        <span class="tabular whitespace-nowrap text-xs font-bold text-brand">${{ number_format($r['precio'], 2, ',', '.') }}</span>
                                                    </button>
                                                @endforeach
                                            </div>
                                        @elseif ($buscandoEn === $i && mb_strlen(trim($it['desc'] ?? '')) >= 2)
                                            <div class="absolute z-30 mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-muted shadow-xl">Sin productos que coincidan con «{{ $it['desc'] }}».</div>
                                        @endif
                                    </div>
                                    <div class="w-20">
                                        <input type="number" min="1" wire:model.live="vItems.{{ $i }}.cant" placeholder="Cant."
                                               class="w-full rounded-lg border border-gray-200 px-2 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                                        @error("vItems.$i.cant") <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="w-28">
                                        <input type="number" step="0.01" min="0" wire:model.live="vItems.{{ $i }}.precio" placeholder="Precio"
                                               class="w-full rounded-lg border border-gray-200 px-2 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                                        @error("vItems.$i.precio") <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                                    </div>
                                    <button type="button" wire:click="quitarItem({{ $i }})" class="mt-1.5 text-muted hover:text-danger" title="Quitar">
                                        <span class="material-symbols-outlined text-[20px]">delete</span>
                                    </button>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-3 flex justify-between border-t border-gray-200 pt-3">
                            <span class="font-bold text-ink">Total</span>
                            <span class="tabular text-base font-extrabold text-brand">${{ number_format($this->totalVenta, 2, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <button type="button" wire:click="$set('modal', false)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold text-graphite hover:bg-gray-50">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-dark">Crear venta</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ===== Modal rechazar venta (motivo obligatorio) ===== --}}
    @if ($modalRechazo)
        <div class="fixed inset-0 z-[70] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" wire:click="cerrarRechazo"></div>
            <div class="relative z-10 w-full max-w-md rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 class="text-base font-extrabold text-ink">Rechazar venta</h3>
                    <button wire:click="cerrarRechazo" class="text-muted hover:text-danger"><span class="material-symbols-outlined">close</span></button>
                </div>
                <form wire:submit="confirmarRechazo" class="space-y-4 p-5">
                    <div>
                        <label class="mb-1 block text-xs font-bold uppercase text-muted">Motivo del rechazo <span class="text-danger">*</span></label>
                        <textarea wire:model="motivoRechazo" rows="3" placeholder="Indicá por qué se rechaza esta venta…" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20"></textarea>
                        @error('motivoRechazo') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <button type="button" wire:click="cerrarRechazo" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold text-graphite hover:bg-gray-50">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-danger px-4 py-2 text-sm font-bold text-white hover:brightness-95">Confirmar rechazo</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ===== Modal detalle de ítems (productos cargados) ===== --}}
    @if ($detalle)
        <div class="fixed inset-0 z-[70] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" wire:click="cerrarDetalle"></div>
            <div class="relative z-10 w-full max-w-lg rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 class="text-base font-extrabold text-ink">Productos de {{ $detalle['num'] }}</h3>
                    <button wire:click="cerrarDetalle" class="text-muted hover:text-danger"><span class="material-symbols-outlined">close</span></button>
                </div>
                <div class="max-h-[70vh] overflow-auto p-5">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-wide text-muted">
                                <th class="pb-2 font-bold">Producto</th>
                                <th class="pb-2 text-center font-bold">Cant.</th>
                                <th class="pb-2 text-right font-bold">Precio</th>
                                <th class="pb-2 text-right font-bold">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="tabular">
                            @foreach ($detalle['items'] as $it)
                                <tr class="border-t border-gray-100">
                                    <td class="py-2">
                                        <p class="font-semibold text-ink">{{ $it['nom'] }} @if ($it['sugerido']) <span class="text-[10px] font-bold text-brand">(sugerido)</span> @endif</p>
                                        <p class="text-xs text-muted">{{ $it['cod'] }}</p>
                                    </td>
                                    <td class="py-2 text-center text-graphite">{{ $it['cant'] }}</td>
                                    <td class="py-2 text-right text-graphite">${{ number_format($it['precio'], 2, ',', '.') }}</td>
                                    <td class="py-2 text-right font-bold text-ink">${{ number_format($it['cant'] * $it['precio'], 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== Modal detalle del cliente (para APROBAR) ===== --}}
    @if ($clienteDet)
        @php
            $riesgoColor = ['alto' => 'bg-red-100 text-red-700', 'medio' => 'bg-amber-100 text-amber-700', 'bajo' => 'bg-green-100 text-green-700'];
            [$cDot, $cTxt, $cBg] = \App\Support\Semaforo::clases($clienteDet['semaforo']['estado']);
            $money = fn ($n) => '$' . number_format((float) $n, 2, ',', '.');
        @endphp
        <div class="fixed inset-0 z-[70] flex items-start justify-center overflow-y-auto p-4 sm:items-center">
            <div class="absolute inset-0 bg-black/50" wire:click="cerrarClienteDet"></div>
            <div class="relative z-10 my-auto w-full max-w-xl rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <div>
                        <h3 class="text-base font-extrabold text-ink">{{ $clienteDet['nombre'] }}</h3>
                        <p class="text-xs text-muted">{{ $clienteDet['doc'] }} · para aprobar {{ $clienteDet['venta'] }}</p>
                    </div>
                    <button wire:click="cerrarClienteDet" class="text-muted hover:text-danger"><span class="material-symbols-outlined">close</span></button>
                </div>
                <div class="max-h-[75vh] space-y-4 overflow-auto p-5">
                    {{-- Semáforo + riesgo --}}
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full {{ $cBg }} px-3 py-1 text-xs font-bold {{ $cTxt }}"><span class="h-2.5 w-2.5 rounded-full {{ $cDot }}"></span> {{ \App\Support\Semaforo::label($clienteDet['semaforo']['estado']) }}</span>
                        <span class="rounded-full px-3 py-1 text-xs font-bold uppercase {{ $riesgoColor[$clienteDet['riesgo']] ?? 'bg-gray-100 text-graphite' }}">Riesgo {{ $clienteDet['riesgo'] }}</span>
                        @if (! $clienteDet['aprobado'])<span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-700">Cliente nuevo</span>@endif
                    </div>

                    @if ($clienteDet['semaforo']['estado'] === 'rojo')
                        <div class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-bold text-red-700"><span class="material-symbols-outlined text-[18px]">block</span> Cliente incobrable: dejó de pagar. Se recomienda RECHAZAR.</div>
                    @elseif ($clienteDet['cheques_rechazados'] > 0 || $clienteDet['vencidas'] > 0)
                        <div class="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-bold text-amber-700"><span class="material-symbols-outlined text-[18px]">warning</span> Revisar: tiene atrasos o cheques rechazados.</div>
                    @endif

                    {{-- Métricas --}}
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border border-gray-100 p-3"><p class="text-[10px] font-bold uppercase text-muted">Saldo cta. cte.</p><p class="tabular text-lg font-extrabold {{ $clienteDet['saldo'] > 0 ? 'text-red-600' : 'text-ink' }}">{{ $money($clienteDet['saldo']) }}</p></div>
                        <div class="rounded-xl border border-gray-100 p-3"><p class="text-[10px] font-bold uppercase text-muted">Límite</p><p class="tabular text-lg font-extrabold text-ink">{{ $money($clienteDet['limite']) }}</p></div>
                        <div class="rounded-xl border border-gray-100 p-3"><p class="text-[10px] font-bold uppercase text-muted">Veces que compró</p><p class="text-lg font-extrabold text-ink">{{ $clienteDet['compras'] }}</p></div>
                        <div class="rounded-xl border border-gray-100 p-3"><p class="text-[10px] font-bold uppercase text-muted">Cheques rechazados</p><p class="text-lg font-extrabold {{ $clienteDet['cheques_rechazados'] > 0 ? 'text-red-600' : 'text-ink' }}">{{ $clienteDet['cheques_rechazados'] }}</p></div>
                        <div class="rounded-xl border border-gray-100 p-3"><p class="text-[10px] font-bold uppercase text-muted">Cuotas vencidas</p><p class="text-lg font-extrabold {{ $clienteDet['vencidas'] > 0 ? 'text-amber-600' : 'text-ink' }}">{{ $clienteDet['vencidas'] }}@if ($clienteDet['max_atraso'] > 0) <span class="text-[11px] font-normal text-muted">({{ $clienteDet['max_atraso'] }}d)</span>@endif</p></div>
                        <div class="rounded-xl border border-gray-100 p-3"><p class="text-[10px] font-bold uppercase text-muted">Devoluciones</p><p class="text-lg font-extrabold text-ink">{{ $clienteDet['devoluciones'] }}</p></div>
                    </div>

                    {{-- Historial de pago --}}
                    <div>
                        <p class="mb-1 text-[11px] font-bold uppercase tracking-wide text-muted">Historial de pago · {{ $clienteDet['cuotas_pagadas'] }}/{{ $clienteDet['cuotas_total'] }} cuotas pagadas</p>
                        @if (empty($clienteDet['ultimos_pagos']))
                            <p class="text-sm text-muted">Sin pagos registrados.</p>
                        @else
                            <div class="divide-y divide-gray-100 rounded-xl border border-gray-100">
                                @foreach ($clienteDet['ultimos_pagos'] as $p)
                                    <div class="flex items-center justify-between px-3 py-2 text-xs">
                                        <span class="text-graphite">{{ $p['fecha'] }} · {{ $p['concepto'] }}</span>
                                        <span class="tabular font-bold text-success">{{ $money($p['monto']) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
                <div class="flex flex-wrap justify-end gap-2 border-t border-gray-100 px-5 py-4">
                    <button wire:click="cerrarClienteDet" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-bold text-graphite hover:bg-gray-100">Cerrar</button>
                    <button wire:click="rechazar({{ $clienteDetVentaId }})" class="rounded-lg border border-red-300 bg-red-50 px-4 py-2 text-sm font-bold text-red-700 hover:bg-red-100">Rechazar venta</button>
                    <button wire:click="aprobar({{ $clienteDetVentaId }})" class="rounded-lg bg-success px-4 py-2 text-sm font-bold text-white hover:brightness-95">Aprobar venta</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== Modal entrega: cargar el código de trazabilidad de cada caja ===== --}}
    @if ($modalEntrega)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" wire:click="$set('modalEntrega', false)"></div>
            <div class="relative z-10 w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl">
                <div class="mb-3 flex items-start justify-between">
                    <div>
                        <h3 class="text-lg font-extrabold text-ink">Entregar — {{ $entregaNumero }}</h3>
                        <p class="text-xs text-muted">Escaneá o cargá el código de la etiqueta de cada caja que entregás.</p>
                    </div>
                    <button wire:click="$set('modalEntrega', false)" class="text-muted hover:text-danger"><span class="material-symbols-outlined">close</span></button>
                </div>

                {{-- A dónde va la mercadería (el cliente puede tener varios domicilios) --}}
                @if (! empty($entregaDomicilio['direccion']))
                    <div class="mb-3 rounded-xl border border-brand/30 bg-brand-soft/40 px-3 py-2.5">
                        <p class="flex items-center gap-1.5 text-xs font-extrabold uppercase text-brand">
                            <span class="material-symbols-outlined text-[16px]">local_shipping</span> Entregar en · {{ $entregaDomicilio['etiqueta'] }}
                        </p>
                        <p class="mt-0.5 text-sm font-semibold text-ink">{{ $entregaDomicilio['direccion'] }}</p>
                        @if (! empty($entregaDomicilio['referencia']))<p class="text-xs italic text-graphite">{{ $entregaDomicilio['referencia'] }}</p>@endif
                        <p class="mt-0.5 text-xs text-graphite">
                            @if (! empty($entregaDomicilio['contacto']))Recibe: <b>{{ $entregaDomicilio['contacto'] }}</b>@endif
                            @if (! empty($entregaDomicilio['telefono'])) · {{ $entregaDomicilio['telefono'] }}@endif
                            @if (! empty($entregaDomicilio['maps'])) · <a href="{{ $entregaDomicilio['maps'] }}" target="_blank" rel="noopener" class="font-bold text-brand hover:underline">ver en mapa</a>@endif
                        </p>
                    </div>
                @endif

                <div class="max-h-[55vh] space-y-2 overflow-y-auto">
                    @foreach ($entCodigos as $i => $row)
                        <div wire:key="ent-{{ $i }}" class="grid grid-cols-5 items-center gap-2">
                            <div class="col-span-2">
                                <p class="text-sm font-semibold text-ink">{{ $row['desc'] }}</p>
                                <p class="text-[11px] text-muted">Caja {{ $i + 1 }}</p>
                            </div>
                            <div class="col-span-3">
                                <input type="text" wire:model="entCodigos.{{ $i }}.codigo" placeholder="TRZ-..."
                                       class="w-full rounded-lg border border-gray-200 px-2.5 py-1.5 font-mono text-sm uppercase outline-none focus:border-brand" />
                                @error("entCodigos.{$i}.codigo") <p class="mt-0.5 text-[11px] text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <button wire:click="$set('modalEntrega', false)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold text-graphite hover:bg-gray-50">Cancelar</button>
                    <button wire:click="confirmarEntrega" class="flex items-center gap-1.5 rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-dark">
                        <span class="material-symbols-outlined text-[18px]">check</span> Confirmar entrega
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
