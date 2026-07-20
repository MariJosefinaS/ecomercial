<div class="lg:col-span-4">
    <x-panel title="Mejores Vendedores">
        <div class="space-y-5 p-5">
            @foreach ($sellers as $s)
                <div wire:key="seller-{{ $s['initials'] }}">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <x-avatar :initials="$s['initials']" :variant="$s['variant']" size="sm" />
                            <span class="text-sm font-bold text-ink">{{ $s['name'] }}</span>
                        </div>
                        <div class="text-right leading-tight">
                            <p class="tabular text-sm font-extrabold text-ink">{{ $s['total'] }}</p>
                            <p class="text-[11px] font-medium text-muted">{{ $s['units'] }} unidades</p>
                        </div>
                    </div>
                    <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full rounded-full {{ $s['bar'] }}" style="width: {{ $s['pct'] }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-panel>
</div>
