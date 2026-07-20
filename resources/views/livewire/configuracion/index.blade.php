<div class="space-y-6">

    <div>
        <h1 class="text-2xl font-extrabold text-ink">Configuración</h1>
        <p class="text-sm text-muted">Roles, permisos y parámetros del sistema</p>
    </div>

    @if ($mensaje)
        <div class="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm font-semibold text-green-700">
            <span class="material-symbols-outlined text-[18px]">check_circle</span> {{ $mensaje }}
        </div>
    @endif

    {{-- ===== Roles ===== --}}
    @if ($sub === 'roles')
    <x-panel title="Roles">
        <div class="flex flex-wrap items-end gap-3 border-b border-gray-100 p-5">
            <div class="flex-1">
                <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-muted">Nuevo rol</label>
                <input type="text" wire:model.blur="nuevoRol" wire:keydown.enter="agregarRol" placeholder="Ej: Encargado de depósito"
                       class="w-full max-w-xs rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
            </div>
            <button type="button" wire:click="agregarRol" class="flex items-center gap-2 rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-dark">
                <span class="material-symbols-outlined text-[20px]">add</span> Crear rol
            </button>
        </div>
        @php
            // Clases literales (necesarias para que Tailwind no las purgue).
            $badgeBg = [
                'gray' => 'bg-gray-100 text-graphite', 'brand' => 'bg-brand-soft text-brand',
                'blue' => 'bg-sky-100 text-sky-700', 'green' => 'bg-green-100 text-green-700',
                'red' => 'bg-red-100 text-red-700', 'purple' => 'bg-purple-100 text-purple-700',
                'amber' => 'bg-amber-100 text-amber-700',
            ];
            $swatchBg = [
                'gray' => 'bg-gray-400', 'brand' => 'bg-brand', 'blue' => 'bg-sky-500',
                'green' => 'bg-green-500', 'red' => 'bg-red-500', 'purple' => 'bg-purple-500', 'amber' => 'bg-amber-500',
            ];
        @endphp
        <div class="flex flex-wrap gap-3 p-5">
            @foreach ($roles as $clave => $nombre)
                @php $var = $colorRol[$clave] ?? 'gray'; $esSistema = in_array($clave, $rolesSistema); @endphp
                <div class="flex items-center gap-3 rounded-xl border border-gray-100 bg-white px-4 py-3 shadow-soft" wire:key="rol-{{ $clave }}">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg {{ $badgeBg[$var] ?? $badgeBg['gray'] }}"><span class="material-symbols-outlined text-[20px]">badge</span></span>
                    <div>
                        @if ($esSistema)
                            <p class="text-sm font-bold text-ink">{{ $nombre }}</p>
                        @else
                            <input type="text" wire:model.blur="roles.{{ $clave }}"
                                   class="w-40 rounded-md border border-transparent px-1 py-0.5 text-sm font-bold text-ink outline-none hover:border-gray-200 focus:border-brand focus:ring-2 focus:ring-brand/20" />
                        @endif
                        <p class="text-xs text-muted">{{ $usuariosPorRol[$clave] ?? 0 }} usuario(s)</p>
                        @unless ($esSistema)
                            <div class="mt-1.5 flex items-center gap-1">
                                @foreach (['gray','brand','blue','green','red','purple','amber'] as $col)
                                    <button type="button" wire:click="cambiarColorRol('{{ $clave }}', '{{ $col }}')"
                                            title="{{ ucfirst($col) }}"
                                            class="h-4 w-4 rounded-full {{ $swatchBg[$col] }} ring-offset-1 transition {{ $var === $col ? 'ring-2 ring-ink' : 'ring-1 ring-gray-200 hover:ring-gray-400' }}"></button>
                                @endforeach
                            </div>
                        @endunless
                    </div>
                    @if ($esSistema)
                        <span class="ml-2 rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold uppercase text-graphite">Sistema</span>
                    @else
                        <button wire:click="eliminarRol('{{ $clave }}')" class="ml-2 text-muted transition hover:text-danger" title="Eliminar rol"><span class="material-symbols-outlined text-[20px]">delete</span></button>
                    @endif
                </div>
            @endforeach
        </div>
    </x-panel>
    @endif

    {{-- ===== Matriz de permisos ===== --}}
    @if ($sub === 'permisos')
    <x-panel title="Permisos por rol">
        <x-slot:actions>
            <button wire:click="guardarPermisos" class="flex items-center gap-1.5 rounded-lg bg-brand px-3 py-1.5 text-xs font-bold text-white transition hover:bg-brand-dark">
                <span class="material-symbols-outlined text-[16px]">save</span> Guardar
            </button>
        </x-slot:actions>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-[11px] uppercase tracking-wide text-muted">
                        <th class="px-5 py-3 font-bold">Permiso</th>
                        @foreach ($roles as $clave => $nombre)
                            <th class="px-3 py-3 text-center font-bold">{{ $nombre }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($grupos as $grupo => $permisos)
                        <tr class="bg-gray-50/70">
                            <td colspan="{{ count($roles) + 1 }}" class="px-5 py-2 text-[11px] font-extrabold uppercase tracking-wide text-graphite">{{ $grupo }}</td>
                        </tr>
                        @foreach ($permisos as $permKey => $permLabel)
                            <tr class="border-t border-gray-100 hover:bg-brand-soft/30">
                                <td class="px-5 py-2.5 font-semibold text-ink">{{ $permLabel }}</td>
                                @foreach ($roles as $rolKey => $rolNombre)
                                    <td class="px-3 py-2.5 text-center">
                                        <input type="checkbox" wire:model.live="matriz.{{ $rolKey }}.{{ $permKey }}"
                                               @disabled($rolKey === 'super_admin')
                                               class="h-4 w-4 rounded border-gray-300 text-brand focus:ring-brand/30 disabled:opacity-50" />
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="border-t border-gray-100 px-5 py-3 text-xs text-muted">El <b>Super Admin</b> tiene todos los permisos (no editable). Los permisos de "Solapas" controlan qué secciones ve cada rol; las "Acciones", qué puede ejecutar.</p>
    </x-panel>
    @endif

    {{-- ===== General ===== --}}
    @if ($sub === 'parametros')
    <x-panel title="Parámetros generales">
        <div class="flex flex-wrap items-end gap-4 p-5">
            <div>
                <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-muted">Stock mínimo global (alerta)</label>
                <p class="mb-2 text-xs text-muted">Cantidad por debajo de la cual un producto se informa como "stock bajo".</p>
                <input type="number" min="0" wire:model="stockMinimo"
                       class="w-32 rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
            </div>
            <button wire:click="guardarGeneral" class="flex items-center gap-2 rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-dark">
                <span class="material-symbols-outlined text-[20px]">save</span> Guardar
            </button>
        </div>
    </x-panel>
    @endif

    {{-- ===== Sucursales / locales ===== --}}
    @if ($sub === 'sucursales')
    @puede('gestionar_locales')
        <x-panel title="Sucursales / Locales">
            <x-slot:actions>
                <button wire:click="guardarSucursales" class="flex items-center gap-1.5 rounded-lg bg-brand px-3 py-1.5 text-xs font-bold text-white hover:bg-brand-dark"><span class="material-symbols-outlined text-[16px]">save</span> Guardar</button>
            </x-slot:actions>
            <p class="px-5 pt-4 text-xs text-muted">Renombrá tus locales o agregá nuevas sucursales si el negocio crece. El nombre se refleja en los selectores de Ventas y Compras.</p>
            <div class="space-y-3 p-5">
                @foreach ($sucursales as $i => $s)
                    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-gray-100 p-3 {{ $s['activo'] ? '' : 'opacity-60' }}" wire:key="suc-{{ $s['id'] }}">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-soft text-brand"><span class="material-symbols-outlined text-[20px]">store</span></span>
                        <div class="min-w-[150px] flex-1">
                            <label class="mb-1 block text-[10px] font-bold uppercase text-muted">Nombre</label>
                            <input type="text" wire:model="sucursales.{{ $i }}.nombre" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                        </div>
                        <div class="min-w-[150px] flex-1">
                            <label class="mb-1 block text-[10px] font-bold uppercase text-muted">Dirección</label>
                            <input type="text" wire:model="sucursales.{{ $i }}.direccion" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                        </div>
                        <div class="w-36">
                            <label class="mb-1 block text-[10px] font-bold uppercase text-muted">Teléfono</label>
                            <input type="text" wire:model="sucursales.{{ $i }}.telefono" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                        </div>
                        <button wire:click="toggleSucursal({{ $s['id'] }})" class="mt-4 flex items-center gap-1 rounded-lg border px-3 py-1.5 text-xs font-bold transition {{ $s['activo'] ? 'border-green-200 bg-green-50 text-green-700 hover:bg-green-100' : 'border-gray-200 text-graphite hover:bg-gray-50' }}" title="Activar / desactivar">
                            <span class="material-symbols-outlined text-[16px]">{{ $s['activo'] ? 'toggle_on' : 'toggle_off' }}</span> {{ $s['activo'] ? 'Activa' : 'Inactiva' }}
                        </button>
                    </div>
                @endforeach
            </div>
            <div class="flex flex-wrap items-end gap-3 border-t border-gray-100 p-5">
                <div><label class="mb-1 block text-xs font-bold uppercase text-muted">Nueva sucursal</label><input type="text" wire:model="nuevaSucursalNombre" placeholder="Ej: Sucursal Centro" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" /></div>
                <div><label class="mb-1 block text-xs font-bold uppercase text-muted">Dirección (opcional)</label><input type="text" wire:model="nuevaSucursalDir" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" /></div>
                <button wire:click="agregarSucursal" class="flex items-center gap-2 rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-dark"><span class="material-symbols-outlined text-[20px]">add</span> Agregar sucursal</button>
            </div>
        </x-panel>
    @endpuede
    @endif

    {{-- ===== Conceptos de precio ===== --}}
    @if ($sub === 'conceptos')
    <x-panel title="Conceptos de precio">
        <x-slot:actions>
            <button wire:click="guardarConceptos" class="flex items-center gap-1.5 rounded-lg bg-brand px-3 py-1.5 text-xs font-bold text-white hover:bg-brand-dark"><span class="material-symbols-outlined text-[16px]">save</span> Guardar</button>
        </x-slot:actions>
        <p class="px-5 pt-4 text-xs text-muted">Recargos (en %) que se aplican <b>en cascada</b> al calcular precios. El <b>ámbito</b> define sobre qué pega cada uno: <b>costo</b> (flete, gestión…) o <b>venta</b> (remarque/ganancia, financiación…). Se usan como default por proveedor y al crear productos en Stock.</p>
        <div class="space-y-2 p-5">
            @foreach ($conceptos as $i => $c)
                <div class="flex items-center gap-3" wire:key="cpt-{{ $c['id'] }}">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-soft text-brand"><span class="material-symbols-outlined text-[20px]">percent</span></span>
                    <span class="flex-1 text-sm font-bold text-ink">{{ $c['nombre'] }}</span>
                    <select wire:model="conceptos.{{ $i }}.ambito" class="rounded-lg border border-gray-200 px-2 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                        <option value="costo">Costo</option>
                        <option value="venta">Venta</option>
                    </select>
                    <div class="relative w-28">
                        <input type="number" step="0.01" wire:model="conceptos.{{ $i }}.porcentaje" class="w-full rounded-lg border border-gray-200 px-3 py-2 pr-7 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-muted">%</span>
                    </div>
                    <button wire:click="eliminarConcepto({{ $c['id'] }})" class="text-muted hover:text-danger" title="Eliminar"><span class="material-symbols-outlined text-[20px]">delete</span></button>
                </div>
            @endforeach
        </div>
        <div class="flex flex-wrap items-end gap-3 border-t border-gray-100 p-5">
            <div><label class="mb-1 block text-xs font-bold uppercase text-muted">Nuevo concepto</label><input type="text" wire:model="nuevoConceptoNombre" placeholder="Ej: Impuesto, Financiación…" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" /></div>
            <div><label class="mb-1 block text-xs font-bold uppercase text-muted">Ámbito</label>
                <select wire:model="nuevoConceptoAmbito" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                    <option value="costo">Costo</option>
                    <option value="venta">Venta</option>
                </select>
            </div>
            <div><label class="mb-1 block text-xs font-bold uppercase text-muted">%</label><input type="number" step="0.01" wire:model="nuevoConceptoPct" class="w-24 rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" /></div>
            <button wire:click="agregarConcepto" class="flex items-center gap-2 rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-dark"><span class="material-symbols-outlined text-[20px]">add</span> Agregar concepto</button>
        </div>
    </x-panel>
    @endif

    {{-- ===== Productos de crédito (planes) ===== --}}
    @if ($sub === 'creditos')
    <x-panel title="Productos de crédito">
        <x-slot:actions>
            <button wire:click="guardarPlanesCredito" class="flex items-center gap-1.5 rounded-lg bg-brand px-3 py-1.5 text-xs font-bold text-white hover:bg-brand-dark"><span class="material-symbols-outlined text-[16px]">save</span> Guardar</button>
        </x-slot:actions>
        <p class="px-5 pt-4 text-xs text-muted">Planes de financiación propia. Al elegir el plan en la venta, el sistema calcula anticipo, saldo financiado y cuotas. La <b>tasa</b> es el % de interés por período (día o semana) e incluye la mora de cuotas vencidas. <span class="font-bold text-amber-700">⚠️ tasas provisionales — confirmar con el cliente.</span></p>
        <div class="overflow-x-auto p-5">
            <table class="w-full text-left text-sm">
                <thead><tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-muted">
                    <th class="px-3 py-2.5 font-bold">Plan</th><th class="px-3 py-2.5 font-bold">Modalidad</th>
                    <th class="px-3 py-2.5 text-right font-bold">Anticipo %</th><th class="px-3 py-2.5 text-right font-bold">Tasa %/período</th>
                    <th class="px-3 py-2.5 text-right font-bold">Plazo</th><th class="px-3 py-2.5 text-center font-bold">Activo</th><th class="px-3 py-2.5"></th>
                </tr></thead>
                <tbody>
                    @forelse ($planesCredito as $i => $p)
                        <tr class="border-t border-gray-100" wire:key="plan-{{ $p['id'] }}">
                            <td class="px-3 py-2.5"><input type="text" wire:model="planesCredito.{{ $i }}.nombre" class="w-56 rounded-lg border border-gray-200 px-2 py-1 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" /></td>
                            <td class="px-3 py-2.5">
                                <select wire:model="planesCredito.{{ $i }}.modalidad" class="rounded-lg border border-gray-200 px-2 py-1 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                                    <option value="contado">Contado</option><option value="diario">Diario</option><option value="semanal">Semanal</option><option value="mensual">Mensual</option>
                                </select>
                            </td>
                            <td class="px-3 py-2.5 text-right"><input type="number" step="0.01" min="0" wire:model="planesCredito.{{ $i }}.anticipo_pct" class="w-20 rounded-lg border border-gray-200 px-2 py-1 text-right text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" /></td>
                            <td class="px-3 py-2.5 text-right"><input type="number" step="0.0001" min="0" wire:model="planesCredito.{{ $i }}.tasa_periodo" class="w-24 rounded-lg border border-gray-200 px-2 py-1 text-right text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" /></td>
                            <td class="px-3 py-2.5 text-right"><input type="number" step="1" min="0" wire:model="planesCredito.{{ $i }}.plazo_default" class="w-16 rounded-lg border border-gray-200 px-2 py-1 text-right text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" /> <span class="text-[11px] text-muted">{{ $p['unidad'] }}</span></td>
                            <td class="px-3 py-2.5 text-center"><input type="checkbox" wire:model="planesCredito.{{ $i }}.activo" class="rounded border-gray-300 text-brand focus:ring-brand/30" /></td>
                            <td class="px-3 py-2.5 text-right">
                                @if ($p['codigo'] !== 'contado')
                                    <button wire:click="eliminarPlanCredito({{ $p['id'] }})" class="text-muted hover:text-danger" title="Eliminar"><span class="material-symbols-outlined text-[20px]">delete</span></button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-muted">No hay planes definidos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap items-end gap-3 border-t border-gray-100 p-5">
            <div><label class="mb-1 block text-xs font-bold uppercase text-muted">Nuevo plan</label><input type="text" wire:model="nuevoPlanNombre" placeholder="Ej: 40% + 0,30 diario" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" /></div>
            <div><label class="mb-1 block text-xs font-bold uppercase text-muted">Modalidad</label>
                <select wire:model="nuevoPlanModalidad" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                    <option value="diario">Diario</option><option value="semanal">Semanal</option><option value="mensual">Mensual</option><option value="contado">Contado</option>
                </select>
            </div>
            <div><label class="mb-1 block text-xs font-bold uppercase text-muted">Anticipo %</label><input type="number" step="0.01" min="0" wire:model="nuevoPlanAnticipo" class="w-24 rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" /></div>
            <div><label class="mb-1 block text-xs font-bold uppercase text-muted">Tasa %/período</label><input type="number" step="0.0001" min="0" wire:model="nuevoPlanTasa" class="w-28 rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" /></div>
            <div><label class="mb-1 block text-xs font-bold uppercase text-muted">Plazo</label><input type="number" step="1" min="0" wire:model="nuevoPlanPlazo" class="w-20 rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" /></div>
            <button wire:click="agregarPlanCredito" class="flex items-center gap-2 rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-dark"><span class="material-symbols-outlined text-[20px]">add</span> Agregar plan</button>
        </div>
    </x-panel>
    @endif

    {{-- ===== Categorías de productos ===== --}}
    @if ($sub === 'categorias')
    @puede('gestionar_stock')
    <x-panel title="Categorías de productos">
        <x-slot:actions>
            <button wire:click="guardarCategorias" class="flex items-center gap-1.5 rounded-lg bg-brand px-3 py-1.5 text-xs font-bold text-white hover:bg-brand-dark"><span class="material-symbols-outlined text-[16px]">save</span> Guardar</button>
        </x-slot:actions>
        <p class="px-5 pt-4 text-xs text-muted">Categorías para clasificar productos en Stock. El <b>ícono</b> usa nombres de <a href="https://fonts.google.com/icons" target="_blank" class="text-brand hover:underline">Material Symbols</a> (ej: handyman, chair, warehouse).</p>
        <div class="space-y-2 p-5">
            @forelse ($categorias as $i => $c)
                <div class="flex items-center gap-3" wire:key="cat-{{ $c['id'] }}">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-soft text-brand"><span class="material-symbols-outlined text-[20px]">{{ $c['icono'] ?: 'category' }}</span></span>
                    <input type="text" wire:model="categorias.{{ $i }}.nombre" class="flex-1 rounded-lg border border-gray-200 px-3 py-2 text-sm font-bold outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                    <input type="text" wire:model="categorias.{{ $i }}.icono" placeholder="ícono" class="w-32 rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                    <span class="w-24 text-right text-xs text-muted">{{ $c['productos'] }} prod.</span>
                    <button wire:click="eliminarCategoria({{ $c['id'] }})" wire:confirm="¿Eliminar la categoría? Los productos quedarán sin categoría." class="text-muted hover:text-danger" title="Eliminar"><span class="material-symbols-outlined text-[20px]">delete</span></button>
                </div>
            @empty
                <p class="py-2 text-sm text-muted">No hay categorías. Agregá la primera abajo.</p>
            @endforelse
        </div>
        <div class="flex flex-wrap items-end gap-3 border-t border-gray-100 p-5">
            <div><label class="mb-1 block text-xs font-bold uppercase text-muted">Nueva categoría</label><input type="text" wire:model="nuevaCategoriaNombre" placeholder="Ej: Iluminación, Pinturas…" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" /></div>
            <div><label class="mb-1 block text-xs font-bold uppercase text-muted">Ícono</label><input type="text" wire:model="nuevaCategoriaIcono" placeholder="category" class="w-32 rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" /></div>
            <button wire:click="agregarCategoria" class="flex items-center gap-2 rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-dark"><span class="material-symbols-outlined text-[20px]">add</span> Agregar categoría</button>
        </div>
    </x-panel>
    @endpuede
    @endif

    {{-- ===== Zonas de cobranza ===== --}}
    @if ($sub === 'zonas')
    @puede('gestionar_zonas')
    <x-panel title="Zonas de cobranza">
        <x-slot:actions>
            <button wire:click="guardarZonas" class="flex items-center gap-1.5 rounded-lg bg-brand px-3 py-1.5 text-xs font-bold text-white hover:bg-brand-dark"><span class="material-symbols-outlined text-[16px]">save</span> Guardar</button>
        </x-slot:actions>
        <p class="px-5 pt-4 text-xs text-muted">Cada zona tiene un <b>cobrador asignado</b>. Al cargar una venta a crédito o dar de alta un cliente, se elige la zona y el sistema completa el cobrador. <span class="font-bold text-graphite">Reasignar el cobrador de una zona</span> (por ejemplo si renuncia) mueve automáticamente todas sus cuotas de cobranza abiertas al nuevo cobrador.</p>
        <div class="overflow-x-auto p-5">
            <table class="w-full text-left text-sm">
                <thead><tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-muted">
                    <th class="px-3 py-2.5 font-bold">Zona</th><th class="px-3 py-2.5 font-bold">Sucursal</th>
                    <th class="px-3 py-2.5 font-bold">Cobrador asignado</th>
                    <th class="px-3 py-2.5 text-right font-bold">Cuotas abiertas</th>
                    <th class="px-3 py-2.5 text-center font-bold">Activa</th><th class="px-3 py-2.5"></th>
                </tr></thead>
                <tbody>
                    @forelse ($zonas as $i => $z)
                        <tr class="border-t border-gray-100" wire:key="zona-{{ $z['id'] }}">
                            <td class="px-3 py-2.5"><input type="text" wire:model="zonas.{{ $i }}.nombre" class="w-52 rounded-lg border border-gray-200 px-2 py-1 text-sm font-bold outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" /></td>
                            <td class="px-3 py-2.5">
                                <select wire:model="zonas.{{ $i }}.local_id" class="rounded-lg border border-gray-200 px-2 py-1 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                                    <option value="">— sin sucursal —</option>
                                    @foreach ($sucursales as $s)
                                        <option value="{{ $s['id'] }}">{{ $s['nombre'] }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-3 py-2.5">
                                <select wire:model="zonas.{{ $i }}.cobrador_id" class="w-56 rounded-lg border border-gray-200 px-2 py-1 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                                    <option value="">— sin asignar —</option>
                                    @foreach ($cobradores as $uid => $label)
                                        <option value="{{ $uid }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-3 py-2.5 text-right">
                                @if ($z['cuotas_abiertas'] > 0)
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-700">{{ $z['cuotas_abiertas'] }}</span>
                                @else
                                    <span class="text-xs text-muted">0</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-center"><input type="checkbox" wire:model="zonas.{{ $i }}.activo" class="rounded border-gray-300 text-brand focus:ring-brand/30" /></td>
                            <td class="px-3 py-2.5 text-right">
                                <button wire:click="eliminarZona({{ $z['id'] }})" wire:confirm="¿Eliminar la zona? Las ventas y cuotas quedarán sin zona (no se borran)." class="text-muted hover:text-danger" title="Eliminar"><span class="material-symbols-outlined text-[20px]">delete</span></button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-muted">No hay zonas definidas. Agregá la primera abajo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap items-end gap-3 border-t border-gray-100 p-5">
            <div><label class="mb-1 block text-xs font-bold uppercase text-muted">Nueva zona</label><input type="text" wire:model="nuevaZonaNombre" wire:keydown.enter="agregarZona" placeholder="Ej: Distritos Chilecito" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" /></div>
            <div><label class="mb-1 block text-xs font-bold uppercase text-muted">Sucursal</label>
                <select wire:model="nuevaZonaLocal" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                    <option value="">— sin sucursal —</option>
                    @foreach ($sucursales as $s)
                        <option value="{{ $s['id'] }}">{{ $s['nombre'] }}</option>
                    @endforeach
                </select>
            </div>
            <div><label class="mb-1 block text-xs font-bold uppercase text-muted">Cobrador</label>
                <select wire:model="nuevaZonaCobrador" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                    <option value="">— sin asignar —</option>
                    @foreach ($cobradores as $uid => $label)
                        <option value="{{ $uid }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button wire:click="agregarZona" class="flex items-center gap-2 rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-dark"><span class="material-symbols-outlined text-[20px]">add</span> Agregar zona</button>
        </div>
    </x-panel>
    @endpuede
    @endif
</div>
