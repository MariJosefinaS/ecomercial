@props(['active' => 'lista'])

<div class="flex items-center gap-1 border-b border-gray-200">
    <a href="{{ route('proveedores') }}"
       class="-mb-px flex items-center gap-1.5 border-b-2 px-4 py-2.5 text-sm font-bold transition {{ $active === 'lista' ? 'border-brand text-brand' : 'border-transparent text-graphite hover:text-brand' }}">
        <span class="material-symbols-outlined text-[18px]">local_shipping</span> Proveedores
    </a>
    <a href="{{ route('proveedores.deuda') }}"
       class="-mb-px flex items-center gap-1.5 border-b-2 px-4 py-2.5 text-sm font-bold transition {{ $active === 'deuda' ? 'border-brand text-brand' : 'border-transparent text-graphite hover:text-brand' }}">
        <span class="material-symbols-outlined text-[18px]">request_quote</span> Deuda y Cheques
    </a>
</div>
