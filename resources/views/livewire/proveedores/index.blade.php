<div class="space-y-6">

    @if ($mensaje)
        <div class="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm font-semibold text-green-700">
            <span class="material-symbols-outlined text-[18px]">check_circle</span> {{ $mensaje }}
        </div>
    @endif

    @if ($proveedor)
        {{-- ====================== FICHA DEL PROVEEDOR ====================== --}}
        @php
            $pedidoBadge = ['pendiente' => 'bg-amber-100 text-amber-700', 'aprobado' => 'bg-sky-100 text-sky-700', 'en_camino' => 'bg-indigo-100 text-indigo-700', 'recibido' => 'bg-green-100 text-green-700'];
            $devBadge = ['reingresado' => 'bg-green-100 text-green-700', 'enviado_a_fabrica' => 'bg-amber-100 text-amber-700', 'en_reparacion' => 'bg-sky-100 text-sky-700', 'defectuoso' => 'bg-red-100 text-red-700'];
            $devLabel = ['reingresado' => 'Reingresado a stock', 'enviado_a_fabrica' => 'Enviado a fábrica', 'en_reparacion' => 'En reparación', 'defectuoso' => 'Defectuoso (baja)'];
        @endphp

        <button wire:click="volver" class="flex items-center gap-1 text-sm font-bold text-graphite hover:text-brand">
            <span class="material-symbols-outlined text-[20px]">arrow_back</span> Volver a proveedores
        </button>

        <div class="flex flex-wrap items-start justify-between gap-4 rounded-xl border border-gray-100 bg-white p-5 shadow-card">
            <div class="flex items-center gap-4">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-soft text-brand"><span class="material-symbols-outlined text-[28px]">local_shipping</span></span>
                <div>
                    <h1 class="text-xl font-extrabold text-ink">{{ $proveedor['nombre'] }}</h1>
                    <p class="text-xs text-muted">{{ $proveedor['cuit'] }} · {{ $proveedor['tel'] }} · {{ $proveedor['dir'] }}</p>
                    <span class="mt-1 inline-block rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-bold text-graphite">{{ $proveedor['rubro'] }} · demora {{ $proveedor['dias'] }} días</span>
                </div>
            </div>
            <div class="text-right"><p class="text-[11px] font-bold uppercase text-muted">Deuda</p><p class="tabular text-lg font-extrabold {{ $proveedor['deuda'] > 0 ? 'text-danger' : 'text-ink' }}">${{ number_format($proveedor['deuda'], 0, ',', '.') }}</p></div>
        </div>

        <x-panel>
            <div class="flex flex-wrap items-center gap-1 border-b border-gray-100 px-3">
                @php
                    $tabs = ['cuenta' => 'Cuenta', 'pedidos' => 'Pedidos', 'compras' => 'Compras', 'pagos' => 'Pagos', 'devoluciones' => 'Devoluciones'];
                    $puedeConceptos = auth()->user() && \App\Support\Permisos::puede(auth()->user()->rol, 'gestionar_proveedores');
                @endphp
                @foreach ($tabs as $t => $lbl)
                    <button wire:click="setTab('{{ $t }}')" class="-mb-px whitespace-nowrap border-b-2 px-4 py-3 text-sm font-bold transition {{ $tab === $t ? 'border-brand text-brand' : 'border-transparent text-graphite hover:text-brand' }}">{{ $lbl }}</button>
                @endforeach
                @if ($puedeConceptos)
                    <button wire:click="setTab('conceptos')" class="-mb-px ml-auto flex items-center gap-1 whitespace-nowrap rounded-t-lg border-b-2 px-4 py-3 text-sm font-bold transition {{ $tab === 'conceptos' ? 'border-brand bg-brand-soft/40 text-brand' : 'border-transparent text-graphite hover:text-brand' }}">
                        <span class="material-symbols-outlined text-[18px]">percent</span> Conceptos a cobrar
                    </button>
                @endif
            </div>

            @if ($tab === 'cuenta')
                <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="text-[11px] uppercase tracking-wide text-muted"><th class="px-5 py-3 font-bold">Fecha</th><th class="px-5 py-3 font-bold">Concepto</th><th class="px-5 py-3 text-right font-bold">Compra (debe)</th><th class="px-5 py-3 text-right font-bold">Pago (haber)</th></tr></thead>
                    <tbody class="tabular">
                        @foreach ($proveedor['movimientos'] as $m)
                            <tr class="border-t border-gray-100">
                                <td class="px-5 py-3 text-graphite">{{ $m['fecha'] }}</td>
                                <td class="px-5 py-3 font-semibold text-ink">{{ $m['concepto'] }}</td>
                                <td class="px-5 py-3 text-right {{ $m['tipo'] === 'haber' ? 'font-bold text-danger' : 'text-gray-300' }}">{{ $m['tipo'] === 'haber' ? '$' . number_format($m['monto'], 2, ',', '.') : '—' }}</td>
                                <td class="px-5 py-3 text-right {{ $m['tipo'] === 'debe' ? 'font-bold text-success' : 'text-gray-300' }}">{{ $m['tipo'] === 'debe' ? '$' . number_format($m['monto'], 2, ',', '.') : '—' }}</td>
                            </tr>
                        @endforeach
                        <tr class="border-t-2 border-gray-200 bg-gray-50"><td colspan="3" class="px-5 py-3 text-right font-bold uppercase text-muted">Saldo (deuda)</td><td class="px-5 py-3 text-right text-base font-extrabold text-danger">${{ number_format($proveedor['deuda'], 2, ',', '.') }}</td></tr>
                    </tbody>
                </table>
                </div>

            @elseif ($tab === 'pedidos')
                <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="text-[11px] uppercase tracking-wide text-muted"><th class="px-5 py-3 font-bold">Pedido</th><th class="px-5 py-3 font-bold">Fecha pedido</th><th class="px-5 py-3 font-bold">Estado</th><th class="px-5 py-3 font-bold">Llegada estimada</th><th class="px-5 py-3 font-bold">Llegada real</th></tr></thead>
                    <tbody class="tabular">
                        @foreach ($proveedor['pedidos'] as $pe)
                            <tr class="border-t border-gray-100">
                                <td class="px-5 py-3 font-bold text-ink">{{ $pe['oc'] }}</td>
                                <td class="px-5 py-3 text-graphite">{{ $pe['fecha'] }}</td>
                                <td class="px-5 py-3"><span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase {{ $pedidoBadge[$pe['estado']] }}">{{ str_replace('_', ' ', $pe['estado']) }}</span></td>
                                <td class="px-5 py-3 text-graphite">{{ $pe['estimada'] }}</td>
                                <td class="px-5 py-3 {{ $pe['llegada'] ? 'font-bold text-ink' : 'text-muted' }}">{{ $pe['llegada'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>

            @elseif ($tab === 'compras')
                <div class="space-y-4 p-5">
                    @foreach ($proveedor['compras'] as $c)
                        <div class="overflow-hidden rounded-xl border border-gray-100">
                            <div class="flex flex-wrap items-center justify-between gap-2 bg-gray-50 px-4 py-2.5">
                                <span class="text-sm font-bold text-ink">
                                    {{ $c['tiene_factura'] ? 'Factura' : 'Remito' }} {{ $c['fac'] }} <span class="font-medium text-muted">· {{ $c['fecha'] }}</span>
                                    @if (! $c['tiene_factura'])<span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700" title="Recibido sin factura — no impacta la cuenta del proveedor hasta cargar la factura">P · sin factura</span>@endif
                                </span>
                                <div class="flex items-center gap-2">
                                    <span class="tabular text-sm font-extrabold text-ink">${{ number_format($c['monto'], 2, ',', '.') }}</span>
                                    @if (! $c['tiene_factura'])
                                        @puede('gestionar_proveedores')
                                            <button wire:click="pedirCargarFactura({{ $c['id'] }})" class="inline-flex items-center gap-1 rounded-lg border border-brand/30 bg-brand-soft/50 px-2.5 py-1 text-[11px] font-bold text-brand hover:bg-brand-soft"><span class="material-symbols-outlined text-[14px]">receipt_long</span> Cargar factura</button>
                                        @endpuede
                                    @endif
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead><tr class="text-[11px] uppercase tracking-wide text-muted"><th class="px-4 py-2 font-bold">Artículo</th><th class="px-4 py-2 text-center font-bold">Cant.</th><th class="px-4 py-2 text-right font-bold">Costo unit.</th><th class="px-4 py-2 text-right font-bold">Subtotal</th></tr></thead>
                                <tbody class="tabular">
                                    @foreach ($c['items'] as $it)
                                        <tr class="border-t border-gray-100">
                                            <td class="px-4 py-2 font-semibold text-ink">{{ $it['prod'] }}</td>
                                            <td class="px-4 py-2 text-center text-graphite">{{ $it['cant'] }}</td>
                                            <td class="px-4 py-2 text-right text-graphite">${{ number_format($it['costo'], 2, ',', '.') }}</td>
                                            <td class="px-4 py-2 text-right font-bold text-ink">${{ number_format($it['cant'] * $it['costo'], 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            </div>
                        </div>
                    @endforeach
                </div>

            @elseif ($tab === 'pagos')
                {{-- Obligaciones (facturas) a pagar --}}
                @php $obPend = collect($proveedor['obligaciones'])->where('saldo', '>', 0); @endphp
                <div class="border-b border-gray-100 p-5">
                    <p class="mb-2 text-[11px] font-bold uppercase tracking-wide text-muted">Facturas a pagar</p>
                    @if ($obPend->isEmpty())
                        <p class="text-sm text-muted">No hay facturas pendientes de pago.</p>
                    @else
                        <div class="divide-y divide-gray-100 rounded-xl border border-gray-100">
                            @foreach ($obPend as $ob)
                                <div class="flex flex-wrap items-center justify-between gap-2 px-3 py-2.5">
                                    <div>
                                        <p class="text-sm font-bold text-ink">Factura {{ $ob['factura'] }} <span class="font-medium text-muted">· vence {{ $ob['vence'] }}</span></p>
                                        <p class="text-[11px] text-muted">Total ${{ number_format($ob['monto'], 2, ',', '.') }} · Pagado ${{ number_format($ob['pagado'], 2, ',', '.') }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="tabular text-sm font-extrabold text-red-600">Saldo ${{ number_format($ob['saldo'], 2, ',', '.') }}</span>
                                        @puede('gestionar_proveedores')
                                            <button wire:click="pedirPagoProveedor({{ $ob['id'] }})" class="inline-flex items-center gap-1 rounded-lg bg-brand px-3 py-1.5 text-xs font-bold text-white hover:bg-brand-dark"><span class="material-symbols-outlined text-[16px]">payments</span> Registrar pago</button>
                                        @endpuede
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <p class="px-5 pt-4 text-[11px] font-bold uppercase tracking-wide text-muted">Pagos realizados</p>
                <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="text-[11px] uppercase tracking-wide text-muted"><th class="px-5 py-3 font-bold">Fecha</th><th class="px-5 py-3 font-bold">Medio</th><th class="px-5 py-3 font-bold">Comprobante</th><th class="px-5 py-3 text-right font-bold">Monto</th></tr></thead>
                    <tbody class="tabular">
                        @foreach ($proveedor['pagos'] as $pg)
                            <tr class="border-t border-gray-100">
                                <td class="px-5 py-3 text-graphite">{{ $pg['fecha'] }}</td>
                                <td class="px-5 py-3"><span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-bold text-graphite">{{ $pg['medio'] }}</span></td>
                                <td class="px-5 py-3 text-graphite">{{ $pg['comp'] }}</td>
                                <td class="px-5 py-3 text-right font-bold text-success">${{ number_format($pg['monto'], 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>

            @elseif ($tab === 'conceptos')
                @puede('gestionar_proveedores')
                    <div class="p-5">
                        <p class="mb-3 text-sm text-muted">Conceptos (y % por defecto) que este proveedor aplica. Los de ámbito <b>costo</b> recargan el costo; los de <b>venta</b> (ej. Remarcar) el precio de venta. Es el default que se carga al dar de alta un producto de este proveedor; después se puede ajustar/quitar por producto.</p>
                        <div class="overflow-x-auto rounded-xl border border-gray-100">
                            <table class="w-full text-left text-sm">
                                <thead><tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-muted"><th class="px-4 py-2.5 font-bold">Aplica</th><th class="px-4 py-2.5 font-bold">Concepto</th><th class="px-4 py-2.5 font-bold">Ámbito</th><th class="px-4 py-2.5 text-right font-bold">% por defecto</th></tr></thead>
                                <tbody>
                                    @forelse ($conceptosProv as $i => $c)
                                        <tr class="border-t border-gray-100" wire:key="provconcepto-{{ $c['id'] }}">
                                            <td class="px-4 py-2.5">
                                                <input type="checkbox" wire:model.live="conceptosProv.{{ $i }}.aplica" class="rounded border-gray-300 text-brand focus:ring-brand/30" />
                                            </td>
                                            <td class="px-4 py-2.5 font-semibold {{ ($c['aplica'] ?? false) ? 'text-ink' : 'text-muted line-through' }}">{{ $c['nombre'] }}</td>
                                            <td class="px-4 py-2.5">
                                                @if (($c['ambito'] ?? 'costo') === 'venta')
                                                    <span class="rounded-full bg-brand-soft px-2 py-0.5 text-[11px] font-bold text-brand">Venta</span>
                                                @else
                                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-bold text-graphite">Costo</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2.5">
                                                <div class="flex items-center justify-end gap-1">
                                                    <input type="number" step="0.01" min="0" wire:model="conceptosProv.{{ $i }}.porcentaje"
                                                           class="w-24 rounded-lg border border-gray-200 px-2 py-1 text-right text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 {{ ($c['aplica'] ?? false) ? '' : 'opacity-40' }}" />
                                                    <span class="text-muted">%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-muted">No hay conceptos definidos. Crealos en <b>Configuración → Conceptos de precio</b>.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4 flex justify-end">
                            <button wire:click="guardarConceptos" class="rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-dark">Guardar conceptos</button>
                        </div>
                    </div>
                @endpuede

            @else
                <div class="divide-y divide-gray-100">
                    @forelse ($proveedor['devoluciones'] as $d)
                        <div class="flex items-center justify-between px-5 py-3.5">
                            <div>
                                <p class="text-sm font-bold text-ink">{{ $d['prod'] }} <span class="font-medium text-muted">· x{{ $d['cant'] }}</span></p>
                                <p class="text-xs text-graphite">Motivo: <span class="font-semibold text-red-600">{{ $d['motivo'] }}</span></p>
                            </div>
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase {{ $devBadge[$d['estado']] }}">
                                <span class="material-symbols-outlined text-[14px]">build</span> {{ $devLabel[$d['estado']] }}
                            </span>
                        </div>
                    @empty
                        <p class="px-5 py-8 text-center text-sm text-muted">Sin devoluciones registradas.</p>
                    @endforelse
                </div>
            @endif
        </x-panel>

    @else
        {{-- ====================== LISTA ====================== --}}
        <div class="flex items-center justify-between">
            <div><h1 class="text-2xl font-extrabold text-ink">Proveedores</h1><p class="text-sm text-muted">Cuentas, pedidos, compras y pagos a proveedores</p></div>
            @puede('gestionar_proveedores')
                <button wire:click="nuevoProveedor" class="flex items-center gap-2 rounded-lg bg-brand px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-dark">
                    <span class="material-symbols-outlined text-[20px]">add</span> Nuevo proveedor
                </button>
            @endpuede
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <x-kpi-card variant="white" title="Proveedores" :value="$stats['total']" icon="local_shipping" subtitle="Registrados" />
            <x-kpi-card variant="blue"  title="Con Deuda" :value="$stats['con_deuda']" icon="account_balance_wallet" subtitle="Cuentas abiertas" />
            <x-kpi-card variant="brand" title="Deuda Total" :value="'$' . number_format($stats['deuda_total'], 0, ',', '.')" icon="payments" />
        </div>

        <x-panel title="Listado de proveedores">
            <x-slot:actions>
                <a href="{{ route('tesoreria') }}" class="flex items-center gap-1 text-xs font-bold text-brand hover:underline"><span class="material-symbols-outlined text-[16px]">account_balance</span> Ver deuda y cheques en Tesorería</a>
            </x-slot:actions>
            <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-5">
                <div class="relative min-w-[240px] flex-1">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[20px] text-muted">search</span>
                    <input type="text" wire:model.live.debounce.300ms="buscar" placeholder="Buscar por nombre o CUIT..." class="w-full rounded-full border border-gray-200 bg-gray-50 py-2 pl-10 pr-4 text-sm font-medium outline-none transition focus:border-brand focus:bg-white focus:ring-2 focus:ring-brand/20" />
                </div>
                <select wire:model.live="rubro" class="rounded-lg border border-gray-200 py-2 pl-3 pr-8 text-sm font-medium text-ink outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                    <option value="todos">Todos los rubros</option>
                    @foreach ($rubros as $r)<option value="{{ $r }}">{{ $r }}</option>@endforeach
                </select>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="text-[11px] uppercase tracking-wide text-muted"><th class="px-5 py-3 font-bold">Proveedor</th><th class="px-5 py-3 font-bold">Rubro</th><th class="px-5 py-3 font-bold">Teléfono</th><th class="px-5 py-3 text-center font-bold">Demora</th><th class="px-5 py-3 text-right font-bold">Deuda</th><th class="px-5 py-3 text-right font-bold">Acciones</th></tr></thead>
                    <tbody class="tabular">
                        @forelse ($filas as $i => $p)
                            <tr class="border-t border-gray-100 {{ $i % 2 ? 'bg-gray-50/50' : '' }} hover:bg-brand-soft/40" wire:key="prov-{{ $p['id'] }}">
                                <td class="px-5 py-3">@puede('ver_ficha_proveedor')<button wire:click="abrir({{ $p['id'] }})" class="text-left font-bold text-ink hover:text-brand hover:underline">{{ $p['nombre'] }}</button>@else<span class="font-bold text-ink">{{ $p['nombre'] }}</span>@endpuede<p class="text-xs text-muted">{{ $p['cuit'] }}</p></td>
                                <td class="px-5 py-3"><span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-bold text-graphite">{{ $p['rubro'] }}</span></td>
                                <td class="px-5 py-3 text-graphite">{{ $p['tel'] }}</td>
                                <td class="px-5 py-3 text-center text-graphite">{{ $p['dias'] }} días</td>
                                <td class="px-5 py-3 text-right font-bold {{ $p['deuda'] > 0 ? 'text-danger' : 'text-graphite' }}">${{ number_format($p['deuda'], 2, ',', '.') }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        @puede('ver_ficha_proveedor')<button wire:click="abrir({{ $p['id'] }})" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-graphite transition hover:bg-gray-100 hover:text-brand" title="Ver ficha"><span class="material-symbols-outlined text-[20px]">visibility</span></button>@endpuede
                                        @puede('gestionar_proveedores')
                                            <button wire:click="editarProveedor({{ $p['id'] }})" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-graphite transition hover:bg-gray-100 hover:text-brand" title="Editar"><span class="material-symbols-outlined text-[20px]">edit</span></button>
                                        @endpuede
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-10 text-center text-sm text-muted"><span class="material-symbols-outlined mb-1 block text-3xl">local_shipping</span>No hay proveedores con esos filtros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-panel>
    @endif

    {{-- ===== Modal alta / edición de proveedor ===== --}}
    @if ($modal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" wire:click="$set('modal', false)"></div>
            <div class="relative z-10 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 class="text-base font-extrabold text-ink">{{ $editId ? 'Editar proveedor' : 'Nuevo proveedor' }}</h3>
                    <button wire:click="$set('modal', false)" class="text-muted hover:text-danger"><span class="material-symbols-outlined">close</span></button>
                </div>

                <form wire:submit="guardarProveedor" class="p-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Nombre / Razón social</label>
                            <input type="text" wire:model="fNombre" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                            @error('fNombre') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Rubro</label>
                            <input type="text" wire:model="fRubro" placeholder="Construcción, Herramientas…" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">CUIT</label>
                            <input type="text" wire:model="fCuit" placeholder="30-00000000-0" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Teléfono</label>
                            <input type="text" wire:model="fTel" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Email</label>
                            <input type="email" wire:model="fEmail" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                            @error('fEmail') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Dirección</label>
                            <input type="text" wire:model="fDir" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold uppercase text-muted">Demora de entrega (días)</label>
                            <input type="number" min="0" wire:model="fDias" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                            @error('fDias') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Costeo del proveedor (afecta cómo se calcula el costo/precio de venta) --}}
                    <div class="mt-4 rounded-xl border border-gray-100 bg-gray-50 p-4">
                        <p class="mb-3 text-xs font-bold uppercase tracking-wide text-muted">Costeo</p>
                        <div>
                            <label class="mb-1 flex items-center gap-2 text-sm font-semibold text-graphite">
                                <input type="checkbox" wire:model.live="fCosteaIva" class="rounded border-gray-300 text-brand focus:ring-brand/30" />
                                Costear con IVA
                            </label>
                            <p class="text-[11px] text-muted">RI con crédito fiscal: dejar destildado (IVA no es costo).</p>
                            @if ($fCosteaIva)
                                <div class="mt-2 flex items-center gap-2">
                                    <input type="number" step="0.01" min="0" wire:model="fIvaPct" class="w-24 rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                                    <span class="text-sm text-muted">% IVA</span>
                                </div>
                                @error('fIvaPct') <p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p> @enderror
                            @endif
                        </div>
                        <p class="mt-3 text-[11px] text-muted">El <b>remarque/ganancia</b> y los demás recargos son <b>conceptos</b> (ámbito costo o venta) que se ajustan en la solapa <b>Conceptos a cobrar</b> de la ficha del proveedor.</p>
                    </div>

                    @if (! $editId)
                        <p class="mt-3 rounded-lg bg-brand-soft/60 px-3 py-2 text-[11px] text-graphite">El proveedor arranca con los conceptos de precio activos por defecto. Ajustalos luego en su ficha → <b>Conceptos a cobrar</b>.</p>
                    @endif

                    <div class="mt-5 flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <button type="button" wire:click="$set('modal', false)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold text-graphite hover:bg-gray-50">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-dark">{{ $editId ? 'Guardar cambios' : 'Crear proveedor' }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ===== Modal: Cargar factura (remito→factura) ===== --}}
    @if ($facturaCompraId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4"><h3 class="flex items-center gap-2 text-base font-extrabold text-ink"><span class="material-symbols-outlined text-brand">receipt_long</span> Cargar factura</h3><button wire:click="cerrarFactura" class="text-muted hover:text-ink"><span class="material-symbols-outlined">close</span></button></div>
                <div class="space-y-3 p-5">
                    <p class="text-sm text-muted">Al cargar la factura del proveedor se <b>genera la deuda</b> en su cuenta corriente (el remito ya sumó stock; recién ahora impacta la cuenta).</p>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-graphite">Número de factura</label>
                        <input type="text" wire:model="facNumero" placeholder="Ej: A-0001-00012345" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
                        @error('facNumero') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-graphite">Vencimiento</label>
                        <input type="date" wire:model="facVencimiento" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
                        @error('facVencimiento') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-4">
                    <button wire:click="cerrarFactura" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-bold text-graphite hover:bg-gray-100">Cancelar</button>
                    <button wire:click="cargarFactura" class="rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-dark">Cargar factura</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== Modal: Registrar pago a proveedor ===== --}}
    @if ($pagoObligacionId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4"><h3 class="flex items-center gap-2 text-base font-extrabold text-ink"><span class="material-symbols-outlined text-brand">payments</span> Registrar pago a proveedor</h3><button wire:click="cerrarPagoProveedor" class="text-muted hover:text-ink"><span class="material-symbols-outlined">close</span></button></div>
                <div class="space-y-3 p-5">
                    <p class="text-sm text-muted">Se registra un <b>egreso en caja</b> y baja el saldo de la factura.</p>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-graphite">Importe</label>
                        <div class="relative"><span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted">$</span>
                            <input type="number" step="0.01" min="0" wire:model="pagoMonto" class="w-full rounded-lg border border-gray-200 py-2 pl-7 pr-3 text-sm outline-none focus:border-brand" /></div>
                        @error('pagoMonto') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-graphite">Medio</label>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach (['transferencia' => 'Transferencia', 'efectivo' => 'Efectivo', 'cheque' => 'Cheque'] as $val => $lbl)
                                <button type="button" wire:click="$set('pagoMedio', '{{ $val }}')" class="rounded-lg border px-2 py-2 text-xs font-bold transition {{ $pagoMedio === $val ? 'border-brand bg-brand-soft text-brand' : 'border-gray-200 text-graphite hover:bg-gray-50' }}">{{ $lbl }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-4">
                    <button wire:click="cerrarPagoProveedor" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-bold text-graphite hover:bg-gray-100">Cancelar</button>
                    <button wire:click="registrarPagoProveedor" class="inline-flex items-center gap-1 rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-dark"><span class="material-symbols-outlined text-[18px]">check</span> Registrar pago</button>
                </div>
            </div>
        </div>
    @endif
</div>
