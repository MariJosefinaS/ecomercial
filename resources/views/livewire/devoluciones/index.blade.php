<div class="space-y-6">

    @php
        $estBadge = ['pendiente' => 'bg-amber-100 text-amber-700', 'aprobada' => 'bg-green-100 text-green-700', 'rechazada' => 'bg-red-100 text-red-700'];
        $segBadge = ['reingresado' => 'bg-green-100 text-green-700', 'enviado_a_fabrica' => 'bg-amber-100 text-amber-700', 'en_reparacion' => 'bg-sky-100 text-sky-700', 'defectuoso' => 'bg-red-100 text-red-700'];
        $segLabel = ['reingresado' => 'Reingresado', 'enviado_a_fabrica' => 'En fábrica', 'en_reparacion' => 'En reparación', 'defectuoso' => 'Defectuoso'];
        $medioLabel = ['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'cheque' => 'Cheque', 'cuenta_corriente' => 'Cta. corriente', 'diario' => 'Pago diario', 'semanal' => 'Pago semanal'];
    @endphp

    <div class="flex items-center justify-between">
        <div><h1 class="text-2xl font-extrabold text-ink">Devoluciones</h1><p class="text-sm text-muted">Anulación de ventas, reingreso de stock y seguimiento</p></div>
        @puede('crear_devolucion')
        <button wire:click="nuevaDevolucion" class="flex items-center gap-2 rounded-lg bg-brand px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-dark">
            <span class="material-symbols-outlined text-[20px]">assignment_return</span> Nueva devolución
        </button>
        @endpuede
    </div>

    @if ($mensaje)
        <div class="flex items-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-4 py-2.5 text-sm font-semibold text-sky-700">
            <span class="material-symbols-outlined text-[18px]">info</span> {{ $mensaje }}
        </div>
    @endif

    {{-- Efectos automáticos de la última aprobación --}}
    @if (! empty($efectos))
        <div class="rounded-xl border-2 border-green-200 bg-green-50 p-5">
            <h3 class="mb-2 flex items-center gap-1.5 text-sm font-extrabold uppercase text-green-700"><span class="material-symbols-outlined text-[20px]">auto_awesome</span> Devolución aprobada — efectos automáticos</h3>
            <ul class="space-y-1">
                @foreach ($efectos as $e)
                    <li class="flex items-start gap-2 text-sm text-ink"><span class="material-symbols-outlined mt-0.5 text-[16px] text-green-600">check</span> {{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Stats --}}
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi-card variant="white" title="Pendientes" :value="$stats['pendientes']" icon="pending_actions" subtitle="A revisar" />
        <x-kpi-card variant="blue"  title="Aprobadas" :value="$stats['aprobadas']" icon="task_alt" />
        <x-kpi-card variant="beige" title="En Fábrica / Reparación" :value="$stats['a_fabrica']" icon="build" />
        <x-kpi-card variant="brand" title="Monto Devuelto" :value="'$' . number_format($stats['monto'], 0, ',', '.')" icon="assignment_return" />
    </div>

    <x-panel title="Listado de devoluciones">
        <x-slot:actions><span class="text-xs font-semibold text-muted">{{ count($filas) }} resultado(s)</span></x-slot:actions>

        <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-5">
            <div class="relative min-w-[240px] flex-1">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[20px] text-muted">search</span>
                <input type="text" wire:model.live.debounce.300ms="buscar" placeholder="Buscar por cliente, venta o producto..." class="w-full rounded-full border border-gray-200 bg-gray-50 py-2 pl-10 pr-4 text-sm font-medium outline-none transition focus:border-brand focus:bg-white focus:ring-2 focus:ring-brand/20" />
            </div>
            <div class="flex overflow-hidden rounded-lg border border-gray-200 text-sm font-bold">
                @foreach (['todos' => 'Todas', 'pendiente' => 'Pendientes', 'aprobada' => 'Aprobadas', 'rechazada' => 'Rechazadas'] as $val => $lbl)
                    <button wire:click="$set('estado', '{{ $val }}')" class="px-3 py-2 transition {{ $estado === $val ? 'bg-brand text-white' : 'text-graphite hover:bg-gray-50' }}">{{ $lbl }}</button>
                @endforeach
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wide text-muted">
                        <th class="px-5 py-3 font-bold">Fecha</th><th class="px-5 py-3 font-bold">Cliente / Venta</th><th class="px-5 py-3 font-bold">Producto</th>
                        <th class="px-5 py-3 font-bold">Medio pago</th><th class="px-5 py-3 text-right font-bold">Monto</th>
                        <th class="px-5 py-3 text-center font-bold">Seguimiento</th><th class="px-5 py-3 text-center font-bold">Estado</th><th class="px-5 py-3 text-right font-bold">Acciones</th>
                    </tr>
                </thead>
                <tbody class="tabular">
                    @forelse ($filas as $d)
                        <tr class="border-t border-gray-100 hover:bg-brand-soft/40" wire:key="dev-{{ $d['id'] }}">
                            <td class="px-5 py-3 text-graphite">{{ $d['fecha'] }}</td>
                            <td class="px-5 py-3"><p class="font-bold text-ink">{{ $d['cliente'] }}</p><p class="text-xs text-muted">{{ $d['venta'] }}</p></td>
                            <td class="px-5 py-3"><p class="font-semibold text-ink">{{ $d['producto'] }} <span class="text-muted">x{{ $d['cant'] }}</span></p><p class="text-xs text-red-600">{{ $d['motivo'] }}</p></td>
                            <td class="px-5 py-3"><span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-bold text-graphite">{{ $medioLabel[$d['medio']] ?? $d['medio'] }}</span></td>
                            <td class="px-5 py-3 text-right font-bold text-ink">${{ number_format($d['monto'], 2, ',', '.') }}</td>
                            <td class="px-5 py-3 text-center">
                                @if ($d['seguimiento'])
                                    <span class="inline-block rounded-full px-2 py-0.5 text-[10px] font-bold uppercase {{ $segBadge[$d['seguimiento']] }}">{{ $segLabel[$d['seguimiento']] }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-center"><span class="inline-block rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase {{ $estBadge[$d['estado']] }}">{{ $d['estado'] }}</span></td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    @puede('aprobar_devoluciones')
                                        @if ($d['estado'] === 'pendiente')
                                            <button wire:click="aprobar({{ $d['id'] }})" class="rounded-lg bg-success px-3 py-1.5 text-xs font-bold text-white hover:brightness-95">Aprobar</button>
                                            <button wire:click="rechazar({{ $d['id'] }})" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-bold text-graphite hover:bg-gray-50">Rechazar</button>
                                        @elseif ($d['estado'] === 'aprobada' && $d['seguimiento'] === 'enviado_a_fabrica')
                                            <button wire:click="marcarReparado({{ $d['id'] }})" class="rounded-lg bg-success px-3 py-1.5 text-xs font-bold text-white hover:brightness-95" title="Reparado → reingresa a stock">Reingresar</button>
                                            <button wire:click="marcarDefectuoso({{ $d['id'] }})" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-bold text-graphite hover:bg-gray-50">Defectuoso</button>
                                        @else
                                            <span class="material-symbols-outlined text-[20px] text-gray-300">check_circle</span>
                                        @endif
                                    @else
                                        {{-- Sin permiso para aprobar (ej. vendedor): solo lectura del estado. --}}
                                        @if ($d['estado'] === 'pendiente')
                                            <span class="text-xs font-semibold italic text-muted">Pendiente de aprobación</span>
                                        @else
                                            <span class="material-symbols-outlined text-[20px] text-gray-300">check_circle</span>
                                        @endif
                                    @endpuede
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-10 text-center text-sm text-muted"><span class="material-symbols-outlined mb-1 block text-3xl">assignment_return</span>No hay devoluciones con esos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-panel>

    {{-- ===== Modal nueva devolución ===== --}}
    @if ($modal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" wire:click="$set('modal', false)"></div>
            <div class="relative z-10 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 class="text-base font-extrabold text-ink">Nueva devolución</h3>
                    <button wire:click="$set('modal', false)" class="text-muted hover:text-danger"><span class="material-symbols-outlined">close</span></button>
                </div>
                <form wire:submit="guardarDevolucion" class="space-y-4 p-5">
                    {{-- Código de trazabilidad de la caja → identifica origen y venta --}}
                    <div class="rounded-xl border border-brand/30 bg-brand-soft/40 p-3">
                        <label class="mb-1 block text-xs font-bold uppercase text-brand">Código de trazabilidad de la caja</label>
                        <input type="text" wire:model.live.debounce.400ms="fCodigo" placeholder="TRZ-…"
                               class="w-full rounded-lg border border-gray-200 px-3 py-2 font-mono text-sm uppercase outline-none focus:border-brand" />
                        @error('fCodigo')<p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p>@enderror
                        @php $info = $this->unidadInfo; @endphp
                        @if ($info && isset($info['error']))
                            <p class="mt-2 flex items-center gap-1 text-xs font-semibold text-danger"><span class="material-symbols-outlined text-[14px]">error</span>{{ $info['error'] }}</p>
                        @elseif ($info)
                            <div class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 rounded-lg bg-white p-2.5 text-xs">
                                <p><span class="text-muted">Producto:</span> <span class="font-bold text-ink">{{ $info['producto'] }}</span></p>
                                <p><span class="text-muted">Proveedor:</span> <span class="font-bold text-ink">{{ $info['proveedor'] }}</span></p>
                                <p><span class="text-muted">Ingresó:</span> <span class="font-semibold">{{ $info['fecha_ingreso'] }}</span></p>
                                <p><span class="text-muted">Vendido:</span> <span class="font-semibold">{{ $info['venta'] ? $info['venta'].' · '.$info['fecha_venta'] : 'sin venta registrada' }}</span></p>
                                @if ($info['cliente'])<p class="col-span-2"><span class="text-muted">Cliente:</span> <span class="font-semibold">{{ $info['cliente'] }}</span></p>@endif
                                <p class="col-span-2"><span class="text-muted">Estado actual:</span> <span class="font-bold text-brand">{{ $info['estado'] }}</span></p>
                            </div>
                        @else
                            <p class="mt-1 text-[11px] text-muted">Opcional pero recomendado: con el código se completan solos el producto, la venta y el cliente.</p>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div><label class="mb-1 block text-xs font-bold uppercase text-muted">Venta (N°)</label><input type="text" wire:model="fVenta" placeholder="FAC-…" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />@error('fVenta')<p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p>@enderror</div>
                        <div><label class="mb-1 block text-xs font-bold uppercase text-muted">Cliente</label><input type="text" wire:model="fCliente" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />@error('fCliente')<p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p>@enderror</div>
                        <div><label class="mb-1 block text-xs font-bold uppercase text-muted">Producto</label><input type="text" wire:model="fProducto" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />@error('fProducto')<p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p>@enderror</div>
                        <div class="grid grid-cols-2 gap-3">
                            <div><label class="mb-1 block text-xs font-bold uppercase text-muted">Cant.</label><input type="number" wire:model="fCant" min="1" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" /></div>
                            <div><label class="mb-1 block text-xs font-bold uppercase text-muted">Monto</label><input type="number" wire:model="fMonto" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />@error('fMonto')<p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p>@enderror</div>
                        </div>
                        <div><label class="mb-1 block text-xs font-bold uppercase text-muted">Medio de pago original</label>
                            <select wire:model="fMedio" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                                <option value="efectivo">Efectivo</option><option value="transferencia">Transferencia</option><option value="cheque">Cheque</option><option value="cuenta_corriente">Cuenta corriente</option><option value="diario">Pago diario</option><option value="semanal">Pago semanal</option>
                            </select>
                        </div>
                        <div><label class="mb-1 block text-xs font-bold uppercase text-muted">Condición del producto</label>
                            <select wire:model="fCondicion" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                                <option value="en_condiciones">En condiciones (reingresa a stock)</option><option value="a_fabrica">Enviar a fábrica (reparación)</option><option value="defectuoso">Defectuoso (baja)</option>
                            </select>
                        </div>
                    </div>
                    <div><label class="mb-1 block text-xs font-bold uppercase text-muted">Motivo</label><input type="text" wire:model="fMotivo" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />@error('fMotivo')<p class="mt-1 text-xs font-semibold text-danger">{{ $message }}</p>@enderror</div>
                    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <button type="button" wire:click="$set('modal', false)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold text-graphite hover:bg-gray-50">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-dark">Registrar devolución</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
