<div class="space-y-6">

    {{-- Encabezado de sección --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-ink">Stock</h1>
            <p class="text-sm text-muted">{{ $sub === 'consulta' ? 'Consultá stock y precio por sucursal' : 'Productos, stock y precio por sucursal' }}</p>
        </div>
        @if ($sub === 'catalogo')
            @puede('gestionar_stock')
                <button wire:click="nuevoProducto" class="flex items-center gap-2 rounded-lg bg-brand px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-dark">
                    <span class="material-symbols-outlined text-[20px]">add</span> Nuevo producto
                </button>
            @endpuede
        @endif
    </div>

    {{-- ============================================================ --}}
    {{-- SUB: CONSULTA                                                --}}
    {{-- ============================================================ --}}
    @if ($sub === 'consulta')
        @if ($consultaMsg)
            <div class="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm font-semibold text-green-700">
                <span class="material-symbols-outlined text-[18px]">check_circle</span> {{ $consultaMsg }}
            </div>
        @endif

        @if ($productoConsulta)
            {{-- Detalle --}}
            @php $hayDif = abs($productoConsulta['pa'] - $productoConsulta['pb']) >= 0.01; @endphp
            <div class="max-w-2xl">
                <x-panel>
                    <div class="p-5">
                        <button wire:click="volverConsulta" class="mb-3 flex items-center gap-1 self-start text-sm font-bold text-graphite hover:text-brand">
                            <span class="material-symbols-outlined text-[20px]">arrow_back</span> Volver
                        </button>

                        <div class="flex items-center gap-3">
                            <x-product-image :src="$productoConsulta['img']" :icon="$productoConsulta['icon']" icon-class="text-[34px]"
                                             class="h-16 w-16 rounded-2xl border border-gray-200" />
                            <div>
                                <h2 class="text-lg font-extrabold leading-tight text-ink">{{ $productoConsulta['nom'] }}</h2>
                                <p class="text-xs font-semibold text-muted">{{ $productoConsulta['cod'] }}</p>
                            </div>
                        </div>

                        @if ($productoConsulta['img'])
                            <img src="{{ $productoConsulta['img'] }}" loading="lazy" alt="{{ $productoConsulta['nom'] }}"
                                 class="mt-3 max-h-64 w-full rounded-xl border border-gray-100 object-contain bg-gray-50" />
                        @endif

                        @if ($productoConsulta['desc'])
                            <p class="mt-3 text-sm text-graphite">{{ $productoConsulta['desc'] }}</p>
                        @endif

                        @if (! empty($productoConsulta['detalles']))
                            <dl class="mt-3 divide-y divide-gray-100 rounded-xl border border-gray-100">
                                @foreach ($productoConsulta['detalles'] as $d)
                                    <div class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                                        <dt class="font-semibold text-muted">{{ $d['clave'] }}</dt>
                                        <dd class="text-right font-bold text-ink">{{ $d['valor'] }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif

                        <h3 class="mt-5 mb-2 text-xs font-bold uppercase tracking-wide text-muted">Disponibilidad por local</h3>
                        <div class="space-y-2">
                            @foreach ([[$productoConsulta['la'], $productoConsulta['sa'], $productoConsulta['pa']], [$productoConsulta['lb'], $productoConsulta['sb'], $productoConsulta['pb']]] as [$loc, $cant, $precio])
                                <div class="flex items-center justify-between rounded-xl border border-gray-100 bg-white p-3 shadow-soft">
                                    <div class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-[20px] text-graphite">store</span>
                                        <span class="text-sm font-bold text-ink">{{ $loc }}</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $cant > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                            {{ $cant > 0 ? $cant . ' u.' : 'Sin stock' }}
                                        </span>
                                        <span class="tabular text-sm font-extrabold text-ink">${{ number_format($precio, 2, ',', '.') }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if ($hayDif)
                            <p class="mt-2 flex items-center gap-1 text-xs font-semibold text-brand">
                                <span class="material-symbols-outlined text-[16px]">info</span>
                                Precio distinto entre locales (dif. ${{ number_format(abs($productoConsulta['pa'] - $productoConsulta['pb']), 2, ',', '.') }})
                            </p>
                        @endif

                        <p class="mt-4 text-xs text-muted">Proveedor: <span class="font-semibold text-graphite">{{ $productoConsulta['prov'] }}</span></p>

                        <div class="mt-6 space-y-2">
                            @puede('crear_venta')
                                <a href="{{ route('ventas.nueva', ['producto' => $productoConsulta['cod']]) }}" wire:navigate
                                   class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand py-3 text-sm font-bold text-white transition hover:bg-brand-dark">
                                    <span class="material-symbols-outlined text-[20px]">point_of_sale</span> Cargar venta con este producto
                                </a>
                            @endpuede
                            <button wire:click="solicitarReposicion('{{ $productoConsulta['cod'] }}')" class="flex w-full items-center justify-center gap-2 rounded-xl border-2 border-gray-200 py-3 text-sm font-bold text-graphite transition hover:border-brand hover:text-brand">
                                <span class="material-symbols-outlined text-[20px]">inventory</span> Solicitar reposición
                            </button>
                        </div>
                    </div>
                </x-panel>
            </div>
        @else
            {{-- Buscador + resultados --}}
            <div class="max-w-3xl">
                <x-panel title="Consulta de stock">
                    <div class="p-5">
                        <p class="mb-3 text-sm text-muted">Buscá un producto para ver stock y precio por local.</p>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-[22px] text-muted">search</span>
                            <input type="text" wire:model.live.debounce.300ms="buscar" inputmode="search"
                                   placeholder="Nombre o código del producto..."
                                   class="w-full rounded-xl border border-gray-200 bg-white py-3.5 pl-12 pr-4 text-base font-medium outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20" />
                        </div>

                        <div class="mt-4">
                            @if (empty($resultados))
                                @if (trim($buscar) === '')
                                    <div class="flex flex-col items-center justify-center py-12 text-center text-muted">
                                        <span class="material-symbols-outlined mb-2 text-5xl text-gray-300">inventory_2</span>
                                        <p class="text-sm font-medium">Todavía no hay productos cargados.</p>
                                    </div>
                                @else
                                    <div class="flex flex-col items-center justify-center py-12 text-center text-muted">
                                        <span class="material-symbols-outlined mb-2 text-5xl text-gray-300">search_off</span>
                                        <p class="text-sm font-medium">No encontramos "{{ $buscar }}".</p>
                                    </div>
                                @endif
                            @else
                                <div class="rounded-xl border border-gray-200 bg-gray-50/60 p-3 shadow-inner">
                                    <div class="space-y-2.5">
                                    @foreach ($resultados as $p)
                                        <button wire:click="seleccionar('{{ $p['cod'] }}')" wire:key="cr-{{ $p['cod'] }}"
                                                class="flex w-full items-center gap-3 rounded-xl border border-gray-100 bg-white p-3 text-left shadow-soft transition hover:border-brand/40">
                                            <x-product-image :src="$p['img']" :icon="$p['icon']" icon-class="text-[24px]"
                                                             class="h-12 w-12 rounded-xl border border-gray-100" />
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate font-bold text-ink">{{ $p['nom'] }}</p>
                                                <p class="text-xs text-muted">{{ $p['cod'] }} · ${{ number_format(max($p['pa'], $p['pb']), 2, ',', '.') }}</p>
                                                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $p['sa'] > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $p['la'] }}: {{ $p['sa'] }}</span>
                                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $p['sb'] > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $p['lb'] }}: {{ $p['sb'] }}</span>
                                                </div>
                                            </div>
                                            <span class="material-symbols-outlined text-[22px] text-gray-300">chevron_right</span>
                                        </button>
                                    @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </x-panel>
            </div>
        @endif

    @else
        {{-- ============================================================ --}}
        {{-- SUB: CATÁLOGO (admin)                                       --}}
        {{-- ============================================================ --}}
        @if (session('stockMsg'))
            <div class="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm font-semibold text-green-700">
                <span class="material-symbols-outlined text-[18px]">check_circle</span> {{ session('stockMsg') }}
            </div>
        @endif

        {{-- Stats rápidas --}}
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <x-kpi-card variant="white" title="Productos" :value="$stats['total']" icon="inventory_2" subtitle="En catálogo" />
            <x-kpi-card variant="red"   title="Stock Bajo" :value="$stats['bajo']" icon="production_quantity_limits" subtitle="Ítems bajo el mínimo" />
            <x-kpi-card variant="blue"  title="Diferencia de Precio" :value="$stats['dif']" icon="price_change" subtitle="Mismo producto entre sucursales" />
        </div>

        {{-- Toolbar de filtros --}}
        <x-panel title="Catálogo de productos">
            <x-slot:actions>
                <span class="text-xs font-semibold text-muted">{{ count($filas) }} resultado(s)</span>
            </x-slot:actions>

            <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-5">
                <div class="relative min-w-[240px] flex-1">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[20px] text-muted">search</span>
                    <input type="text" wire:model.live.debounce.300ms="buscar" placeholder="Buscar por nombre o código..."
                           class="w-full rounded-full border border-gray-200 bg-gray-50 py-2 pl-10 pr-4 text-sm font-medium outline-none transition focus:border-brand focus:bg-white focus:ring-2 focus:ring-brand/20" />
                </div>

                <div class="flex overflow-hidden rounded-lg border border-gray-200 text-sm font-bold">
                    <button wire:click="$set('local', 'todos')" class="px-3 py-2 transition {{ $local === 'todos' ? 'bg-brand text-white' : 'text-graphite hover:bg-gray-50' }}">Todas</button>
                    @foreach ($locales as $l)
                        <button wire:click="$set('local', '{{ $l->id }}')" class="px-3 py-2 transition {{ (string) $local === (string) $l->id ? 'bg-brand text-white' : 'text-graphite hover:bg-gray-50' }}">{{ $l->nombre }}</button>
                    @endforeach
                </div>

                <select wire:model.live="categoria" class="rounded-lg border border-gray-200 py-2 pl-3 pr-8 text-sm font-medium text-ink outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                    <option value="todas">Todas las categorías</option>
                    @foreach ($categorias as $c)
                        <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                    @endforeach
                </select>

                <select wire:model.live="proveedor" class="rounded-lg border border-gray-200 py-2 pl-3 pr-8 text-sm font-medium text-ink outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" title="Filtrar por proveedor (mismo producto puede existir por distinto proveedor)">
                    <option value="todos">Todos los proveedores</option>
                    @foreach ($proveedores as $prov)
                        <option value="{{ $prov->id }}">{{ $prov->nombre }}</option>
                    @endforeach
                </select>

                <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-graphite">
                    <input type="checkbox" wire:model.live="soloBajo" class="rounded border-gray-300 text-brand focus:ring-brand/30" /> Stock bajo
                </label>
                <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-graphite">
                    <input type="checkbox" wire:model.live="soloDiferencia" class="rounded border-gray-300 text-brand focus:ring-brand/30" /> Con diferencia
                </label>

                <button wire:click="limpiar" class="ml-auto flex items-center gap-1 text-xs font-bold text-muted hover:text-brand">
                    <span class="material-symbols-outlined text-[16px]">close</span> Limpiar
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wide text-muted">
                            <th class="px-5 py-3 font-bold">Producto</th>
                            <th class="px-5 py-3 font-bold">Categoría</th>
                            @foreach ($locales as $l)
                                <th class="px-5 py-3 text-center font-bold">Stock {{ $l->nombre }}</th>
                                <th class="px-5 py-3 text-right font-bold">Precio {{ $l->nombre }}</th>
                            @endforeach
                            <th class="px-5 py-3 text-right font-bold">Dif.</th>
                            <th class="px-5 py-3 text-right font-bold"></th>
                        </tr>
                    </thead>
                    <tbody class="tabular">
                        @forelse ($filas as $i => $p)
                            <tr class="border-t border-gray-100 {{ $i % 2 ? 'bg-gray-50/50' : '' }} hover:bg-brand-soft/40" wire:key="prod-{{ $p['id'] }}">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <x-product-image :src="$p['img']" :icon="$p['icon']" icon-class="text-[20px]"
                                                         class="h-9 w-9 rounded-lg border border-gray-100" />
                                        <div>
                                            <p class="font-semibold text-ink">{{ $p['nom'] }}</p>
                                            <p class="text-xs text-muted">{{ $p['cod'] }} · {{ $p['prov'] }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-graphite">{{ $p['cat'] }}</td>

                                @foreach ($locales as $l)
                                    @php $cell = $p['porLocal'][$l->id]; @endphp
                                    <td class="px-5 py-3 text-center">
                                        <span class="inline-flex min-w-[2.5rem] justify-center rounded-full px-2 py-0.5 text-xs font-bold {{ $cell['bajo'] ? 'bg-kpiRed-bg text-kpiRed-fg' : 'bg-gray-100 text-graphite' }}">{{ $cell['cantidad'] }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-right text-graphite">${{ number_format($cell['precio'], 2, ',', '.') }}</td>
                                @endforeach

                                <td class="px-5 py-3 text-right">
                                    @if ($p['dif'] > 0)
                                        <span class="inline-flex items-center gap-0.5 font-bold text-brand">
                                            <span class="material-symbols-outlined text-[18px]">price_change</span>${{ number_format($p['dif'], 2, ',', '.') }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>

                                <td class="px-5 py-3 text-right">
                                    @puede('gestionar_stock')
                                        <button wire:click="editarProducto({{ $p['id'] }})" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-graphite transition hover:bg-gray-100 hover:text-brand" title="Editar producto">
                                            <span class="material-symbols-outlined text-[20px]">edit</span>
                                        </button>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endpuede
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 4 + count($locales) * 2 }}" class="px-5 py-10 text-center text-sm text-muted">
                                    <span class="material-symbols-outlined mb-1 block text-3xl">search_off</span>
                                    No se encontraron productos con esos filtros.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-panel>

        {{-- ===== Modal alta / edición de producto ===== --}}
        @if ($modal)
            <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" wire:click="$set('modal', false)"></div>
                <div class="relative z-10 max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                        <h3 class="text-base font-extrabold text-ink">{{ $editando ? 'Editar producto' : 'Nuevo producto' }}</h3>
                        <button wire:click="$set('modal', false)" class="text-muted hover:text-danger"><span class="material-symbols-outlined">close</span></button>
                    </div>

                    <form wire:submit="guardarProducto" class="p-5">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-bold uppercase text-muted">Nombre del producto</label>
                                <input type="text" wire:model="pNombre" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                                @error('pNombre') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-bold uppercase text-muted">Código</label>
                                <input type="text" wire:model="pCodigo" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                                @error('pCodigo') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-bold uppercase text-muted">Categoría</label>
                                <select wire:model="pCategoriaId" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                                    <option value="">Seleccionar…</option>
                                    @foreach ($categorias as $c)
                                        <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                                    @endforeach
                                </select>
                                @error('pCategoriaId') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-bold uppercase text-muted">Proveedor</label>
                                <select wire:model.live="pProveedorId" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                                    <option value="">Seleccionar…</option>
                                    @foreach ($proveedores as $prov)
                                        <option value="{{ $prov->id }}">{{ $prov->nombre }}</option>
                                    @endforeach
                                </select>
                                @error('pProveedorId') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-bold uppercase text-muted">Precio neto <span class="font-normal normal-case text-gray-400">(de lista / factura)</span></label>
                                <input type="number" step="0.01" wire:model.live="pPrecioCompra" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                                @error('pPrecioCompra') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="mt-4 rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <div class="mb-2 flex items-center justify-between">
                                <p class="text-xs font-bold uppercase tracking-wide text-muted">Costeo</p>
                                <span class="text-[11px] text-muted">Neto → costo → precio de venta (conceptos en cascada)</span>
                            </div>

                            @if (! $pProveedorId)
                                <p class="py-2 text-sm text-muted">Seleccioná un proveedor para ver el costeo.</p>
                            @else
                                @php($d = $this->desgloseCosto)
                                <div class="space-y-1.5 text-sm">
                                    <div class="flex justify-between border-b border-gray-200 pb-1.5">
                                        <span class="text-graphite">Precio neto</span>
                                        <span class="tabular font-bold text-ink">${{ number_format($d['neto'], 2, ',', '.') }}</span>
                                    </div>

                                    {{-- Conceptos que recargan el COSTO --}}
                                    @foreach ($pConceptos as $i => $c)
                                        @if (($c['ambito'] ?? 'costo') === 'costo')
                                            <div class="flex items-center justify-between gap-2" wire:key="pconcepto-{{ $c['id'] }}">
                                                <label class="flex flex-1 items-center gap-2 {{ ($c['aplica'] ?? false) ? 'text-graphite' : 'text-muted line-through' }}">
                                                    <input type="checkbox" wire:model.live="pConceptos.{{ $i }}.aplica" class="rounded border-gray-300 text-brand focus:ring-brand/30" />
                                                    {{ $c['nombre'] }}
                                                </label>
                                                <div class="flex items-center gap-1">
                                                    <input type="number" step="0.01" min="0" wire:model.live="pConceptos.{{ $i }}.porcentaje"
                                                           class="w-16 rounded-lg border border-gray-200 px-2 py-1 text-right text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 {{ ($c['aplica'] ?? false) ? '' : 'opacity-40' }}" />
                                                    <span class="text-muted">%</span>
                                                    <span class="tabular w-20 text-right {{ ($c['aplica'] ?? false) ? 'text-graphite' : 'text-gray-300' }}">
                                                        ${{ number_format($d['montos'][$c['id']] ?? 0, 2, ',', '.') }}
                                                    </span>
                                                    <button type="button" wire:click="quitarConceptoDeProducto({{ $i }})" class="text-muted hover:text-danger" title="Quitar concepto"><span class="material-symbols-outlined text-[16px]">close</span></button>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach

                                    @if ($d['iva_pct'] > 0)
                                        <div class="flex items-center justify-between">
                                            <span class="text-graphite">IVA ({{ (float) $d['iva_pct'] }}%)</span>
                                            <span class="tabular text-graphite">${{ number_format($d['iva_monto'], 2, ',', '.') }}</span>
                                        </div>
                                    @endif

                                    <div class="mt-1 flex justify-between border-t border-gray-200 pt-1.5">
                                        <span class="font-bold text-ink">Costo puesto en depósito</span>
                                        <span class="tabular font-extrabold text-ink">${{ number_format($d['costo'], 2, ',', '.') }}</span>
                                    </div>

                                    {{-- Conceptos que recargan la VENTA (remarque, financiación, …) --}}
                                    @foreach ($pConceptos as $i => $c)
                                        @if (($c['ambito'] ?? 'costo') === 'venta')
                                            <div class="flex items-center justify-between gap-2 pt-1" wire:key="pconcepto-{{ $c['id'] }}">
                                                <label class="flex flex-1 items-center gap-2 {{ ($c['aplica'] ?? false) ? 'text-graphite' : 'text-muted line-through' }}">
                                                    <input type="checkbox" wire:model.live="pConceptos.{{ $i }}.aplica" class="rounded border-gray-300 text-brand focus:ring-brand/30" />
                                                    {{ $c['nombre'] }}
                                                </label>
                                                <div class="flex items-center gap-1">
                                                    <input type="number" step="0.01" min="0" wire:model.live="pConceptos.{{ $i }}.porcentaje"
                                                           class="w-16 rounded-lg border border-gray-200 px-2 py-1 text-right text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 {{ ($c['aplica'] ?? false) ? '' : 'opacity-40' }}" />
                                                    <span class="text-muted">%</span>
                                                    <span class="tabular w-20 text-right {{ ($c['aplica'] ?? false) ? 'text-graphite' : 'text-gray-300' }}">
                                                        ${{ number_format($d['montos'][$c['id']] ?? 0, 2, ',', '.') }}
                                                    </span>
                                                    <button type="button" wire:click="quitarConceptoDeProducto({{ $i }})" class="text-muted hover:text-danger" title="Quitar concepto"><span class="material-symbols-outlined text-[16px]">close</span></button>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach

                                    <div class="mt-1 flex justify-between border-t border-gray-200 pt-2">
                                        <span class="font-bold text-ink">Precio de venta</span>
                                        <span class="tabular text-base font-extrabold text-brand">${{ number_format($this->precioVenta, 2, ',', '.') }}</span>
                                    </div>

                                    {{-- Agregar un concepto puntual a este producto --}}
                                    @php($disponibles = $this->conceptosDisponibles)
                                    @if ($disponibles->isNotEmpty())
                                        <div class="mt-2 flex items-center gap-2 border-t border-dashed border-gray-200 pt-2">
                                            <select wire:model="conceptoAgregar" class="flex-1 rounded-lg border border-gray-200 px-2 py-1 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                                                <option value="">+ Agregar concepto…</option>
                                                @foreach ($disponibles as $cd)
                                                    <option value="{{ $cd->id }}">{{ $cd->nombre }} ({{ $cd->ambito === 'venta' ? 'venta' : 'costo' }})</option>
                                                @endforeach
                                            </select>
                                            <button type="button" wire:click="agregarConceptoAProducto" class="rounded-lg bg-brand px-3 py-1 text-sm font-bold text-white hover:bg-brand-dark">Agregar</button>
                                        </div>
                                    @endif
                                </div>
                            @endif
                            <p class="mt-2 text-[11px] text-muted">El costo aplica los conceptos de <b>costo</b> (en cascada){{ ($d['iva_pct'] ?? 0) > 0 ? ' + IVA' : '' }}; el precio de venta aplica los de <b>venta</b> (ej. Remarcar). Los defaults vienen de la <b>ficha del proveedor</b> y acá podés agregar/quitar conceptos por producto.</p>
                        </div>

                        <div class="mt-4">
                            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-muted">Imagen del producto</p>
                            <div class="flex items-start gap-4">
                                <div class="flex h-24 w-24 flex-shrink-0 items-center justify-center overflow-hidden rounded-xl border border-gray-200 bg-gray-50">
                                    @if ($pImagen)
                                        <img src="{{ $pImagen->temporaryUrl() }}" class="h-full w-full object-cover" alt="preview" />
                                    @elseif ($pImagenActual)
                                        <img src="{{ asset('storage/' . $pImagenActual) }}" class="h-full w-full object-cover" alt="imagen" />
                                    @else
                                        <span class="material-symbols-outlined text-[28px] text-gray-300">image</span>
                                    @endif
                                </div>
                                <div class="flex-1">
                                    <input type="file" wire:model="pImagen" accept="image/*"
                                           class="block w-full text-sm text-graphite file:mr-3 file:rounded-lg file:border-0 file:bg-brand-soft file:px-3 file:py-2 file:text-sm file:font-bold file:text-brand hover:file:bg-brand-soft/70" />
                                    <div wire:loading wire:target="pImagen" class="mt-1 text-xs text-muted">Subiendo…</div>
                                    @error('pImagen') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                                    @if ($pImagen || $pImagenActual)
                                        <button type="button" wire:click="quitarImagen" class="mt-2 text-xs font-semibold text-danger hover:underline">Quitar imagen</button>
                                    @endif
                                    <p class="mt-1 text-[11px] text-muted">JPG, PNG o WEBP, hasta 2&nbsp;MB.</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-muted">Descripción <span class="font-medium normal-case text-muted/70">(opcional)</span></label>
                            <textarea wire:model="pDescripcion" rows="2" placeholder="Detalle a mano del producto..."
                                      class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20"></textarea>
                            @error('pDescripcion') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                        </div>

                        <div class="mt-4">
                            <p class="mb-1 text-xs font-bold uppercase tracking-wide text-muted">Detalles importantes <span class="font-medium normal-case text-muted/70">(opcional)</span></p>
                            <p class="mb-2 text-[11px] text-muted">Mini-ficha de dato : valor (ej. Medida · 1,20m). Las filas vacías se ignoran.</p>
                            @foreach ($pDetalles as $i => $d)
                                <div wire:key="det-{{ $i }}" class="mb-2 flex items-center gap-2">
                                    <input type="text" wire:model="pDetalles.{{ $i }}.clave" placeholder="Dato (ej. Medida)"
                                           class="w-1/3 rounded-lg border border-gray-200 px-2.5 py-1.5 text-sm outline-none focus:border-brand" />
                                    <input type="text" wire:model="pDetalles.{{ $i }}.valor" placeholder="Valor (ej. 1,20m)"
                                           class="flex-1 rounded-lg border border-gray-200 px-2.5 py-1.5 text-sm outline-none focus:border-brand" />
                                    <button type="button" wire:click="quitarDetalle({{ $i }})" class="text-gray-300 hover:text-danger"><span class="material-symbols-outlined text-[20px]">delete</span></button>
                                </div>
                            @endforeach
                            <button type="button" wire:click="agregarDetalle" class="flex items-center gap-1 text-sm font-bold text-brand hover:text-brand-dark">
                                <span class="material-symbols-outlined text-[18px]">add</span> Agregar detalle
                            </button>
                        </div>

                        <div class="mt-4">
                            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-muted">Stock por sucursal</p>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                @foreach ($locales as $l)
                                    <div>
                                        <label class="mb-1 block text-xs font-bold uppercase text-muted">{{ $l->nombre }}</label>
                                        <input type="number" min="0" wire:model="pStock.{{ $l->id }}" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                                    </div>
                                @endforeach
                                <div>
                                    <label class="mb-1 block text-xs font-bold uppercase text-muted">Stock mínimo</label>
                                    <input type="number" min="0" wire:model="pMin" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                                    @error('pMin') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <p class="mb-1 text-xs font-bold uppercase tracking-wide text-muted">Productos sugeridos (venta cruzada)</p>
                            <p class="mb-2 text-[11px] text-muted">Se ofrecen al cargar este producto en una nota de pedido.</p>

                            @if (! empty($pSugeridos))
                                <div class="mb-2 flex flex-wrap gap-2">
                                    @foreach ($pSugeridos as $s)
                                        <span wire:key="sug-{{ $s['id'] }}" class="flex items-center gap-1.5 rounded-lg bg-brand-soft px-2.5 py-1.5 text-sm font-semibold text-brand">
                                            {{ $s['nom'] }} <span class="text-[11px] text-muted">· {{ $s['cod'] }}</span>
                                            <button type="button" wire:click="quitarSugerencia({{ $s['id'] }})" class="text-brand/70 hover:text-danger"><span class="material-symbols-outlined text-[16px]">close</span></button>
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            <div class="relative">
                                <input type="text" wire:model.live.debounce.300ms="sugBuscar" placeholder="Buscar producto a sugerir por nombre o código..."
                                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                                @if (! empty($this->sugResultados))
                                    <div class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-xl">
                                        @foreach ($this->sugResultados as $r)
                                            <button type="button" wire:click="agregarSugerencia({{ $r['id'] }})" wire:key="sugres-{{ $r['id'] }}"
                                                    class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-brand-soft/50">
                                                <span class="font-bold text-ink">{{ $r['nom'] }}</span>
                                                <span class="text-xs text-muted">{{ $r['cod'] }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <label class="mt-2 flex cursor-pointer items-center gap-1.5 text-xs font-semibold text-graphite">
                                <input type="checkbox" wire:model="pSugReciproco" class="rounded border-gray-300 text-brand focus:ring-brand/30" />
                                Agregar también a la inversa (que cada sugerido ofrezca este producto)
                            </label>
                        </div>

                        <div class="mt-5 flex justify-end gap-2 border-t border-gray-100 pt-4">
                            <button type="button" wire:click="$set('modal', false)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold text-graphite hover:bg-gray-50">Cancelar</button>
                            <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-dark">{{ $editando ? 'Guardar cambios' : 'Crear producto' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endif
</div>
