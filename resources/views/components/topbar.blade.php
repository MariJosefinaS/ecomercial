@php
    $u = auth()->user();
    $nombre = $u?->name ?? 'Usuario';
    $iniciales = collect(explode(' ', $nombre))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $rolLabel = \App\Support\Permisos::rolesNombres()[$u?->rol] ?? ucfirst(str_replace('_', ' ', (string) $u?->rol));
    $avatar = $u?->avatar ? asset('storage/' . $u->avatar) : null;
@endphp

<header class="sticky top-0 z-40 flex h-16 items-center gap-4 border-b border-gray-200 bg-white/90 px-4 backdrop-blur sm:px-6">
    {{-- Hamburguesa (solo mobile) --}}
    <button @click="sidebarOpen = true" class="flex h-10 w-10 items-center justify-center rounded-full text-graphite transition hover:bg-gray-50 lg:hidden">
        <span class="material-symbols-outlined">menu</span>
    </button>

    <div class="ml-auto flex items-center gap-3 sm:gap-4">
        {{-- Notificaciones (aprobaciones pendientes) --}}
        <livewire:shared.notifications />

        {{-- Usuario + menú (Ver perfil / Cerrar sesión) --}}
        <div class="relative border-l border-gray-200 pl-3 sm:pl-4" x-data="{ open: false }" @keydown.escape.window="open = false">
            <button type="button" @click="open = !open"
                    class="flex items-center gap-3 rounded-full py-1 pl-1 pr-1 transition hover:bg-gray-50 sm:pr-2"
                    :aria-expanded="open" aria-haspopup="menu">
                <div class="hidden text-right leading-tight sm:block">
                    <p class="text-sm font-bold text-ink">{{ $nombre }}</p>
                    <p class="text-[11px] font-medium uppercase tracking-wide text-muted">{{ $rolLabel }}</p>
                </div>
                @if ($avatar)
                    <img src="{{ $avatar }}" alt="{{ $nombre }}" class="h-10 w-10 rounded-full object-cover" />
                @else
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-graphite text-sm font-bold text-white">{{ $iniciales ?: 'U' }}</div>
                @endif
                <span class="material-symbols-outlined hidden text-[20px] text-muted transition-transform sm:block" :class="open && 'rotate-180'">expand_more</span>
            </button>

            {{-- Dropdown --}}
            <div x-show="open" x-cloak @click.outside="open = false"
                 x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                 class="absolute right-0 mt-2 w-60 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-2xl"
                 role="menu">
                {{-- Cabecera del menú --}}
                <div class="flex items-center gap-3 border-b border-gray-100 bg-gray-50 px-4 py-3">
                    @if ($avatar)
                        <img src="{{ $avatar }}" alt="{{ $nombre }}" class="h-10 w-10 rounded-full object-cover" />
                    @else
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-graphite text-sm font-bold text-white">{{ $iniciales ?: 'U' }}</div>
                    @endif
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold text-ink">{{ $nombre }}</p>
                        <p class="truncate text-[11px] font-medium uppercase tracking-wide text-muted">{{ $rolLabel }}</p>
                    </div>
                </div>

                <a href="{{ route('perfil') }}" wire:navigate role="menuitem"
                   class="flex items-center gap-3 px-4 py-3 text-sm font-semibold text-graphite transition hover:bg-gray-50">
                    <span class="material-symbols-outlined text-[20px] text-muted">account_circle</span> Ver perfil
                </a>

                <form method="POST" action="{{ route('logout') }}" class="border-t border-gray-100">
                    @csrf
                    <button type="submit" role="menuitem"
                            class="flex w-full items-center gap-3 px-4 py-3 text-sm font-bold text-danger transition hover:bg-red-50">
                        <span class="material-symbols-outlined text-[20px]">logout</span> Cerrar sesión
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
