@php
    $riesgoColor = ['alto' => 'bg-red-100 text-red-700', 'medio' => 'bg-amber-100 text-amber-700', 'bajo' => 'bg-green-100 text-green-700'];
    $calc = $this->plan;
    $esCredito = ($planes[$planCodigo]['modalidad'] ?? 'contado') !== 'contado';
    $unidad = $calc['unidad'] ?: 'días';
    $pasos = [1 => 'Cliente', 2 => 'Artículos', 3 => 'Plan / Financiación', 4 => 'Confirmar'];
@endphp

<div class="mx-auto max-w-3xl space-y-6">

    {{-- Encabezado --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-ink">Nueva venta · Nota de pedido</h1>
            <p class="text-sm text-muted">Cargá el pedido; queda pendiente de aprobación del administrador.</p>
        </div>
        <a href="{{ route('ventas') }}" wire:navigate class="flex items-center gap-1 text-sm font-bold text-muted hover:text-brand">
            <span class="material-symbols-outlined text-[20px]">arrow_back</span> Volver al listado
        </a>
    </div>

    {{-- Stepper --}}
    <div class="flex items-center justify-between rounded-xl border border-gray-100 bg-white px-4 py-3 shadow-card">
        @foreach ($pasos as $n => $label)
            <div class="flex items-center gap-2 {{ $n < count($pasos) ? 'flex-1' : '' }}">
                <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full text-xs font-extrabold
                    {{ $paso === $n ? 'bg-brand text-white' : ($paso > $n ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-muted') }}">
                    @if ($paso > $n) <span class="material-symbols-outlined text-[18px]">check</span> @else {{ $n }} @endif
                </span>
                <span class="hidden text-xs font-bold sm:block {{ $paso === $n ? 'text-ink' : 'text-muted' }}">{{ $label }}</span>
                @if ($n < count($pasos))
                    <span class="mx-2 hidden h-px flex-1 bg-gray-200 sm:block"></span>
                @endif
            </div>
        @endforeach
    </div>

    <x-panel>
        <div class="p-5">

            {{-- ============================ PASO 1: CLIENTE ============================ --}}
            @if ($paso === 1)
                <h2 class="mb-3 text-base font-extrabold text-ink">Cliente</h2>

                @if ($cliId)
                    <div class="rounded-2xl border border-gray-100 bg-white p-3 shadow-soft">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="font-bold text-ink">{{ $cliNombre }}</p>
                                <p class="text-xs text-muted">{{ $cliDoc ?: 'Sin documento' }}</p>
                            </div>
                            <button type="button" wire:click="cambiarCliente" class="text-xs font-bold text-brand">Cambiar</button>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-1.5">
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $riesgoColor[$cliRiesgo] ?? 'bg-gray-100 text-gray-600' }}">Riesgo {{ $cliRiesgo }}</span>
                            @if ($cliNuevo)
                                <span class="rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-bold text-blue-700">Cliente nuevo · requiere aprobación</span>
                            @endif
                        </div>

                        @if ($p = $this->perfilCliente)
                            <div class="mt-3 grid grid-cols-2 gap-2 border-t border-gray-100 pt-3 text-center sm:grid-cols-4">
                                <div><p class="text-[10px] font-semibold uppercase text-muted">Saldo cta. cte.</p><p class="text-sm font-extrabold {{ $p['saldo'] > 0 ? 'text-red-600' : 'text-ink' }}">${{ number_format($p['saldo'], 2, ',', '.') }}</p></div>
                                <div><p class="text-[10px] font-semibold uppercase text-muted">Última compra</p><p class="text-sm font-extrabold text-ink">{{ $p['ultima_fecha'] ? '$' . number_format($p['ultima_monto'], 0, ',', '.') : '—' }}</p></div>
                                <div><p class="text-[10px] font-semibold uppercase text-muted">Cheques rech.</p><p class="text-sm font-extrabold {{ $p['cheques_rechazados'] > 0 ? 'text-red-600' : 'text-ink' }}">{{ $p['cheques_rechazados'] }}</p></div>
                                <div><p class="text-[10px] font-semibold uppercase text-muted">Devoluciones</p><p class="text-sm font-extrabold {{ $p['devoluciones'] > 0 ? 'text-amber-600' : 'text-ink' }}">{{ $p['devoluciones'] }}</p></div>
                            </div>
                            @if ($p['cheques_rechazados'] > 0)
                                <p class="mt-2 flex items-center gap-1 rounded-lg bg-red-50 px-2 py-1.5 text-xs font-semibold text-red-700">
                                    <span class="material-symbols-outlined text-[16px]">block</span> Tiene {{ $p['cheques_rechazados'] }} cheque(s) rechazado(s) — mal pagador.
                                </p>
                            @endif
                        @endif
                    </div>
                @else
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[20px] text-muted">person_search</span>
                        <input type="text" wire:model.live.debounce.300ms="cliBuscar" placeholder="Buscar cliente por nombre o documento..."
                               class="w-full rounded-xl border border-gray-200 bg-white py-3 pl-10 pr-3 text-sm font-medium shadow-soft outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                    </div>
                    @error('cliId') <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p> @enderror

                    @if (! empty($this->clientesEncontrados))
                        <div class="mt-2 space-y-1.5">
                            @foreach ($this->clientesEncontrados as $c)
                                <button wire:click="elegirCliente({{ $c['id'] }})" wire:key="cli-{{ $c['id'] }}"
                                        class="flex w-full items-center justify-between rounded-xl border border-gray-100 bg-white p-2.5 text-left shadow-soft hover:border-brand/40">
                                    <div>
                                        <p class="text-sm font-bold text-ink">{{ $c['nombre'] }}</p>
                                        <p class="text-xs text-muted">{{ $c['doc'] ?: 'Sin documento' }}</p>
                                    </div>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $riesgoColor[$c['riesgo']] ?? 'bg-gray-100 text-gray-600' }}">{{ $c['riesgo'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    @elseif (mb_strlen(trim($cliBuscar)) >= 2 && ! $altaCliente)
                        <p class="mt-2 text-xs text-muted">No encontramos «{{ $cliBuscar }}».
                            <button wire:click="mostrarAltaCliente" class="font-bold text-brand">Dar de alta</button>
                        </p>
                    @endif

                    @if ($altaCliente)
                        @php $personaDoc = in_array($ncTipoDoc, ['DNI', 'CUIL']); @endphp
                        <div class="mt-3 space-y-2 rounded-2xl border border-dashed border-brand/40 bg-brand-soft/30 p-3">
                            <p class="text-xs font-bold text-brand">Nuevo cliente (nace pendiente de aprobación)</p>

                            <div>
                                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Nombre <span class="text-red-500">*</span></label>
                                <input type="text" wire:model="ncNombre" placeholder="Nombre y apellido" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
                                @error('ncNombre') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Documento <span class="text-red-500">*</span></label>
                                @php $de = $this->docEstado; @endphp
                                <div class="flex gap-2">
                                    <select wire:model.live="ncTipoDoc" class="rounded-lg border border-gray-200 py-2 pl-3 pr-8 text-sm outline-none focus:border-brand"><option>CUIT</option><option>CUIL</option><option>DNI</option></select>
                                    <input type="text" wire:model.live.debounce.300ms="ncDoc" inputmode="numeric"
                                           placeholder="{{ $ncTipoDoc === 'DNI' ? 'Número de DNI' : 'XX-XXXXXXXX-X' }}"
                                           class="flex-1 rounded-lg border px-3 py-2 text-sm outline-none focus:border-brand {{ $de['estado'] === 'invalido' ? 'border-red-300' : ($de['estado'] === 'valido' ? 'border-green-300' : 'border-gray-200') }}" />
                                </div>
                                @error('ncDoc')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @else
                                    @if ($de['estado'] === 'valido')
                                        <p class="mt-1 flex items-center gap-1 text-[11px] font-semibold text-green-600"><span class="material-symbols-outlined text-[14px]">check_circle</span>{{ $de['msg'] }}</p>
                                    @elseif ($de['estado'] === 'invalido')
                                        <p class="mt-1 flex items-center gap-1 text-[11px] font-semibold text-red-600"><span class="material-symbols-outlined text-[14px]">error</span>{{ $de['msg'] }}</p>
                                    @elseif ($de['estado'] === 'incompleto')
                                        <p class="mt-1 text-[11px] text-muted">{{ $de['msg'] }}</p>
                                    @endif
                                @enderror
                            </div>

                            <div>
                                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">
                                    Fecha de nacimiento @if ($personaDoc) <span class="text-red-500">*</span> @else <span class="font-medium normal-case text-muted/70">(opcional)</span> @endif
                                </label>
                                <input type="date" wire:model="ncFechaNac" max="{{ now()->subYears(18)->toDateString() }}" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
                                @error('ncFechaNac') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Email <span class="font-medium normal-case text-muted/70">(opcional)</span></label>
                                <input type="email" wire:model="ncEmail" placeholder="correo@ejemplo.com" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
                                @error('ncEmail') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-muted">Teléfono <span class="font-medium normal-case text-muted/70">(opcional)</span></label>
                                <input type="text" wire:model="ncTel" placeholder="Teléfono de contacto" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
                            </div>
                            <button type="button" wire:click="guardarClienteNuevo" class="w-full rounded-lg bg-brand py-2 text-sm font-bold text-white hover:bg-brand-dark">Guardar cliente</button>
                        </div>
                    @endif
                @endif

            {{-- ============================ PASO 2: ARTÍCULOS ============================ --}}
            @elseif ($paso === 2)
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-extrabold text-ink">Artículos</h2>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-muted">Sucursal</span>
                        <select wire:model.live="vLocal" class="rounded-lg border border-gray-200 px-2 py-1.5 text-sm outline-none focus:border-brand">
                            @foreach ($locales as $l)
                                <option value="{{ $l }}">{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="space-y-3">
                    @foreach ($items as $i => $it)
                        <div wire:key="it-{{ $i }}" class="rounded-xl border border-gray-100 bg-white p-3 shadow-soft">
                            <div class="relative">
                                <input type="text" wire:model.live.debounce.300ms="items.{{ $i }}.desc" placeholder="Buscar producto por nombre · código · proveedor..."
                                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
                                @if ($buscandoEn === $i && ! empty($this->resultados))
                                    <div class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-xl">
                                        @foreach ($this->resultados as $r)
                                            <button type="button" wire:click="elegirProducto({{ $i }}, {{ $r['id'] }})" wire:key="res-{{ $i }}-{{ $r['id'] }}"
                                                    class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-brand-soft/50">
                                                <span><span class="font-bold text-ink">{{ $r['nom'] }}</span> <span class="text-xs text-muted">· {{ $r['cod'] }} · {{ $r['prov'] }}</span></span>
                                                <span class="tabular text-xs font-bold text-graphite">${{ number_format($r['precio'], 2, ',', '.') }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            @error("items.{$i}.producto_id") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                            <div class="mt-2 flex flex-wrap items-center gap-3">
                                <div class="flex items-center gap-1">
                                    <span class="text-xs text-muted">Cant.</span>
                                    <input type="number" min="1" wire:model="items.{{ $i }}.cant" class="w-16 rounded-lg border border-gray-200 px-2 py-1.5 text-sm outline-none focus:border-brand" />
                                </div>
                                <div class="flex items-center gap-1">
                                    <span class="text-xs text-muted">Precio</span>
                                    <input type="number" step="0.01" wire:model="items.{{ $i }}.precio" class="w-28 rounded-lg border border-gray-200 px-2 py-1.5 text-sm outline-none focus:border-brand" />
                                </div>
                                @if (! empty($it['sugerido']))
                                    <span class="flex items-center gap-1 rounded-full bg-brand-soft px-2 py-0.5 text-[11px] font-bold text-brand"><span class="material-symbols-outlined text-[14px]">auto_awesome</span> Sugerido</span>
                                @endif
                                <span class="ml-auto tabular text-sm font-extrabold text-ink">${{ number_format((float) ($it['cant'] ?: 0) * (float) ($it['precio'] ?: 0), 2, ',', '.') }}</span>
                                <button wire:click="quitarItem({{ $i }})" class="text-gray-300 hover:text-red-500"><span class="material-symbols-outlined text-[20px]">delete</span></button>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if (! empty($this->sugerencias))
                    <div class="mt-3 rounded-xl border border-brand/30 bg-brand-soft/40 p-3">
                        <div class="mb-2 flex items-center gap-1.5 text-xs font-extrabold uppercase tracking-wide text-brand">
                            <span class="material-symbols-outlined text-[18px]">lightbulb</span> También se suele llevar
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($this->sugerencias as $s)
                                <button type="button" wire:click="agregarSugerido({{ $s['id'] }})" wire:key="sug-{{ $s['id'] }}"
                                        class="flex items-center gap-2 rounded-lg border border-brand/30 bg-white px-3 py-2 text-left text-sm shadow-soft transition hover:border-brand hover:bg-brand-soft/60">
                                    <span class="material-symbols-outlined text-[18px] text-brand">add_circle</span>
                                    <span>
                                        <span class="block font-bold text-ink">{{ $s['nom'] }}</span>
                                        <span class="block text-[11px] text-muted">{{ $s['origen_label'] }} · ${{ number_format($s['precio'], 2, ',', '.') }}</span>
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                <button wire:click="agregarItem" class="mt-3 flex items-center gap-1 text-sm font-bold text-brand hover:text-brand-dark">
                    <span class="material-symbols-outlined text-[20px]">add</span> Agregar otro producto
                </button>

                <div class="mt-4 flex items-center justify-between rounded-xl bg-anthracite px-4 py-3 text-white">
                    <span class="text-sm font-semibold">Total venta</span>
                    <span class="text-lg font-extrabold">${{ number_format($this->total, 2, ',', '.') }}</span>
                </div>

            {{-- ============================ PASO 3: PLAN ============================ --}}
            @elseif ($paso === 3)
                <h2 class="mb-3 text-base font-extrabold text-ink">Plan comercial</h2>
                <div class="space-y-1.5">
                    @foreach ($planes as $cod => $pl)
                        <label wire:key="pl-{{ $cod }}" class="flex cursor-pointer items-center justify-between rounded-xl border bg-white p-3 shadow-soft transition {{ $planCodigo === $cod ? 'border-brand ring-2 ring-brand/20' : 'border-gray-100' }}">
                            <div class="flex items-center gap-2">
                                <input type="radio" wire:model.live="planCodigo" value="{{ $cod }}" class="accent-brand" />
                                <span class="text-sm font-bold text-ink">{{ $pl['nombre'] }}</span>
                            </div>
                            @if ($pl['modalidad'] !== 'contado')
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold uppercase text-graphite">{{ $pl['modalidad'] }}</span>
                            @endif
                        </label>
                    @endforeach
                </div>

                @if ($esCredito)
                    <div class="mt-3 space-y-3 rounded-2xl border border-gray-100 bg-white p-4 shadow-soft">
                        <p class="flex items-center gap-1 text-[11px] font-semibold text-amber-600">
                            <span class="material-symbols-outlined text-[14px]">info</span> Tasas provisionales — a confirmar con el cliente.
                        </p>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-muted">Anticipo mínimo ({{ $planes[$planCodigo]['anticipo_pct'] }}%)</span>
                            <span class="font-bold text-ink">${{ number_format($calc['anticipo_min'], 2, ',', '.') }}</span>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-graphite">Anticipo entregado</label>
                            <input type="number" step="0.01" wire:model.live.debounce.500ms="anticipo" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
                            @error('anticipo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-muted">Saldo a financiar</span>
                            <span class="font-bold text-ink">${{ number_format($calc['saldo'], 2, ',', '.') }}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-graphite">Plazo ({{ $unidad }})</label>
                                <input type="number" wire:model.live.debounce.500ms="plazo" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
                                @error('plazo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-graphite">Cuota pactada</label>
                                <input type="number" step="0.01" wire:model.live.debounce.500ms="cuota" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
                                @error('cuota') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <p class="text-[11px] text-muted">Cuota mínima sugerida: <span class="font-bold text-graphite">${{ number_format($calc['cuota_min'], 2, ',', '.') }}</span> · Total financiado: <span class="font-bold text-graphite">${{ number_format($calc['total_financiado'], 2, ',', '.') }}</span></p>

                        <div class="border-t border-gray-100 pt-3">
                            <label class="mb-1 flex items-center gap-1 text-xs font-semibold text-graphite">
                                <span class="material-symbols-outlined text-[14px] text-brand">event</span>
                                Vencimiento de la 1ª cuota
                            </label>
                            <input type="date" wire:model.live="fechaPrimeraCuota" min="{{ now()->toDateString() }}" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
                            @error('fechaPrimeraCuota') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            <p class="mt-1 text-[11px] text-muted">Las cuotas siguientes caen {{ $unidad === 'semanas' ? 'una por semana' : 'una por día' }} a partir de esta fecha. Se puede empezar más adelante (ej. la semana que viene).</p>
                        </div>
                    </div>
                @endif

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-graphite">Medio de pago {{ $esCredito ? 'del anticipo' : '' }}</label>
                        <select wire:model.live="medioAnticipo" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand">
                            @foreach (\App\Livewire\Ventas\Nueva::MEDIOS as $m)
                                <option>{{ $m }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($esCredito)
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-graphite">Zona de cobranza</label>
                            <select wire:model.live="zonaId" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand">
                                <option value="">— elegí una zona —</option>
                                @foreach ($zonas as $z)
                                    <option value="{{ $z->id }}">{{ $z->nombre }}</option>
                                @endforeach
                            </select>
                            @if ($zonas->isEmpty())
                                <p class="mt-1 text-[11px] text-amber-600">No hay zonas cargadas. Definilas en Configuración → Zonas de cobranza.</p>
                            @endif
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-semibold text-graphite">Cobrador asignado</label>
                            <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
                                <span class="material-symbols-outlined text-[18px] {{ $cobrador ? 'text-brand' : 'text-muted' }}">person_pin_circle</span>
                                <span class="{{ $cobrador ? 'font-semibold text-ink' : 'text-muted' }}">{{ $cobrador ?: 'Se completa al elegir la zona' }}</span>
                            </div>
                        </div>
                    @endif
                </div>

                @if ($medioAnticipo === 'Cheque')
                    <div class="mt-3 grid grid-cols-1 gap-3 rounded-xl border border-gray-100 bg-gray-50 p-3 sm:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-graphite">N° de cheque</label>
                            <input type="text" wire:model="chqNumero" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
                            @error('chqNumero') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-graphite">Banco</label>
                            <input type="text" wire:model="chqBanco" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-graphite">Vencimiento</label>
                            <input type="date" wire:model="chqVencimiento" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand" />
                            @error('chqVencimiento') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <p class="text-[11px] text-muted sm:col-span-3">El cheque entra "en cartera" (pendiente); el depósito se calcula a vencimiento + 1 día hábil.</p>
                    </div>
                @endif

            {{-- ============================ PASO 4: CONFIRMAR ============================ --}}
            @elseif ($paso === 4)
                <h2 class="mb-3 text-base font-extrabold text-ink">Confirmar nota de pedido</h2>

                <div class="space-y-3 text-sm">
                    <div class="flex justify-between border-b border-gray-100 pb-2">
                        <span class="text-muted">Cliente</span>
                        <span class="font-bold text-ink">{{ $cliNombre }} <span class="font-normal text-muted">({{ $cliDoc ?: 's/doc' }})</span></span>
                    </div>

                    <div class="border-b border-gray-100 pb-2">
                        <span class="text-muted">Artículos</span>
                        <ul class="mt-1 space-y-1">
                            @foreach ($items as $it)
                                <li class="flex justify-between">
                                    <span class="text-ink">{{ $it['cant'] }}× {{ $it['desc'] ?: '—' }} @if ($it['sugerido']) <span class="text-[10px] font-bold text-brand">(sugerido)</span> @endif</span>
                                    <span class="tabular font-semibold text-graphite">${{ number_format((float) ($it['cant'] ?: 0) * (float) ($it['precio'] ?: 0), 2, ',', '.') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-muted">Plan</span>
                        <span class="font-bold text-ink">{{ $planes[$planCodigo]['nombre'] }}</span>
                    </div>
                    @if ($esCredito)
                        <div class="flex justify-between"><span class="text-muted">Anticipo</span><span class="font-semibold text-graphite">${{ number_format((float) $anticipo, 2, ',', '.') }} ({{ $medioAnticipo }})</span></div>
                        <div class="flex justify-between"><span class="text-muted">Financiación</span><span class="font-semibold text-graphite">{{ $plazo }} {{ $unidad }} · cuota ${{ number_format((float) $cuota, 2, ',', '.') }}</span></div>
                        @if ($fechaPrimeraCuota)
                            <div class="flex justify-between"><span class="text-muted">1ª cuota vence</span><span class="font-semibold text-graphite">{{ \Illuminate\Support\Carbon::parse($fechaPrimeraCuota)->format('d/m/Y') }}</span></div>
                        @endif
                        @if ($cobrador || $zonaCobranza)
                            <div class="flex justify-between"><span class="text-muted">Cobranza</span><span class="font-semibold text-graphite">{{ $cobrador ?: '—' }} {{ $zonaCobranza ? '· ' . $zonaCobranza : '' }}</span></div>
                        @endif
                    @else
                        <div class="flex justify-between"><span class="text-muted">Medio de pago</span><span class="font-semibold text-graphite">{{ $medioAnticipo }}</span></div>
                    @endif

                    <div class="flex items-center justify-between rounded-xl bg-anthracite px-4 py-3 text-white">
                        <span class="text-sm font-semibold">Total venta</span>
                        <span class="text-lg font-extrabold">${{ number_format($this->total, 2, ',', '.') }}</span>
                    </div>
                </div>
            @endif

            {{-- ============================ NAVEGACIÓN ============================ --}}
            <div class="mt-6 flex items-center justify-between border-t border-gray-100 pt-4">
                <button wire:click="atras" @disabled($paso === 1)
                        class="flex items-center gap-1 rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold text-graphite transition hover:bg-gray-50 disabled:opacity-40">
                    <span class="material-symbols-outlined text-[18px]">arrow_back</span> Atrás
                </button>

                @if ($paso < 4)
                    <button wire:click="siguiente" class="flex items-center gap-1 rounded-lg bg-brand px-5 py-2 text-sm font-bold text-white transition hover:bg-brand-dark">
                        Siguiente <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                    </button>
                @else
                    <button wire:click="confirmar" class="flex items-center gap-2 rounded-lg bg-brand px-5 py-2 text-sm font-bold text-white transition hover:bg-brand-dark">
                        <span class="material-symbols-outlined text-[18px]">check_circle</span> Confirmar nota de pedido
                    </button>
                @endif
            </div>
        </div>
    </x-panel>
</div>
