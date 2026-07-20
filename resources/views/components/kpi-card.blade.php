@props([
    'title',
    'value',
    'icon' => null,
    'variant' => 'white', // brand | blue | red | beige | white
    'subtitle' => null,
])

@php
    $variants = [
        'brand' => 'bg-brand text-white',
        'blue'  => 'bg-kpiBlue-bg text-kpiBlue-fg border border-gray-100',
        'red'   => 'bg-kpiRed-bg text-kpiRed-fg border border-gray-100',
        'beige' => 'bg-kpiBeige-bg text-kpiBeige-fg border border-gray-100',
        'white' => 'bg-white text-ink border border-gray-100',
    ];
    $box = $variants[$variant] ?? $variants['white'];
    $labelTone = $variant === 'brand' ? 'text-white/80' : '';
@endphp

<div class="rounded-xl {{ $box }} p-5 shadow-card">
    <div class="flex items-start justify-between">
        <span class="text-xs font-bold uppercase tracking-wide {{ $labelTone }}">{{ $title }}</span>
        @if ($icon)
            <span class="material-symbols-outlined {{ $variant === 'brand' ? 'ico-fill text-white/80' : '' }}">{{ $icon }}</span>
        @endif
    </div>

    <h2 class="tabular mt-3 text-4xl font-extrabold leading-none">{{ $value }}</h2>

    @if ($subtitle)
        <p class="mt-3 text-xs font-semibold uppercase tracking-wide {{ $variant === 'brand' ? 'text-white/80' : 'opacity-70' }}">{{ $subtitle }}</p>
    @endif

    {{-- slot opcional: sparkline, tendencia, etc. --}}
    {{ $slot }}
</div>
