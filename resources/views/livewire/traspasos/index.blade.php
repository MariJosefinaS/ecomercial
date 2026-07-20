<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-extrabold text-ink">Traspasos entre sucursales</h1>
            <p class="text-sm text-muted">Mové cajas de un local a otro por su código de trazabilidad. Requiere aprobación.</p>
        </div>
        @puede('crear_traspaso')
            <button wire:click="nuevo" class="flex items-center gap-1.5 rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-dark">
                <span class="material-symbols-outlined text-[18px]">swap_horiz</span> Nuevo traspaso
            </button>
        @endpuede
    </div>

    @if ($mensaje)
        <div class="flex items-center gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">
            <span class="material-symbols-outlined text-[18px]">check_circle</span>{{ $mensaje }}
        </div>
    @endif

    <x-panel>
        <div class="mb-3 flex items-center gap-2">
            @foreach (['todos' => 'Todos', 'pendiente' => 'Pendientes', 'aprobada' => 'Aprobados', 'rechazada' => 'Rechazados'] as $val => $lbl)
                <button wire:click="$set('estado', '{{ $val }}')" class="rounded-lg px-3 py-1.5 text-xs font-bold {{ $estado === $val ? 'bg-brand text-white' : 'text-graphite hover:bg-gray-50' }}">{{ $lbl }}</button>
            @endforeach
            @if ($pendientes)
                <span class="ml-auto rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-700">{{ $pendientes }} pendiente(s)</span>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-[11px] uppercase text-muted">
                    <tr class="border-b border-gray-100">
                        <th class="px-3 py-2">N°</th>
                        <th class="px-3 py-2">Ruta</th>
                        <th class="px-3 py-2 text-center">Cajas</th>
                        <th class="px-3 py-2">Solicitó</th>
                        <th class="px-3 py-2">Fecha</th>
                        <th class="px-3 py-2 text-center">Estado</th>
                        <th class="px-3 py-2 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($filas as $tr)
                        <tr class="border-t border-gray-50 hover:bg-brand-soft/40 {{ $highlight === $tr->numero ? 'bg-brand-soft' : '' }}" wire:key="tr-{{ $tr->id }}">
                            <td class="px-3 py-2 font-bold text-ink">{{ $tr->numero }}</td>
                            <td class="px-3 py-2">
                                <span class="font-semibold text-graphite">{{ $tr->origen?->nombre }}</span>
                                <span class="material-symbols-outlined align-middle text-[16px] text-brand">east</span>
                                <span class="font-semibold text-graphite">{{ $tr->destino?->nombre }}</span>
                                @if ($tr->motivo)<p class="text-[11px] text-muted">{{ $tr->motivo }}</p>@endif
                            </td>
                            <td class="px-3 py-2 text-center font-bold">{{ $tr->items_count }}</td>
                            <td class="px-3 py-2 text-graphite">{{ $tr->usuario?->name ?? '—' }}</td>
                            <td class="px-3 py-2 text-graphite">{{ $tr->fecha?->format('d/m/Y') }}</td>
                            <td class="px-3 py-2 text-center">
                                <span class="inline-block rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase
                                    {{ $tr->estado === 'aprobada' ? 'bg-green-100 text-green-700' : ($tr->estado === 'rechazada' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">{{ $tr->estado }}</span>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-center justify-end gap-2">
                                    @if ($tr->estado === 'pendiente')
                                        @puede('aprobar_traspasos')
                                            <button wire:click="aprobar({{ $tr->id }})" class="rounded-lg bg-success px-3 py-1.5 text-xs font-bold text-white hover:brightness-95">Aprobar</button>
                                            <button wire:click="rechazar({{ $tr->id }})" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-bold text-graphite hover:bg-gray-50">Rechazar</button>
                                        @else
                                            <span class="text-xs text-muted">Pendiente</span>
                                        @endpuede
                                    @else
                                        <span class="text-xs text-muted">—</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-10 text-center text-sm text-muted">
                            <span class="material-symbols-outlined mb-1 block text-3xl">swap_horiz</span>No hay traspasos.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-panel>

    {{-- ===== Modal nuevo traspaso ===== --}}
    @if ($modal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" wire:click="$set('modal', false)"></div>
            <div class="relative z-10 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-5 shadow-xl">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-lg font-extrabold text-ink">Nuevo traspaso</h3>
                    <button wire:click="$set('modal', false)" class="text-muted hover:text-danger"><span class="material-symbols-outlined">close</span></button>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-[11px] font-bold uppercase text-muted">Origen *</label>
                        @if ($sucursalFija)
                            @php $o = $locales->firstWhere('id', $tOrigen); @endphp
                            <div class="flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-100 px-2.5 py-2 text-sm font-bold text-graphite"><span class="material-symbols-outlined text-[16px] text-muted">lock</span>{{ $o?->nombre ?? '—' }}</div>
                        @else
                            <select wire:model="tOrigen" class="w-full rounded-lg border border-gray-200 px-2.5 py-2 text-sm outline-none focus:border-brand">
                                @foreach ($locales as $l)<option value="{{ $l->id }}">{{ $l->nombre }}</option>@endforeach
                            </select>
                        @endif
                        @error('tOrigen')<p class="mt-0.5 text-[11px] text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-bold uppercase text-muted">Destino *</label>
                        <select wire:model="tDestino" class="w-full rounded-lg border border-gray-200 px-2.5 py-2 text-sm outline-none focus:border-brand">
                            <option value="">Seleccionar…</option>
                            @foreach ($locales as $l)<option value="{{ $l->id }}">{{ $l->nombre }}</option>@endforeach
                        </select>
                        @error('tDestino')<p class="mt-0.5 text-[11px] text-danger">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="mt-3">
                    <label class="mb-1 block text-[11px] font-bold uppercase text-muted">Motivo (opcional)</label>
                    <input type="text" wire:model="tMotivo" placeholder="Reposición, pedido de la otra sucursal…" class="w-full rounded-lg border border-gray-200 px-2.5 py-2 text-sm outline-none focus:border-brand" />
                </div>

                <div class="mt-4">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-muted">Cajas a traspasar (código)</p>
                        <button type="button" wire:click="agregarCodigo" class="inline-flex items-center gap-1 text-xs font-bold text-brand hover:text-brand-dark"><span class="material-symbols-outlined text-[18px]">add</span> Agregar</button>
                    </div>
                    <div class="space-y-2">
                        @php $estados = $this->codigosEstado; @endphp
                        @foreach ($tCodigos as $i => $cod)
                            @php $st = $estados[$i] ?? null; @endphp
                            <div wire:key="tcod-{{ $i }}">
                                <div class="flex items-center gap-2">
                                    {{-- Auto-guion del lado del cliente (sin saltos de cursor): arma TRZ-AAMMDD-PPP-SS-NNNN a partir de los dígitos --}}
                                    <input type="text" wire:model.live.debounce.400ms="tCodigos.{{ $i }}" placeholder="TRZ-…" maxlength="21"
                                           x-data
                                           x-on:input="
                                               let d = $el.value.toUpperCase().replace(/[^0-9]/g,'').slice(0,15);
                                               let out = d ? 'TRZ-'+d.slice(0,6) : '';
                                               if (d.length>6) out += '-'+d.slice(6,9);
                                               if (d.length>9) out += '-'+d.slice(9,11);
                                               if (d.length>11) out += '-'+d.slice(11,15);
                                               $el.value = out;
                                           "
                                           class="w-full rounded-lg border px-2.5 py-1.5 font-mono text-sm uppercase outline-none focus:border-brand
                                                  {{ $st === null ? 'border-gray-200' : ($st['ok'] ? 'border-green-400' : 'border-red-400') }}" />
                                    @if ($st !== null)
                                        <span class="material-symbols-outlined text-[20px] {{ $st['ok'] ? 'text-green-600' : 'text-red-500' }}">{{ $st['ok'] ? 'check_circle' : 'cancel' }}</span>
                                    @endif
                                    <button type="button" wire:click="quitarCodigo({{ $i }})" class="text-muted hover:text-danger"><span class="material-symbols-outlined text-[20px]">delete</span></button>
                                </div>
                                @if ($st !== null)
                                    <p class="mt-0.5 text-[11px] font-semibold {{ $st['ok'] ? 'text-green-600' : 'text-red-500' }}">{{ $st['msg'] }}</p>
                                @endif
                                @error("tCodigos.{$i}")<p class="text-[11px] text-danger">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <button wire:click="$set('modal', false)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold text-graphite hover:bg-gray-50">Cancelar</button>
                    <button wire:click="guardar" class="rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-dark">Crear traspaso</button>
                </div>
            </div>
        </div>
    @endif
</div>
