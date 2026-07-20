<div class="mx-auto max-w-5xl space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-extrabold text-ink">Recepción de mercadería</h1>
            <p class="text-sm text-muted">Registrá el remito que llega: elegí la sucursal, escanealo y controlá contra la factura.</p>
        </div>
        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-soft text-brand"><span class="material-symbols-outlined">inventory_2</span></span>
    </div>

    @if ($mensaje)
        <div class="flex items-center justify-between gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">
            <span class="flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">check_circle</span>{{ $mensaje }}</span>
            @if ($ultimoRemito)
                <a href="{{ route('recepcion.remito.etiquetas', $ultimoRemito) }}" target="_blank" class="flex items-center gap-1 rounded-lg bg-green-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-green-700">
                    <span class="material-symbols-outlined text-[16px]">print</span> Imprimir etiquetas
                </a>
            @endif
        </div>
    @endif

    {{-- ============== RECEPCIÓN DE UN REMITO ============== --}}
    @if ($compra)
        <x-panel>
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-muted">Recibiendo remito de</p>
                    <h2 class="text-lg font-extrabold text-ink">{{ $compra->numero }} · {{ $compra->proveedor?->nombre ?? '—' }}</h2>
                    @if ($compra->factura_numero)<p class="text-xs text-muted">Factura {{ $compra->factura_numero }}</p>@endif
                </div>
                <button wire:click="cerrar" class="flex items-center gap-1 text-sm font-bold text-graphite hover:text-brand">
                    <span class="material-symbols-outlined text-[20px]">arrow_back</span> Volver
                </button>
            </div>

            {{-- Sucursal destino + Nº de remito --}}
            <div class="mb-4 grid grid-cols-1 gap-3 rounded-xl border border-gray-100 bg-gray-50/60 p-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase text-muted">Sucursal destino *</label>
                    @if ($sucursalFija)
                        @php $miSuc = $locales->firstWhere('id', $localId); @endphp
                        <div class="flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-100 px-2.5 py-1.5 text-sm font-bold text-graphite" title="Tu sucursal asignada (no editable)">
                            <span class="material-symbols-outlined text-[16px] text-muted">lock</span>
                            {{ $miSuc?->nombre ?? '—' }}
                        </div>
                    @else
                        <select wire:model="localId" class="w-full rounded-lg border border-gray-200 py-1.5 pl-2.5 pr-8 text-sm outline-none focus:border-brand">
                            @foreach ($locales as $l)
                                <option value="{{ $l->id }}">{{ $l->nombre }}</option>
                            @endforeach
                        </select>
                    @endif
                    @error('localId') <p class="mt-0.5 text-[11px] text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-bold uppercase text-muted">N° de remito</label>
                    <input type="text" wire:model="numeroRemito" placeholder="R0001-..." class="w-full rounded-lg border border-gray-200 px-2.5 py-1.5 text-sm outline-none focus:border-brand" />
                </div>
            </div>

            {{-- ===== Escaneo del remito ===== --}}
            <div class="mb-4 rounded-xl border border-brand/30 bg-brand-soft/40 p-3">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-brand">document_scanner</span>
                    <div>
                        <p class="text-sm font-bold text-ink">Escanear remito</p>
                        <p class="text-xs text-muted">Sacá una foto o cargá una foto/PDF del remito; marcamos lo que llegó sobre la factura.</p>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <label class="flex cursor-pointer items-center gap-1.5 rounded-lg border border-brand bg-white px-3 py-2 text-xs font-bold text-brand hover:bg-brand-soft">
                        <span class="material-symbols-outlined text-[16px]">photo_camera</span> Sacar foto
                        <input type="file" wire:model="factura" accept="image/*" capture="environment" class="hidden" />
                    </label>
                    <label class="flex cursor-pointer items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-bold text-graphite hover:border-brand hover:text-brand">
                        <span class="material-symbols-outlined text-[16px]">upload_file</span> Subir foto o PDF
                        <input type="file" wire:model="factura" accept="image/*,application/pdf" class="hidden" />
                    </label>
                    <span wire:loading.remove wire:target="factura" class="text-xs text-muted">
                        @if ($factura) <span class="font-semibold text-ink">{{ is_object($factura) ? $factura->getClientOriginalName() : 'archivo' }}</span> @else Ningún archivo aún @endif
                    </span>
                    <span wire:loading wire:target="factura" class="flex items-center gap-1 text-xs text-muted"><span class="material-symbols-outlined text-[14px] animate-spin">progress_activity</span> Cargando…</span>
                    <button wire:click="escanearFactura" wire:loading.attr="disabled" wire:target="escanearFactura,factura" @disabled(! $factura)
                            class="ml-auto flex items-center gap-1.5 rounded-lg bg-brand px-3 py-2 text-xs font-bold text-white hover:bg-brand-dark disabled:opacity-50">
                        <span wire:loading.remove wire:target="escanearFactura" class="material-symbols-outlined text-[16px]">auto_awesome</span>
                        <span wire:loading wire:target="escanearFactura" class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                        <span wire:loading.remove wire:target="escanearFactura">Leer remito</span>
                        <span wire:loading wire:target="escanearFactura">Leyendo…</span>
                    </button>
                </div>
                @error('factura') <p class="mt-2 text-[11px] font-semibold text-red-600">{{ $message }}</p> @enderror
                @if ($scanError)
                    <p class="mt-2 flex items-center gap-1 text-[11px] font-semibold text-red-600"><span class="material-symbols-outlined text-[14px]">error</span>{{ $scanError }}</p>
                @elseif ($scanMsg)
                    <p class="mt-2 flex items-center gap-1 text-[11px] font-semibold text-green-700"><span class="material-symbols-outlined text-[14px]">check_circle</span>{{ $scanMsg }}</p>
                @endif
            </div>

            {{-- Productos del remito que NO están en la factura: reasignar --}}
            @if (count($extras))
                <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50/70 p-3">
                    <p class="mb-1 flex items-center gap-1.5 text-sm font-bold text-amber-800">
                        <span class="material-symbols-outlined text-[18px]">report</span>
                        El remito trae {{ count($extras) }} producto(s) que no figuran en la factura
                    </p>
                    <p class="mb-3 text-[11px] text-amber-700">Decidí qué hacer con cada uno. Por defecto se ignoran (no entran al stock).</p>

                    <div class="space-y-3">
                        @foreach ($extras as $i => $ex)
                            <div wire:key="ex-{{ $i }}" class="rounded-xl border border-amber-200 bg-white p-3">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p class="font-bold text-ink">{{ $ex['codigo'] ? $ex['codigo'].' · ' : '' }}{{ $ex['descripcion'] }}</p>
                                        <p class="text-xs text-muted">Remito: x{{ (int) $ex['cantidad'] }} · costo unit. ${{ number_format((float) $ex['costo'], 2, ',', '.') }}</p>
                                    </div>
                                    <select wire:model.live="extras.{{ $i }}.destino" class="rounded-lg border border-gray-200 py-1.5 pl-2.5 pr-8 text-sm font-semibold outline-none focus:border-brand">
                                        <option value="ignorar">Ignorar</option>
                                        <option value="item">Vincular a un renglón</option>
                                        <option value="agregar">Agregar a la factura</option>
                                    </select>
                                </div>

                                {{-- Vincular a un renglón pendiente de la factura --}}
                                @if (($ex['destino'] ?? 'ignorar') === 'item')
                                    <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        <div>
                                            <label class="mb-1 block text-[11px] font-bold uppercase text-muted">¿A qué renglón corresponde?</label>
                                            <select wire:model="extras.{{ $i }}.item_id" class="w-full rounded-lg border border-gray-200 py-1.5 pl-2.5 pr-8 text-sm outline-none focus:border-brand">
                                                <option value="">Elegí un renglón…</option>
                                                @foreach ($rItems as $r)
                                                    <option value="{{ $r['item_id'] }}">{{ $r['desc'] }} (pend. {{ $r['pendiente'] }})</option>
                                                @endforeach
                                            </select>
                                            @error("extras.{$i}.item_id") <p class="mt-0.5 text-[11px] text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-[11px] font-bold uppercase text-muted">Cantidad</label>
                                            <input type="number" min="1" wire:model="extras.{{ $i }}.cantidad" class="w-full rounded-lg border border-gray-200 px-2.5 py-1.5 text-sm outline-none focus:border-brand" />
                                        </div>
                                    </div>
                                    <p class="mt-1.5 text-[11px] text-muted">Suma esa cantidad al renglón elegido (corrige un reconocimiento fallido).</p>
                                @endif

                                {{-- Agregar como ítem nuevo de la factura --}}
                                @if (($ex['destino'] ?? 'ignorar') === 'agregar')
                                    <div class="mt-3 space-y-2">
                                        <div>
                                            <label class="mb-1 block text-[11px] font-bold uppercase text-muted">¿Qué producto es?</label>
                                            <select wire:model.live="extras.{{ $i }}.prod_sel" class="w-full rounded-lg border border-gray-200 py-1.5 pl-2.5 pr-8 text-sm outline-none focus:border-brand">
                                                <option value="">Elegí el producto…</option>
                                                @foreach (($ex['candidatos'] ?? []) as $c)
                                                    <option value="{{ $c['producto_id'] }}">{{ $c['nombre'] }}{{ isset($c['score']) ? '  ('.round($c['score']*100).'%)' : '' }}</option>
                                                @endforeach
                                                <option value="nuevo">➕ Producto nuevo (no está en el catálogo)</option>
                                            </select>
                                            @error("extras.{$i}.prod_sel") <p class="mt-0.5 text-[11px] text-red-600">{{ $message }}</p> @enderror
                                        </div>

                                        @if (($ex['prod_sel'] ?? '') === 'nuevo')
                                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                                <div>
                                                    <label class="mb-1 block text-[11px] font-bold uppercase text-muted">Nombre del producto *</label>
                                                    <input type="text" wire:model="extras.{{ $i }}.nuevo_nombre" class="w-full rounded-lg border border-gray-200 px-2.5 py-1.5 text-sm outline-none focus:border-brand" />
                                                    @error("extras.{$i}.nuevo_nombre") <p class="mt-0.5 text-[11px] text-red-600">{{ $message }}</p> @enderror
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-[11px] font-bold uppercase text-muted">Código (opcional)</label>
                                                    <input type="text" wire:model="extras.{{ $i }}.nuevo_codigo" class="w-full rounded-lg border border-gray-200 px-2.5 py-1.5 text-sm outline-none focus:border-brand" />
                                                </div>
                                            </div>
                                        @endif

                                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                            <div>
                                                <label class="mb-1 block text-[11px] font-bold uppercase text-muted">Cantidad *</label>
                                                <input type="number" min="1" wire:model="extras.{{ $i }}.cantidad" class="w-full rounded-lg border border-gray-200 px-2.5 py-1.5 text-sm outline-none focus:border-brand" />
                                                @error("extras.{$i}.cantidad") <p class="mt-0.5 text-[11px] text-red-600">{{ $message }}</p> @enderror
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-[11px] font-bold uppercase text-muted">Costo neto</label>
                                                <input type="number" min="0" step="0.01" wire:model="extras.{{ $i }}.costo" class="w-full rounded-lg border border-gray-200 px-2.5 py-1.5 text-sm outline-none focus:border-brand" />
                                            </div>
                                            <div class="col-span-2 sm:col-span-1">
                                                <label class="mb-1 block text-[11px] font-bold uppercase text-muted">Nota (opcional)</label>
                                                <input type="text" wire:model="extras.{{ $i }}.nota" class="w-full rounded-lg border border-gray-200 px-2.5 py-1.5 text-sm outline-none focus:border-brand" />
                                            </div>
                                        </div>
                                        <p class="text-[11px] text-muted">Se agrega a la factura, suma al stock de la sucursal y genera trazabilidad.</p>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (count($rItems))
                {{-- Ítems pendientes de la factura --}}
                <div class="space-y-3">
                    @foreach ($rItems as $i => $r)
                        @php
                            $pend = (int) $r['pendiente'];
                            $est = $r['estado'] ?? 'ok';
                            $recibida = $est === 'ok' ? $pend : ($est === 'parcial' ? (int) $r['llego'] : ($est === 'defectuoso' ? $pend : 0));
                            $def = $est === 'defectuoso' ? (int) $r['defectuosos'] : 0;
                            $okIngresan = max(0, $recibida - $def);
                        @endphp
                        <div wire:key="ri-{{ $r['item_id'] }}" class="rounded-xl border border-gray-100 p-3 shadow-soft
                            {{ $est === 'no_llego' ? 'bg-red-50/40' : ($est === 'defectuoso' ? 'bg-amber-50/50' : '') }}">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="font-bold text-ink">{{ $r['desc'] }}</p>
                                    <p class="text-xs text-muted">Cód: {{ $r['cod'] }}{{ $r['sku'] ? ' · SKU '.$r['sku'] : '' }}</p>
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                        @if ($r['match'] === 'alta')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-bold text-green-700"><span class="material-symbols-outlined text-[12px]">verified</span>Confirmado por remito</span>
                                        @elseif ($r['match'] === 'dudosa')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700"><span class="material-symbols-outlined text-[12px]">help</span>Match dudoso</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <select wire:model.live="rItems.{{ $i }}.estado" class="rounded-lg border border-gray-200 py-1.5 pl-2.5 pr-8 text-sm font-semibold outline-none focus:border-brand">
                                        <option value="ok">Llegó OK</option>
                                        <option value="parcial">Llegó parcial</option>
                                        <option value="defectuoso">Con defectuosos</option>
                                        <option value="no_llego">No llegó</option>
                                    </select>
                                    <div class="text-right">
                                        <p class="text-[10px] font-bold uppercase text-muted">Ingresan</p>
                                        <p class="text-lg font-extrabold {{ $okIngresan === 0 ? 'text-red-600' : ($est !== 'ok' ? 'text-amber-600' : 'text-green-600') }}">{{ $okIngresan }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-5">
                                <div>
                                    <label class="mb-1 block text-[11px] font-bold uppercase text-muted">Facturado</label>
                                    <p class="rounded-lg bg-gray-100 px-2.5 py-1.5 text-sm font-semibold text-graphite">{{ $r['facturado'] }}</p>
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-bold uppercase text-muted">Ya recibido</label>
                                    <p class="rounded-lg bg-gray-100 px-2.5 py-1.5 text-sm font-semibold text-graphite">{{ $r['yaRecibido'] }}</p>
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-bold uppercase text-brand">Pendiente</label>
                                    <p class="rounded-lg bg-brand-soft px-2.5 py-1.5 text-sm font-bold text-brand">{{ $pend }}</p>
                                </div>
                                {{-- Campo según el estado elegido --}}
                                @if ($est === 'parcial')
                                    <div>
                                        <label class="mb-1 block text-[11px] font-bold uppercase text-amber-700">¿Cuántos llegaron?</label>
                                        <input type="number" min="1" max="{{ $pend }}" wire:model.live="rItems.{{ $i }}.llego"
                                               class="w-full rounded-lg border border-amber-300 px-2.5 py-1.5 text-sm outline-none focus:border-brand" />
                                        @error("rItems.{$i}.llego") <p class="mt-0.5 text-[11px] text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                @elseif ($est === 'defectuoso')
                                    <div>
                                        <label class="mb-1 block text-[11px] font-bold uppercase text-amber-700">¿Cuántos defectuosos?</label>
                                        <input type="number" min="1" max="{{ $pend }}" wire:model.live="rItems.{{ $i }}.defectuosos"
                                               class="w-full rounded-lg border border-amber-300 px-2.5 py-1.5 text-sm outline-none focus:border-brand" />
                                        @error("rItems.{$i}.defectuosos") <p class="mt-0.5 text-[11px] text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                @else
                                    <div class="hidden sm:block"></div>
                                @endif
                                <div>
                                    <label class="mb-1 block text-[11px] font-bold uppercase text-muted">Costo neto (factura)</label>
                                    <p class="rounded-lg bg-gray-100 px-2.5 py-1.5 text-sm font-semibold text-graphite">${{ number_format((float) $r['costo'], 2, ',', '.') }}</p>
                                    <p class="mt-0.5 text-[11px] text-muted">Puesto en depósito: <span class="font-semibold text-graphite">${{ number_format((float) ($r['costoDepo'] ?? 0), 2, ',', '.') }}</span></p>
                                </div>
                            </div>

                            @if ($est !== 'ok')
                                <div class="mt-2">
                                    <label class="mb-1 block text-[11px] font-bold uppercase text-muted">Observación <span class="text-red-600">*</span></label>
                                    <input type="text" wire:model="rItems.{{ $i }}.nota" placeholder="Detalle de lo que llegó / falla / por qué no llegó"
                                           class="w-full rounded-lg border border-red-300 px-2.5 py-1.5 text-sm outline-none focus:border-brand" />
                                    @error("rItems.{$i}.nota") <p class="mt-0.5 text-[11px] text-red-600">{{ $message }}</p> @enderror
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
                @error('rItems') <p class="mt-2 text-[11px] font-semibold text-red-600">{{ $message }}</p> @enderror

                <div class="mt-4 flex items-center justify-end gap-2">
                    <button wire:click="cerrar" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold text-graphite hover:bg-gray-50">Cancelar</button>
                    <button wire:click="confirmarRecepcion" class="flex items-center gap-1.5 rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-dark">
                        <span class="material-symbols-outlined text-[18px]">check</span> Confirmar remito
                    </button>
                </div>
            @else
                <p class="py-6 text-center text-sm text-muted">Esta factura ya no tiene saldo pendiente.</p>
            @endif
        </x-panel>

    {{-- ============== LISTA: POR RECIBIR + REMITOS RECIENTES ============== --}}
    @else
        <x-panel title="Por recibir (facturas con saldo pendiente)">
            @forelse ($porRecibir as $c)
                @php $pendTotal = $c->items->sum(fn ($it) => $it->pendiente()); @endphp
                <div wire:key="pr-{{ $c->id }}"
                     class="flex items-center justify-between gap-3 border-b border-gray-50 px-2 py-3 last:border-0 {{ $highlight === $c->numero ? 'rounded-lg bg-brand-soft ring-2 ring-inset ring-brand' : '' }}"
                     @if ($highlight === $c->numero) x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'center' })" @endif>
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 text-blue-700"><span class="material-symbols-outlined text-[20px]">local_shipping</span></span>
                        <div>
                            <p class="font-bold text-ink">{{ $c->numero }} · {{ $c->proveedor?->nombre ?? '—' }}</p>
                            <p class="text-xs text-muted">
                                {{ $c->factura_numero ? 'Factura '.$c->factura_numero.' · ' : '' }}
                                <span class="font-semibold text-brand">{{ $pendTotal }} u. pendientes</span>
                                · <span class="font-semibold {{ $c->estado === 'parcial' ? 'text-amber-600' : 'text-green-600' }}">{{ ucfirst($c->estado) }}</span>
                            </p>
                        </div>
                    </div>
                    @puede('recepcionar')
                        <button wire:click="abrir({{ $c->id }})" class="flex items-center gap-1 rounded-lg bg-brand px-3 py-1.5 text-xs font-bold text-white hover:bg-brand-dark">
                            <span class="material-symbols-outlined text-[16px]">move_to_inbox</span> Recibir entrega
                        </button>
                    @endpuede
                </div>
            @empty
                <p class="py-8 text-center text-sm text-muted">No hay mercadería pendiente de recibir.</p>
            @endforelse
        </x-panel>

        @if ($remitosRecientes->isNotEmpty())
            <x-panel title="Remitos recibidos recientemente">
                <div class="mb-2 flex justify-end">
                    <a href="{{ route('recepcion.etiquetas.dia') }}" target="_blank" class="flex items-center gap-1 rounded-lg bg-brand px-3 py-1.5 text-xs font-bold text-white hover:bg-brand-dark">
                        <span class="material-symbols-outlined text-[16px]">print</span> Imprimir etiquetas del día
                    </a>
                </div>
                @foreach ($remitosRecientes as $rm)
                    <div wire:key="rm-{{ $rm->id }}" class="flex items-center justify-between gap-3 border-b border-gray-50 py-2.5 last:border-0">
                        <div>
                            <p class="text-sm font-bold text-ink">{{ $rm->numero ?: 'Remito #'.$rm->id }} · {{ $rm->compra?->proveedor?->nombre ?? '—' }}</p>
                            <p class="text-xs text-muted">{{ $rm->compra?->numero }} · {{ $rm->local?->nombre }} · {{ $rm->recibidoPor?->name ?? '—' }} · {{ $rm->recibido_at?->format('d/m/Y H:i') }}</p>
                        </div>
                        <a href="{{ route('recepcion.remito.etiquetas', $rm->id) }}" target="_blank" class="flex items-center gap-1 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-bold text-graphite hover:border-brand hover:text-brand">
                            <span class="material-symbols-outlined text-[16px]">print</span> Etiquetas
                        </a>
                    </div>
                @endforeach
            </x-panel>
        @endif
    @endif
</div>
