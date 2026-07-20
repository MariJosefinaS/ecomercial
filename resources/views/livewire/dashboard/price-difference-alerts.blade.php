<div class="lg:col-span-8">
    <x-panel title="Alertas de Diferencia de Precio" class="overflow-hidden">
        <x-slot:actions>
            <span class="flex items-center gap-1.5 rounded-full bg-brand-soft px-3 py-1 text-xs font-bold text-brand">
                <span class="material-symbols-outlined text-[16px]">warning</span> {{ count($rows) }} alertas
            </span>
        </x-slot:actions>

        <div class="max-h-[360px] overflow-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wide text-muted">
                        <th class="px-5 py-3 font-bold">Producto</th>
                        <th class="px-5 py-3 text-right font-bold">Local A</th>
                        <th class="px-5 py-3 text-right font-bold">Local B</th>
                        <th class="px-5 py-3 text-right font-bold">Dif.</th>
                    </tr>
                </thead>
                <tbody class="tabular">
                    @foreach ($rows as $i => $r)
                        <tr class="border-t border-gray-100 {{ $i % 2 ? 'bg-gray-50/60' : '' }}">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 text-graphite">
                                        <span class="material-symbols-outlined text-[20px]">{{ $r['icon'] }}</span>
                                    </span>
                                    <span class="font-semibold text-ink">{{ $r['name'] }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-right {{ $r['higher'] === 'a' ? 'font-bold text-ink' : 'text-graphite' }}">{{ $r['a'] }}</td>
                            <td class="px-5 py-3 text-right {{ $r['higher'] === 'b' ? 'font-bold text-ink' : 'text-graphite' }}">{{ $r['b'] }}</td>
                            <td class="px-5 py-3 text-right">
                                <span class="inline-flex items-center gap-0.5 font-bold {{ $r['dir'] === 'up' ? 'text-brand' : 'text-danger' }}">
                                    <span class="material-symbols-outlined text-[18px]">{{ $r['dir'] === 'up' ? 'arrow_upward' : 'arrow_downward' }}</span>{{ $r['diff'] }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-panel>
</div>
