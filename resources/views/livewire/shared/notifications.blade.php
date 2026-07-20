<div class="relative" x-data="{ open: false }" @click.outside="open = false">
    <button @click="open = !open; if (open) { $wire.abrir() }"
            class="relative flex h-10 w-10 items-center justify-center rounded-full text-graphite transition hover:bg-gray-50">
        <span class="material-symbols-outlined text-[22px]">notifications</span>
        @if ($count > 0)
            <span class="absolute right-1.5 top-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-brand text-[10px] font-bold text-white">{{ $count }}</span>
        @endif
    </button>

    <div x-show="open" x-cloak x-transition.origin.top.right
         class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-card">
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
            <h4 class="text-sm font-extrabold uppercase tracking-wide text-ink">{{ $titulo }}</h4>
            @if (count($items) > 0)
                <span class="rounded-full bg-brand-soft px-2 py-0.5 text-[11px] font-bold text-brand">{{ count($items) }} nuevas</span>
            @endif
        </div>

        @php
            $badge = fn ($vv) => match ($vv) {
                'brand' => 'bg-brand-soft text-brand',
                'blue' => 'bg-kpiBlue-bg text-kpiBlue-fg',
                'green' => 'bg-green-100 text-green-700',
                'red' => 'bg-red-100 text-red-700',
                default => 'bg-gray-100 text-graphite',
            };
        @endphp

        <div class="max-h-96 overflow-y-auto">
            @forelse ($items as $n)
                @php $tag = $n['url'] ? 'a' : 'div'; @endphp
                <{{ $tag }} @if ($n['url']) href="{{ $n['url'] }}" @endif
                    class="flex items-start gap-3 border-b border-gray-50 px-4 py-3 transition {{ $n['url'] ? 'hover:bg-brand-soft/40' : '' }}">
                    <span class="mt-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg {{ $badge($n['vv']) }}">
                        <span class="material-symbols-outlined text-[18px]">{{ $n['icon'] }}</span>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[11px] font-bold uppercase tracking-wide text-muted">{{ $n['tipo'] }} · {{ $n['id'] }}</span>
                            <span class="tabular text-xs font-bold text-ink">{{ $n['monto'] }}</span>
                        </div>
                        <p class="truncate text-sm font-semibold text-ink">{{ $n['desc'] }}</p>
                        <p class="truncate text-xs text-graphite">{{ $n['sub'] }}</p>
                    </div>
                </{{ $tag }}>
            @empty
                <p class="px-4 py-8 text-center text-sm text-muted">{{ $vacio }}</p>
            @endforelse
        </div>

        <a href="{{ $verTodas ?? route('ventas') }}" class="block bg-gray-50 px-4 py-2.5 text-center text-xs font-bold text-brand transition hover:bg-gray-100">{{ $verTodasLabel ?? 'Ver todas' }}</a>
    </div>
</div>
