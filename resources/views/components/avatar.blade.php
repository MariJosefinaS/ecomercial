@props([
    'initials',
    'variant' => 'gray', // brand | blue | gray
    'size' => 'md',       // sm | md
])

@php
    $variants = [
        'brand' => 'bg-brand-soft text-brand',
        'blue'  => 'bg-kpiBlue-bg text-kpiBlue-fg',
        'gray'  => 'bg-gray-100 text-graphite',
    ];
    $sizes = [
        'sm' => 'h-8 w-8 text-xs',
        'md' => 'h-10 w-10 text-sm',
    ];
    $tone = $variants[$variant] ?? $variants['gray'];
    $dim  = $sizes[$size] ?? $sizes['md'];
@endphp

<div class="flex items-center justify-center rounded-full font-bold {{ $dim }} {{ $tone }}">{{ $initials }}</div>
