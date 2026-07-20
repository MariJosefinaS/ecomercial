@props([
    'size' => 'md',      // sm | md
    'onDark' => false,   // texto blanco sobre fondo oscuro
])

@php
    $svg = $size === 'sm' ? 'h-6 w-6' : 'h-8 w-8';
    $txt = $size === 'sm' ? 'text-lg' : 'text-xl';
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 leading-none']) }}>
    {{-- Isotipo "E" de bloques (recreación vectorial del manual de marca) --}}
    <svg viewBox="0 0 48 48" class="{{ $svg }} flex-shrink-0" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <rect x="5" y="5"  width="38" height="9" fill="#EC6A19" />
        <rect x="5" y="5"  width="11" height="38" fill="#EC6A19" />
        <rect x="5" y="34" width="38" height="9" fill="#EC6A19" />
        <rect x="5" y="19.5" width="29" height="9" fill="#EC6A19" />
        <rect x="16" y="19.5" width="8" height="9" fill="#FFFFFF" />
    </svg>
    <span class="{{ $txt }} font-extrabold tracking-tight {{ $onDark ? 'text-white' : 'text-ink' }}">.Comercial</span>
</span>
