<div class="lg:col-span-5">
    <x-panel title="Aprobaciones Pendientes">
        @if (empty($approvals))
            <div class="p-8 text-center text-sm text-muted">
                <span class="material-symbols-outlined mb-1 block text-3xl text-success">task_alt</span>
                No hay aprobaciones pendientes.
            </div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach ($approvals as $a)
                    <div class="px-5 py-4" wire:key="appr-{{ $a['id'] }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg
                                    {{ $a['variant'] === 'brand' ? 'bg-brand-soft text-brand' : ($a['variant'] === 'blue' ? 'bg-kpiBlue-bg text-kpiBlue-fg' : 'bg-gray-100 text-graphite') }}">
                                    <span class="material-symbols-outlined text-[20px]">{{ $a['icon'] }}</span>
                                </span>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <x-type-badge :variant="$a['variant']">{{ $a['type'] }}</x-type-badge>
                                        <span class="text-xs font-semibold text-muted">{{ $a['id'] }}</span>
                                    </div>
                                    <a href="{{ $a['url'] }}" class="mt-1 block text-sm font-bold text-ink hover:text-brand hover:underline">{{ $a['title'] }}</a>
                                    <p class="text-xs text-graphite">Solicita <span class="font-semibold text-ink">{{ $a['who'] }}</span> · {{ $a['local'] }}</p>
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                <p class="tabular text-sm font-extrabold text-ink">{{ $a['amount'] }}</p>
                                <a href="{{ $a['url'] }}" class="flex items-center text-xs font-bold text-brand hover:underline">
                                    Ver detalle <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                                </a>
                            </div>
                        </div>
                        <div class="mt-3 flex gap-2">
                            <button wire:click="approve('{{ $a['id'] }}')"
                                    class="rounded-lg bg-success px-4 py-1.5 text-xs font-bold text-white transition hover:brightness-95">Aprobar</button>
                            <button wire:click="reject('{{ $a['id'] }}')"
                                    class="rounded-lg border border-gray-300 px-4 py-1.5 text-xs font-bold text-graphite transition hover:bg-gray-50">Rechazar</button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-panel>
</div>
