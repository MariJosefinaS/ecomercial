<x-layouts.app title="Panel — E.Comercial">

    @php $sub = request('sub', 'resumen'); @endphp

    {{-- ===== Resumen: KPIs + acciones rápidas ===== --}}
    @if ($sub === 'resumen')
        <section class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">

            <x-kpi-card variant="brand" title="Ventas de Hoy" value="$42.500" icon="trending_up">
                <svg viewBox="0 0 120 32" class="mt-3 h-8 w-full" preserveAspectRatio="none">
                    <polyline points="0,26 15,22 30,24 45,16 60,18 75,10 90,13 105,6 120,8"
                              fill="none" stroke="white" stroke-width="2" stroke-opacity="0.9" stroke-linecap="round" stroke-linejoin="round" />
                    <polyline points="0,32 0,26 15,22 30,24 45,16 60,18 75,10 90,13 105,6 120,8 120,32"
                              fill="white" fill-opacity="0.12" stroke="none" />
                </svg>
                <p class="mt-1 flex items-center gap-1 text-xs font-semibold text-white/90">
                    <span class="material-symbols-outlined text-[16px]">arrow_upward</span> +12,4% vs. semana pasada
                </p>
            </x-kpi-card>

            <x-kpi-card variant="blue"  title="Aprobaciones Pendientes" value="14" icon="fact_check" subtitle="Requiere Acción" />
            <x-kpi-card variant="red"   title="Stock Bajo" value="8" icon="production_quantity_limits" subtitle="Ítems Bajo el Mínimo" />
            <x-kpi-card variant="beige" title="Deuda a Proveedores" value="$12.300" icon="credit_card" subtitle="Vence en 5 Días" />
        </section>

        <x-quick-actions />

    {{-- ===== Alertas de diferencia de precio entre locales ===== --}}
    @elseif ($sub === 'alertas')
        <livewire:dashboard.price-difference-alerts />

    {{-- ===== Actividad reciente ===== --}}
    @elseif ($sub === 'actividad')
        <livewire:dashboard.recent-activity />

    {{-- ===== Aprobaciones pendientes ===== --}}
    @elseif ($sub === 'aprobaciones')
        <livewire:dashboard.pending-approvals />

    {{-- ===== Ranking de vendedores ===== --}}
    @elseif ($sub === 'ranking')
        <livewire:dashboard.top-sellers />
    @endif

</x-layouts.app>
