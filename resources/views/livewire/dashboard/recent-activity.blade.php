<div class="lg:col-span-4 flex flex-col rounded-xl border border-gray-100 bg-white shadow-card">
    <div class="border-b border-gray-100 px-5 py-4">
        <h3 class="text-base font-extrabold uppercase tracking-wide text-ink">Actividad Reciente</h3>
    </div>

    <div class="flex-1 space-y-4 p-5">
        @foreach ($events as $e)
            <div class="flex gap-3">
                <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full
                    {{ $e['tone'] === 'brand' ? 'bg-brand-soft text-brand' : ($e['tone'] === 'blue' ? 'bg-kpiBlue-bg text-kpiBlue-fg' : ($e['tone'] === 'red' ? 'bg-kpiRed-bg text-kpiRed-fg' : 'bg-gray-100 text-graphite')) }}">
                    <span class="material-symbols-outlined text-[18px]">{{ $e['icon'] }}</span>
                </span>
                <div class="text-sm">
                    <p class="font-semibold text-ink">{{ $e['title'] }}</p>
                    <p class="text-graphite">{!! $e['detail'] !!}</p>
                    <p class="mt-0.5 text-[11px] font-medium uppercase tracking-wide text-muted">{{ $e['ago'] }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <a href="#" class="m-5 mt-0 flex items-center justify-center gap-1.5 rounded-lg border border-gray-200 py-2.5 text-sm font-bold text-graphite transition hover:border-brand hover:bg-brand-soft hover:text-brand">
        Ver Registro Completo <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
    </a>
</div>
