@props([
    'variant' => 'gray', // brand | blue | gray
])

@php
    $variants = [
        'brand' => 'bg-brand-soft text-brand',
        'blue'  => 'bg-kpiBlue-bg text-kpiBlue-fg',
        'gray'  => 'bg-gray-100 text-graphite',
    ];
    $tone = $variants[$variant] ?? $variants['gray'];
@endphp

<span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $tone }}">{{ $slot }}</span>
