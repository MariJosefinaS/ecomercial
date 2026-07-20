@props([
    'title' => null,   // opcional: si no se pasa, el panel no muestra header
    'actions' => null, // slot opcional para acciones/badges en el header
])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-gray-100 bg-white shadow-card']) }}>
    @if ($title)
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <h3 class="text-base font-extrabold uppercase tracking-wide text-ink">{{ $title }}</h3>
            @if ($actions)
                <div>{{ $actions }}</div>
            @endif
        </div>
    @endif

    {{ $slot }}
</div>
