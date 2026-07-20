@php
    $u = auth()->user();
    $nombre = $u?->name ?? 'Usuario';
    $iniciales = collect(explode(' ', $nombre))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $rolLabel = match ($u?->rol) {
        'super_admin' => 'Super Admin',
        'admin_local' => 'Admin de Local',
        'vendedor' => 'Vendedor',
        'empleado' => 'Empleado',
        default => '—',
    };
@endphp

<header class="sticky top-0 z-40 flex h-16 items-center gap-4 border-b border-gray-200 bg-white/90 px-4 backdrop-blur sm:px-6">
    {{-- Hamburguesa (solo mobile) --}}
    <button @click="sidebarOpen = true" class="flex h-10 w-10 items-center justify-center rounded-full text-graphite transition hover:bg-gray-50 lg:hidden">
        <span class="material-symbols-outlined">menu</span>
    </button>

    <div class="ml-auto flex items-center gap-3 sm:gap-4">
        {{-- Notificaciones (aprobaciones pendientes) --}}
        <livewire:shared.notifications />

        {{-- Usuario --}}
        <div class="flex items-center gap-3 border-l border-gray-200 pl-3 sm:pl-4">
            <div class="hidden text-right leading-tight sm:block">
                <p class="text-sm font-bold text-ink">{{ $nombre }}</p>
                <p class="text-[11px] font-medium uppercase tracking-wide text-muted">{{ $rolLabel }}</p>
            </div>
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-graphite text-sm font-bold text-white">{{ $iniciales ?: 'U' }}</div>
        </div>
    </div>
</header>
