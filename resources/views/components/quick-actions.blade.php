@php
    $actions = [
        ['label' => 'Crear Venta',      'icon' => 'add_shopping_cart', 'href' => route('ventas', ['nuevo' => 1])],
        ['label' => 'Añadir Stock',     'icon' => 'add_box',           'href' => route('stock', ['nuevo' => 1])],
        ['label' => 'Registrar Compra', 'icon' => 'shopping_bag',       'href' => route('compras', ['nuevo' => 1])],
        ['label' => 'Generar Reporte',  'icon' => 'summarize',          'href' => route('reportes')],
    ];
@endphp

<x-panel title="Acciones Rápidas" {{ $attributes }}>
    <div class="grid grid-cols-1 gap-2.5 p-5">
        @foreach ($actions as $a)
            <a href="{{ $a['href'] }}"
               class="flex items-center gap-2.5 rounded-lg border border-gray-200 px-3 py-2.5 text-sm font-semibold text-ink transition hover:border-brand hover:bg-brand-soft hover:text-brand">
                <span class="material-symbols-outlined text-[20px]">{{ $a['icon'] }}</span> {{ $a['label'] }}
            </a>
        @endforeach
    </div>
</x-panel>
