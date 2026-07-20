@props(['src' => null, 'icon' => 'inventory_2', 'iconClass' => 'text-[22px]'])

<div {{ $attributes->merge(['class' => 'flex flex-shrink-0 items-center justify-center overflow-hidden bg-gray-50']) }}>
    @if ($src)
        <img src="{{ $src }}" loading="lazy" class="h-full w-full object-cover" alt="">
    @else
        <span class="material-symbols-outlined {{ $iconClass }} text-gray-300">{{ $icon }}</span>
    @endif
</div>
