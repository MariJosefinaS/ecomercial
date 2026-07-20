<div class="space-y-6">

    {{-- Encabezado --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-ink">Usuarios</h1>
            <p class="text-sm text-muted">Empleados, roles y acceso al sistema</p>
        </div>
        @puede('gestionar_usuarios')
        <button wire:click="nuevoUsuario" class="flex items-center gap-2 rounded-lg bg-brand px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-dark">
            <span class="material-symbols-outlined text-[20px]">person_add</span> Nuevo usuario
        </button>
        @endpuede
    </div>

    {{-- Mensaje --}}
    @if ($mensaje)
        <div class="flex items-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-4 py-2.5 text-sm font-semibold text-sky-700">
            <span class="material-symbols-outlined text-[18px]">info</span> {{ $mensaje }}
        </div>
    @endif

    {{-- Stats --}}
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi-card variant="white" title="Usuarios" :value="$stats['total']" icon="group" subtitle="Total" />
        <x-kpi-card variant="blue"  title="Activos" :value="$stats['activos']" icon="how_to_reg" subtitle="Con acceso" />
        <x-kpi-card variant="beige" title="Administradores" :value="$stats['admins']" icon="admin_panel_settings" />
        <x-kpi-card variant="brand" title="Vendedores" :value="$stats['vendedores']" icon="badge" />
    </div>

    {{-- Tabla + filtros --}}
    <x-panel title="Listado de usuarios">
        <x-slot:actions>
            <span class="text-xs font-semibold text-muted">{{ count($filas) }} resultado(s)</span>
        </x-slot:actions>

        <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-5">
            <div class="relative min-w-[240px] flex-1">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[20px] text-muted">search</span>
                <input type="text" wire:model.live.debounce.300ms="buscar" placeholder="Buscar por nombre o email..."
                       class="w-full rounded-full border border-gray-200 bg-gray-50 py-2 pl-10 pr-4 text-sm font-medium outline-none transition focus:border-brand focus:bg-white focus:ring-2 focus:ring-brand/20" />
            </div>

            <select wire:model.live="rol" class="rounded-lg border border-gray-200 py-2 pl-3 pr-8 text-sm font-medium text-ink outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                <option value="todos">Todos los roles</option>
                @foreach ($roles as $clave => $nombre)
                    <option value="{{ $clave }}">{{ $nombre }}</option>
                @endforeach
            </select>

            <div class="flex overflow-hidden rounded-lg border border-gray-200 text-sm font-bold">
                @foreach (['todos' => 'Todos', 'activo' => 'Activos', 'inactivo' => 'Inactivos'] as $val => $lbl)
                    <button wire:click="$set('estado', '{{ $val }}')"
                            class="px-3 py-2 transition {{ $estado === $val ? 'bg-brand text-white' : 'text-graphite hover:bg-gray-50' }}">{{ $lbl }}</button>
                @endforeach
            </div>

            <button wire:click="limpiar" class="ml-auto flex items-center gap-1 text-xs font-bold text-muted hover:text-brand">
                <span class="material-symbols-outlined text-[16px]">close</span> Limpiar
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wide text-muted">
                        <th class="px-5 py-3 font-bold">Usuario</th>
                        <th class="px-5 py-3 font-bold">Rol</th>
                        <th class="px-5 py-3 font-bold">Local</th>
                        <th class="px-5 py-3 font-bold">Estado</th>
                        <th class="px-5 py-3 font-bold">Último acceso</th>
                        <th class="px-5 py-3 text-right font-bold">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $badgeClase = [
                            'brand' => 'bg-brand-soft text-brand',
                            'blue' => 'bg-sky-100 text-sky-700',
                            'green' => 'bg-green-100 text-green-700',
                            'gray' => 'bg-gray-100 text-graphite',
                            'red' => 'bg-red-100 text-red-700',
                            'purple' => 'bg-purple-100 text-purple-700',
                            'amber' => 'bg-amber-100 text-amber-700',
                        ];
                    @endphp
                    @forelse ($filas as $i => $u)
                        <tr class="border-t border-gray-100 {{ $i % 2 ? 'bg-gray-50/50' : '' }} hover:bg-brand-soft/40" wire:key="user-{{ $u['email'] }}">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <x-avatar :initials="$u['ini']" :variant="$u['vv']" size="md" />
                                    <div>
                                        <p class="font-bold text-ink">{{ $u['nom'] }}</p>
                                        <p class="text-xs text-muted">{{ $u['email'] }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <span class="inline-block rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wide {{ $badgeClase[$u['vv']] ?? $badgeClase['gray'] }}">{{ $roles[$u['rol']] ?? ucfirst(str_replace('_', ' ', $u['rol'])) }}</span>
                            </td>
                            <td class="px-5 py-3 text-graphite">{{ $u['local'] }}</td>
                            <td class="px-5 py-3">
                                @if ($u['activo'])
                                    <span class="inline-flex items-center gap-1.5 text-xs font-bold text-green-700"><span class="h-2 w-2 rounded-full bg-green-500"></span> Activo</span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 text-xs font-bold text-muted"><span class="h-2 w-2 rounded-full bg-gray-400"></span> Inactivo</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-graphite">{{ $u['acceso'] }}</td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    @puede('gestionar_usuarios')
                                    <button wire:click="editarUsuario({{ $u['id'] }})" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-graphite transition hover:bg-gray-100 hover:text-brand" title="Editar">
                                        <span class="material-symbols-outlined text-[20px]">edit</span>
                                    </button>
                                    @endpuede
                                    @puede('reset_password')
                                    <button wire:click="resetearPassword({{ $u['id'] }})" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-graphite transition hover:bg-gray-100 hover:text-brand" title="Resetear contraseña">
                                        <span class="material-symbols-outlined text-[20px]">lock_reset</span>
                                    </button>
                                    @endpuede
                                    @puede('gestionar_usuarios')
                                    <button wire:click="toggleActivo({{ $u['id'] }})" class="inline-flex h-8 w-8 items-center justify-center rounded-lg transition hover:bg-gray-100 {{ $u['activo'] ? 'text-graphite hover:text-danger' : 'text-graphite hover:text-success' }}" title="{{ $u['activo'] ? 'Bloquear / dar de baja' : 'Reactivar' }}">
                                        <span class="material-symbols-outlined text-[20px]">{{ $u['activo'] ? 'toggle_on' : 'toggle_off' }}</span>
                                    </button>
                                    <button wire:click="eliminarUsuario({{ $u['id'] }})" wire:confirm="¿Eliminar a «{{ $u['nom'] }}»? Si tiene historial, se dará de baja en vez de borrarlo." class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-graphite transition hover:bg-gray-100 hover:text-danger" title="Eliminar usuario">
                                        <span class="material-symbols-outlined text-[20px]">delete</span>
                                    </button>
                                    @endpuede
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-sm text-muted">
                                <span class="material-symbols-outlined mb-1 block text-3xl">person_off</span>
                                No hay usuarios con esos filtros.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-panel>

    {{-- ===== Modal alta / edición ===== --}}
    @if ($modal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" wire:click="cerrarModal"></div>
            <div class="relative z-10 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 class="text-base font-extrabold text-ink">{{ $editando ? 'Editar usuario' : 'Nuevo usuario' }}</h3>
                    <button wire:click="cerrarModal" class="text-muted hover:text-danger"><span class="material-symbols-outlined">close</span></button>
                </div>

                <form wire:submit="guardarUsuario" class="space-y-4 p-5">
                    {{-- Avatar --}}
                    <div class="flex items-center gap-4">
                        <div class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-full bg-brand-soft text-brand">
                            @if ($fAvatar)
                                <img src="{{ $fAvatar->temporaryUrl() }}" class="h-full w-full object-cover" alt="avatar" />
                            @else
                                <span class="material-symbols-outlined text-[28px]">person</span>
                            @endif
                        </div>
                        <label class="cursor-pointer rounded-lg border border-gray-200 px-3 py-2 text-sm font-bold text-graphite transition hover:border-brand hover:text-brand">
                            <span class="material-symbols-outlined align-middle text-[18px]">photo_camera</span> Cargar avatar
                            <input type="file" wire:model="fAvatar" accept="image/*" class="hidden" />
                        </label>
                        <span wire:loading wire:target="fAvatar" class="text-xs text-muted">Subiendo…</span>
                    </div>
                    @error('fAvatar') <p class="text-xs font-semibold text-danger">{{ $message }}</p> @enderror

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Nombre</label>
                            <input type="text" wire:model="fNombre" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                            @error('fNombre') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Teléfono</label>
                            <input type="text" wire:model="fTelefono" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Email</label>
                            <input type="email" wire:model="fEmail" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                            @error('fEmail') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">{{ $editando ? 'Nueva contraseña (opcional)' : 'Contraseña' }}</label>
                            <input type="password" wire:model="fPassword" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                            @error('fPassword') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Rol</label>
                            <select wire:model="fRol" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                                @foreach ($roles as $clave => $nombre)
                                    <option value="{{ $clave }}">{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Sucursal</label>
                            <select wire:model="fLocal" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                                <option value="Todos">Todas (sin sucursal fija)</option>
                                @foreach ($locales as $l)
                                    <option value="{{ $l->nombre }}">{{ $l->nombre }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-[11px] text-muted">El encargado de depósito recibe y traspasa <b>solo en su sucursal</b> (queda bloqueada). El vendedor entrega en la suya.</p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <button type="button" wire:click="cerrarModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold text-graphite hover:bg-gray-50">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-dark">{{ $editando ? 'Guardar cambios' : 'Crear usuario' }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
