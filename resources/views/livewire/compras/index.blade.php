<div class="space-y-6">

    {{-- Encabezado --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-ink">Compras</h1>
            <p class="text-sm text-muted">Órdenes de compra e ingreso de mercadería</p>
        </div>
        @puede('crear_compra')
            <button wire:click="registrarCompra" class="flex items-center gap-2 rounded-lg bg-brand px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-dark">
                <span class="material-symbols-outlined text-[20px]">add</span> Registrar compra
            </button>
        @endpuede
    </div>

    {{-- Mensaje --}}
    @if ($mensaje)
        <div class="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm font-semibold text-green-700">
            <span class="material-symbols-outlined text-[18px]">check_circle</span> {{ $mensaje }}
        </div>
    @endif

    {{-- Stats --}}
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
        <x-kpi-card variant="white" title="Pendientes" :value="$stats['pendientes']" icon="pending_actions" subtitle="A aprobar" />
        <x-kpi-card variant="blue"  title="Por Recibir" :value="$stats['por_recibir']" icon="local_shipping" subtitle="Aprobadas" />
        <x-kpi-card variant="brand" title="Total Recibido" :value="'$' . number_format($stats['total_recibido'], 0, ',', '.')" icon="inventory" />
    </div>

    {{-- Tabla + filtros --}}
    <x-panel title="Listado de compras">
        <x-slot:actions>
            <span class="text-xs font-semibold text-muted">{{ count($filas) }} resultado(s)</span>
        </x-slot:actions>

        <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-5">
            <div class="relative min-w-[240px] flex-1">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[20px] text-muted">search</span>
                <input type="text" wire:model.live.debounce.300ms="buscar" placeholder="Buscar por N°, proveedor o factura..."
                       class="w-full rounded-full border border-gray-200 bg-gray-50 py-2 pl-10 pr-4 text-sm font-medium outline-none transition focus:border-brand focus:bg-white focus:ring-2 focus:ring-brand/20" />
            </div>

            <div class="flex overflow-hidden rounded-lg border border-gray-200 text-sm font-bold">
                @foreach (['todos' => 'Todos', 'pendiente' => 'Pendientes', 'aprobada' => 'Aprobadas', 'recibida' => 'Recibidas', 'rechazada' => 'Rechazadas'] as $val => $lbl)
                    <button wire:click="$set('estado', '{{ $val }}')"
                            class="px-3 py-2 transition {{ $estado === $val ? 'bg-brand text-white' : 'text-graphite hover:bg-gray-50' }}">{{ $lbl }}</button>
                @endforeach
            </div>

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
                        <th class="px-5 py-3 font-bold">Proveedor</th>
                        <th class="px-5 py-3 font-bold">Factura</th>
                        <th class="px-5 py-3 font-bold">Local</th>
                        <th class="px-5 py-3 text-center font-bold">Ítems</th>
                        <th class="px-5 py-3 text-right font-bold">Total</th>
                        <th class="px-5 py-3 text-center font-bold">Estado</th>
                        <th class="px-5 py-3 text-right font-bold">Acciones</th>
                    </tr>
                </thead>
                <tbody class="tabular">
                    @forelse ($filas as $i => $c)
                        @php
                            $badge = match ($c['estado']) {
                                'recibida' => 'bg-green-100 text-green-700',
                                'aprobada' => 'bg-sky-100 text-sky-700',
                                'rechazada' => 'bg-red-100 text-red-700',
                                default => 'bg-amber-100 text-amber-700',
                            };
                        @endphp
                        <tr class="border-t border-gray-100 {{ $i % 2 ? 'bg-gray-50/50' : '' }} hover:bg-brand-soft/40 {{ $highlight === $c['num'] ? 'bg-brand-soft ring-2 ring-inset ring-brand' : '' }}" wire:key="compra-{{ $c['num'] }}" @if ($highlight === $c['num']) x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'center' })" @endif>
                            <td class="px-5 py-3"><a href="#" class="font-bold text-ink hover:text-brand hover:underline">{{ $c['num'] }}</a></td>
                            <td class="px-5 py-3 text-graphite">{{ $c['fecha'] }}</td>
                            <td class="px-5 py-3 font-semibold text-ink">{{ $c['prov'] }}</td>
                            <td class="px-5 py-3 text-graphite">{{ $c['factura'] }}</td>
                            <td class="px-5 py-3 text-graphite">{{ $c['local'] }}</td>
                            <td class="px-5 py-3 text-center text-graphite">{{ $c['items'] }}</td>
                            <td class="px-5 py-3 text-right font-bold text-ink">${{ number_format($c['total'], 2, ',', '.') }}</td>
                            <td class="px-5 py-3 text-center">
                                <span class="inline-block rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wide {{ $badge }}">{{ $c['estado'] }}</span>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    {{-- Ver detalle: qué llegará (aprobada) o qué se recibió (recibida) --}}
                                    <button wire:click="verItems({{ $c['id'] }})" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-graphite transition hover:bg-gray-100 hover:text-brand"
                                            title="{{ $c['estado'] === 'recibida' ? 'Ver lo recibido' : ($c['estado'] === 'aprobada' ? 'Ver lo que llegará' : 'Ver detalle') }}">
                                        <span class="material-symbols-outlined text-[20px]">visibility</span>
                                    </button>
                                    @if ($c['estado'] === 'pendiente')
                                        @puede('aprobar_compras')
                                            <button wire:click="aprobar({{ $c['id'] }})" class="rounded-lg bg-success px-3 py-1.5 text-xs font-bold text-white transition hover:brightness-95">Aprobar</button>
                                            <button wire:click="rechazar({{ $c['id'] }})" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-bold text-graphite transition hover:bg-gray-50">Rechazar</button>
                                        @endpuede
                                    @elseif ($c['estado'] === 'aprobada')
                                        @puede('aprobar_compras')
                                            <button wire:click="recibir({{ $c['id'] }})" class="flex items-center gap-1 rounded-lg bg-brand px-3 py-1.5 text-xs font-bold text-white transition hover:bg-brand-dark">
                                                <span class="material-symbols-outlined text-[16px]">inventory_2</span> Recibir
                                            </button>
                                        @endpuede
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-5 py-10 text-center text-sm text-muted">
                                <span class="material-symbols-outlined mb-1 block text-3xl">shopping_cart_off</span>
                                No hay compras con esos filtros.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-panel>

    {{-- ===== Modal registrar compra ===== --}}
    @if ($modal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" wire:click="$set('modal', false)"></div>
            <div class="relative z-10 max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 class="text-base font-extrabold text-ink">Registrar compra</h3>
                    <button wire:click="$set('modal', false)" class="text-muted hover:text-danger"><span class="material-symbols-outlined">close</span></button>
                </div>

                <form wire:submit="guardarCompra" class="p-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="sm:col-span-1">
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Proveedor</label>
                            <select wire:model="cProvId" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                                <option value="">Seleccionar…</option>
                                @foreach ($proveedores as $prov)
                                    <option value="{{ $prov->id }}">{{ $prov->nombre }}</option>
                                @endforeach
                            </select>
                            @error('cProvId') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Local</label>
                            <select wire:model="cLocalId" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                                @foreach ($localesActivos as $l)
                                    <option value="{{ $l->id }}">{{ $l->nombre }}</option>
                                @endforeach
                            </select>
                            @error('cLocalId') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">N° de factura *</label>
                            <input type="text" wire:model="cFactura" placeholder="A 0001-..."
                                   class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                            @error('cFactura') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Renglones de la compra (productos existentes) --}}
                    <div class="mt-4">
                        <div class="mb-2 flex items-center justify-between">
                            <p class="text-xs font-bold uppercase tracking-wide text-muted">Mercadería</p>
                            <button type="button" wire:click="agregarItem" class="inline-flex items-center gap-1 text-xs font-bold text-brand hover:text-brand-dark">
                                <span class="material-symbols-outlined text-[18px]">add</span> Agregar renglón
                            </button>
                        </div>
                        @error('cItems') <p class="mb-2 text-xs font-semibold text-danger">{{ $message }}</p> @enderror

                        <div class="space-y-2" x-data @click.outside="$wire.buscandoEn !== null && $wire.cerrarBusqueda()">
                            @foreach ($cItems as $i => $it)
                                <div class="flex items-start gap-2" wire:key="citem-{{ $i }}">
                                    <div class="relative flex-1">
                                        <input type="text" autocomplete="off" wire:model.live.debounce.300ms="cItems.{{ $i }}.desc"
                                               placeholder="Buscar producto por nombre, código o proveedor…"
                                               class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                                        @error("cItems.$i.producto_id") <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror

                                        @if ($buscandoEn === $i && count($this->resultados))
                                            <div class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-xl">
                                                <p class="border-b border-gray-100 bg-gray-50 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wide text-muted">Productos — busca por nombre · código · proveedor</p>
                                                @foreach ($this->resultados as $r)
                                                    <button type="button" wire:click="elegirProducto({{ $i }}, {{ $r['id'] }})"
                                                            class="flex w-full items-center justify-between gap-2 border-b border-gray-50 px-3 py-2 text-left hover:bg-brand-soft">
                                                        <span>
                                                            <span class="text-sm font-semibold text-ink">{{ $r['nom'] }}</span>
                                                            <span class="block text-[11px] text-muted">{{ $r['cod'] }} · {{ $r['prov'] }}</span>
                                                        </span>
                                                        <span class="tabular whitespace-nowrap text-xs font-bold text-brand">costo ${{ number_format($r['costo'], 2, ',', '.') }}</span>
                                                    </button>
                                                @endforeach
                                            </div>
                                        @elseif ($buscandoEn === $i && mb_strlen(trim($it['desc'] ?? '')) >= 2)
                                            <div class="absolute z-30 mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-muted shadow-xl">Sin productos. Cargalos primero en <b>Stock</b>.</div>
                                        @endif
                                    </div>
                                    <div class="w-20">
                                        <input type="number" min="1" wire:model.live="cItems.{{ $i }}.cant" placeholder="Cant."
                                               class="w-full rounded-lg border border-gray-200 px-2 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                                        @error("cItems.$i.cant") <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="w-28">
                                        <input type="number" step="0.01" min="0" wire:model.live="cItems.{{ $i }}.precio" placeholder="Costo"
                                               class="w-full rounded-lg border border-gray-200 px-2 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                                        @error("cItems.$i.precio") <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                                    </div>
                                    <button type="button" wire:click="quitarItem({{ $i }})" class="mt-1.5 text-muted hover:text-danger" title="Quitar">
                                        <span class="material-symbols-outlined text-[20px]">delete</span>
                                    </button>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-3 flex justify-between border-t border-gray-200 pt-3">
                            <span class="font-bold text-ink">Subtotal mercadería</span>
                            <span class="tabular text-base font-extrabold text-ink">${{ number_format($this->totalCompra, 2, ',', '.') }}</span>
                        </div>
                    </div>

                    {{-- Desglose de la factura (IVA / flete) --}}
                    <div class="mt-4 rounded-xl border border-gray-100 bg-gray-50/60 p-3">
                        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-muted">Factura · IVA y flete</p>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="mb-1 block text-[11px] font-bold uppercase text-muted">IVA 21%</label>
                                <input type="number" step="0.01" min="0" wire:model.live="cIva21" placeholder="0,00"
                                       class="w-full rounded-lg border border-gray-200 px-2.5 py-1.5 text-sm outline-none focus:border-brand" />
                                @error('cIva21') <p class="mt-0.5 text-[11px] text-danger">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-[11px] font-bold uppercase text-muted">IVA 10,5%</label>
                                <input type="number" step="0.01" min="0" wire:model.live="cIva105" placeholder="0,00"
                                       class="w-full rounded-lg border border-gray-200 px-2.5 py-1.5 text-sm outline-none focus:border-brand" />
                                @error('cIva105') <p class="mt-0.5 text-[11px] text-danger">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-[11px] font-bold uppercase text-muted">Flete / otros</label>
                                <input type="number" step="0.01" min="0" wire:model.live="cFlete" placeholder="0,00"
                                       class="w-full rounded-lg border border-gray-200 px-2.5 py-1.5 text-sm outline-none focus:border-brand" />
                                @error('cFlete') <p class="mt-0.5 text-[11px] text-danger">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="mt-3 flex justify-between border-t border-gray-200 pt-2">
                            <span class="font-bold text-ink">Total factura</span>
                            <span class="tabular text-base font-extrabold text-brand">
                                ${{ number_format($this->totalCompra + (float) ($cIva21 ?: 0) + (float) ($cIva105 ?: 0) + (float) ($cFlete ?: 0), 2, ',', '.') }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <button type="button" wire:click="$set('modal', false)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold text-graphite hover:bg-gray-50">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-dark">Registrar compra</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ===== Modal ver detalle de ítems ===== --}}
    @if ($verModal && $verData)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" wire:click="$set('verModal', false)"></div>
            <div class="relative z-10 w-full max-w-2xl rounded-2xl bg-white p-5 shadow-xl">
                <div class="mb-3 flex items-start justify-between">
                    <div>
                        <h3 class="text-lg font-extrabold text-ink">{{ $verData['numero'] }} · {{ $verData['prov'] }}</h3>
                        <p class="text-xs text-muted">
                            {{ $verData['local'] }}
                            @if ($verData['factura']) · Factura {{ $verData['factura'] }} @endif
                            @if ($verData['recibida'] && $verData['recibido_at']) · Recibida {{ $verData['recibido_at'] }} @endif
                        </p>
                    </div>
                    <button wire:click="$set('verModal', false)" class="text-muted hover:text-danger"><span class="material-symbols-outlined">close</span></button>
                </div>

                <p class="mb-2 text-sm font-bold {{ $verData['recibida'] ? 'text-green-700' : 'text-amber-700' }}">
                    @if ($verData['recibida']) Mercadería recibida @else Lo que debería llegar (aún no recibida) @endif
                </p>

                <div class="max-h-[60vh] overflow-auto rounded-xl border border-gray-100">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-[11px] uppercase text-muted">
                            <tr>
                                <th class="px-3 py-2">Producto</th>
                                <th class="px-3 py-2 text-center">Pedido</th>
                                @if ($verData['recibida'])
                                    <th class="px-3 py-2 text-center">Recibido</th>
                                    <th class="px-3 py-2 text-center">Defect.</th>
                                    <th class="px-3 py-2 text-center">No llegó</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($verData['items'] as $it)
                                <tr class="border-t border-gray-100">
                                    <td class="px-3 py-2">
                                        <p class="font-semibold text-ink">{{ $it['desc'] }}</p>
                                        <p class="text-[11px] text-muted">Cód: {{ $it['cod'] }}@if ($it['nota']) · {{ $it['nota'] }} @endif</p>
                                    </td>
                                    <td class="px-3 py-2 text-center font-semibold">{{ $it['pedida'] }}</td>
                                    @if ($verData['recibida'])
                                        <td class="px-3 py-2 text-center font-bold text-green-700">{{ $it['recibida'] }}</td>
                                        <td class="px-3 py-2 text-center {{ $it['defectuosa'] ? 'font-bold text-amber-700' : 'text-muted' }}">{{ $it['defectuosa'] }}</td>
                                        <td class="px-3 py-2 text-center {{ $it['faltante'] ? 'font-bold text-red-600' : 'text-muted' }}">{{ $it['faltante'] }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex justify-end">
                    <button wire:click="$set('verModal', false)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold text-graphite hover:bg-gray-50">Cerrar</button>
                </div>
            </div>
        </div>
    @endif
</div>
