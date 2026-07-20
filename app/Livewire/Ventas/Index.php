<?php

namespace App\Livewire\Ventas;

use App\Livewire\Concerns\AutorizaPermisos;
use App\Models\ActivityLog;
use App\Models\Cliente;
use App\Models\Cuota;
use App\Models\Local;
use App\Models\MovimientoCliente;
use App\Models\Producto;
use App\Models\StockLocal;
use App\Models\UnidadTrazable;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Support\PlanesCredito;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Ventas — E.Comercial')]
class Index extends Component
{
    use AutorizaPermisos;

    #[Url(as: 'sub')]
    public string $sub = 'mis';          // mis (propias) | todas (requiere aprobar_ventas)

    public string $buscar = '';
    public string $estado = 'todos';
    public string $local = 'todos';
    public ?string $mensaje = null;

    #[Url]
    public ?string $highlight = null;

    // Detalle de ítems de una venta (ver productos cargados).
    public ?int $detalleId = null;

    // ===== Modal entrega (códigos de trazabilidad) =====
    public bool $modalEntrega = false;
    public ?int $entregaVentaId = null;
    public ?string $entregaNumero = null;
    public array $entCodigos = [];      // [{producto_id, desc, codigo}]

    // ===== Modal rechazo (motivo obligatorio) =====
    public bool $modalRechazo = false;
    public ?int $rechazarId = null;
    public string $motivoRechazo = '';

    // ===== Modal nueva venta =====
    public bool $modal = false;
    public string $vLocal = 'Local A';
    public string $vMedioPago = 'Contado';
    public array $vItems = [];
    public ?int $buscandoEn = null;

    // Cliente
    public string $vClienteBuscar = '';
    public ?int $vClienteId = null;
    public string $vClienteNombre = '';
    public string $vClienteDoc = '';
    public string $vClienteRiesgo = 'bajo';
    public bool $vClienteNuevo = false;
    public bool $buscandoCliente = false;

    // Alta de cliente nuevo
    public bool $altaCliente = false;
    public string $ncNombre = '';
    public string $ncTipoDoc = 'CUIT';
    public string $ncDoc = '';
    public string $ncTel = '';

    public const MEDIOS = ['Contado', 'Transferencia', 'Efectivo', 'Cheque', 'Cuenta corriente', 'Pago diario', 'Pago semanal'];
    public const MEDIOS_CREDITO = ['Cuenta corriente', 'Pago diario', 'Pago semanal'];

    public function mount(): void
    {
        // El alta ahora es el wizard de página completa (Nota de pedido).
        if (request()->boolean('nuevo') && $this->puede('crear_venta')) {
            $this->redirectRoute('ventas.nueva', navigate: true);
        }
    }

    // ===== Detalle de ítems (ver productos cargados en una venta) =====
    public function verDetalle(int $id): void
    {
        $this->detalleId = $id;
    }

    public function cerrarDetalle(): void
    {
        $this->detalleId = null;
    }

    private function locales(): array
    {
        return Local::where('activo', true)->orderBy('id')->pluck('nombre')->all();
    }

    private function localId(): ?int
    {
        return Local::where('nombre', $this->vLocal)->value('id');
    }

    // ===== Cliente: buscar / elegir / alta =====
    public function getClientesEncontradosProperty(): array
    {
        $q = trim($this->vClienteBuscar);
        if (mb_strlen($q) < 2) {
            return [];
        }

        return Cliente::where('aprobado', true)
            ->where(fn ($w) => $w->where('nombre', 'like', "%{$q}%")->orWhere('documento', 'like', "%{$q}%"))
            ->orderBy('nombre')->limit(8)->get()
            ->map(fn (Cliente $c) => [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'doc' => trim(($c->tipo_doc ?: '') . ' ' . ($c->documento ?: '')),
                'riesgo' => $c->riesgo,
            ])->all();
    }

    public function updatedVClienteBuscar(): void
    {
        $this->buscandoCliente = true;
        $this->vClienteId = null;
        $this->vClienteNombre = '';
        $this->vClienteDoc = '';
        $this->vClienteNuevo = false;
        $this->altaCliente = false;
    }

    public function elegirCliente(int $id): void
    {
        $c = Cliente::find($id);
        if (! $c) {
            return;
        }
        $this->vClienteId = $c->id;
        $this->vClienteNombre = $c->nombre;
        $this->vClienteDoc = trim(($c->tipo_doc ?: '') . ' ' . ($c->documento ?: ''));
        $this->vClienteRiesgo = $c->riesgo;
        $this->vClienteNuevo = ! $c->aprobado;
        $this->vClienteBuscar = $c->nombre;
        $this->buscandoCliente = false;
        $this->altaCliente = false;
    }

    public function mostrarAltaCliente(): void
    {
        $this->autorizar('crear_venta');
        $this->altaCliente = true;
        $this->buscandoCliente = false;
        $this->ncNombre = $this->vClienteBuscar;
        $this->ncTipoDoc = 'CUIT';
        $this->ncDoc = '';
        $this->ncTel = '';
        $this->resetValidation();
    }

    public function guardarClienteNuevo(): void
    {
        $this->autorizar('crear_venta');
        $this->validate([
            'ncNombre' => 'required|min:2',
            'ncTipoDoc' => 'required|in:CUIT,CUIL,DNI',
            'ncDoc' => 'required',
        ], attributes: ['ncNombre' => 'nombre', 'ncTipoDoc' => 'tipo de documento', 'ncDoc' => 'documento']);

        $c = Cliente::create([
            'nombre' => $this->ncNombre,
            'tipo_doc' => $this->ncTipoDoc,
            'documento' => $this->ncDoc,
            'telefono' => $this->ncTel ?: null,
            'riesgo' => 'bajo',
            'activo' => true,
            'aprobado' => false,
        ]);

        ActivityLog::create([
            'tipo' => 'alerta',
            'titulo' => 'Cliente nuevo pendiente de aprobación',
            'detalle' => "«{$c->nombre}» dado de alta por " . (auth()->user()?->name ?? 'un vendedor') . ' durante una venta.',
            'usuario_id' => auth()->id(),
        ]);

        $this->vClienteId = $c->id;
        $this->vClienteNombre = $c->nombre;
        $this->vClienteDoc = trim($c->tipo_doc . ' ' . $c->documento);
        $this->vClienteRiesgo = 'bajo';
        $this->vClienteNuevo = true;
        $this->vClienteBuscar = $c->nombre;
        $this->altaCliente = false;
        $this->buscandoCliente = false;
    }

    // ===== Buscador de productos (renglones) =====
    public function getResultadosProperty(): array
    {
        if ($this->buscandoEn === null) {
            return [];
        }
        $q = trim($this->vItems[$this->buscandoEn]['desc'] ?? '');
        if (mb_strlen($q) < 2) {
            return [];
        }
        $localId = $this->localId();

        return Producto::query()
            ->with(['proveedor:id,nombre', 'stock'])
            ->where(fn ($w) => $w
                ->where('nombre', 'like', "%{$q}%")
                ->orWhere('codigo', 'like', "%{$q}%")
                ->orWhereHas('proveedor', fn ($p) => $p->where('nombre', 'like', "%{$q}%")))
            ->limit(8)->get()
            ->map(fn (Producto $p) => [
                'id' => $p->id,
                'cod' => $p->codigo,
                'nom' => $p->nombre,
                'prov' => $p->proveedor?->nombre ?? '—',
                'precio' => (float) (($localId ? $p->stockEn($localId)?->precio_venta : null) ?? 0),
            ])->all();
    }

    public function updatedVItems($value, $key): void
    {
        [$i, $campo] = array_pad(explode('.', (string) $key), 2, null);
        if ($campo === 'desc') {
            $this->buscandoEn = (int) $i;
        }
    }

    public function elegirProducto(int $i, int $productoId): void
    {
        $localId = $this->localId();
        $p = Producto::with('stock')->find($productoId);
        if (! $p) {
            return;
        }
        $this->vItems[$i]['producto_id'] = $p->id;
        $this->vItems[$i]['cod'] = $p->codigo;
        $this->vItems[$i]['desc'] = $p->nombre;
        $precio = $localId ? $p->stockEn($localId)?->precio_venta : null;
        if ($precio !== null) {
            $this->vItems[$i]['precio'] = (float) $precio;
        }
        $this->buscandoEn = null;
    }

    public function cerrarBusqueda(): void
    {
        $this->buscandoEn = null;
    }

    // ===== Alta de venta =====
    public function nuevaVenta(): void
    {
        $this->autorizar('crear_venta');
        $this->reset([
            'vMedioPago', 'buscandoEn',
            'vClienteBuscar', 'vClienteId', 'vClienteNombre', 'vClienteDoc', 'vClienteRiesgo', 'vClienteNuevo',
            'buscandoCliente', 'altaCliente', 'ncNombre', 'ncTipoDoc', 'ncDoc', 'ncTel',
        ]);
        $this->vLocal = $this->locales()[0] ?? 'Local A';
        $this->vMedioPago = 'Contado';
        $this->vClienteRiesgo = 'bajo';
        $this->ncTipoDoc = 'CUIT';
        $this->vItems = [['producto_id' => null, 'cod' => '', 'desc' => '', 'cant' => 1, 'precio' => '']];
        $this->resetValidation();
        $this->modal = true;
    }

    public function agregarItem(): void
    {
        $this->vItems[] = ['producto_id' => null, 'cod' => '', 'desc' => '', 'cant' => 1, 'precio' => ''];
    }

    public function quitarItem(int $i): void
    {
        unset($this->vItems[$i]);
        $this->vItems = array_values($this->vItems);
        if (empty($this->vItems)) {
            $this->vItems = [['producto_id' => null, 'cod' => '', 'desc' => '', 'cant' => 1, 'precio' => '']];
        }
        $this->buscandoEn = null;
    }

    public function getTotalVentaProperty(): float
    {
        return array_sum(array_map(
            fn ($it) => (float) ($it['cant'] ?: 0) * (float) ($it['precio'] ?: 0),
            $this->vItems
        ));
    }

    public function guardarVenta(): void
    {
        $this->autorizar('crear_venta');
        $this->validate([
            'vClienteId' => 'required',
            'vItems' => 'required|array|min:1',
            'vItems.*.producto_id' => 'required',
            'vItems.*.cant' => 'required|numeric|min:1',
            'vItems.*.precio' => 'required|numeric|min:0',
        ], messages: [
            'vClienteId.required' => 'Elegí un cliente existente o dá de alta uno nuevo.',
            'vItems.*.producto_id.required' => 'Elegí un producto del buscador en cada renglón.',
        ], attributes: [
            'vItems.*.cant' => 'cantidad', 'vItems.*.precio' => 'precio',
        ]);

        $maxNum = (int) (Venta::selectRaw("MAX(CAST(REGEXP_REPLACE(numero, '[^0-9]', '') AS UNSIGNED)) as n")->value('n') ?? 1042);
        $num = 'FAC-' . ($maxNum + 1);
        $credito = in_array($this->vMedioPago, self::MEDIOS_CREDITO, true);

        DB::transaction(function () use ($num, $credito) {
            $venta = Venta::create([
                'numero' => $num,
                'local_id' => $this->localId(),
                'vendedor_id' => auth()->id(),
                'cliente_id' => $this->vClienteId,
                'cliente_nombre' => $this->vClienteNombre,
                'medio_pago' => $this->vMedioPago,
                'credito' => $credito,
                'fecha' => now(),
                'total' => $this->totalVenta,
                'estado' => 'pendiente',
            ]);
            foreach ($this->vItems as $it) {
                VentaItem::create([
                    'venta_id' => $venta->id,
                    'producto_id' => $it['producto_id'],
                    'cantidad' => (int) $it['cant'],
                    'precio_unitario' => (float) $it['precio'],
                ]);
            }
        });

        $this->modal = false;
        if ($this->vClienteNuevo) {
            $this->mensaje = "Venta {$num} creada. El cliente «{$this->vClienteNombre}» es nuevo y requiere aprobación del administrador antes de concretarse.";
        } elseif ($credito) {
            $this->mensaje = "Venta {$num} creada — solicita crédito ({$this->vMedioPago}); requiere aprobación.";
        } else {
            $this->mensaje = "Venta {$num} creada (pendiente de aprobación).";
        }
    }

    public function aprobar(int $id): void
    {
        $this->autorizar('aprobar_ventas');

        $venta = Venta::with(['items', 'cliente'])->find($id);
        if (! $venta || $venta->estado !== 'pendiente') {
            return;
        }

        $extra = '';
        DB::transaction(function () use ($venta, &$extra) {
            // Descontar stock del local de la venta.
            foreach ($venta->items as $it) {
                $sl = StockLocal::where('producto_id', $it->producto_id)->where('local_id', $venta->local_id)->first();
                if ($sl) {
                    $sl->decrement('cantidad', $it->cantidad);
                }
            }

            // Aprobar cliente nuevo, si corresponde.
            if ($venta->cliente && ! $venta->cliente->aprobado) {
                $venta->cliente->update(['aprobado' => true]);
                $extra = " Cliente «{$venta->cliente->nombre}» habilitado.";
            }

            // Si es a crédito, impacta la cuenta corriente del cliente.
            if ($venta->credito && $venta->cliente_id) {
                if ($venta->plan_codigo && PlanesCredito::esCredito($venta->plan_codigo)) {
                    // Plan de financiación: la deuda es el SALDO FINANCIADO (saldo + interés);
                    // el anticipo es cobro, no deuda. Se genera el cronograma de cuotas.
                    $calc = PlanesCredito::calcular($venta->plan_codigo, (float) $venta->total, $venta->plazo);
                    // R4: la 1ª cuota vence en la fecha elegida en la Nota de Pedido;
                    // si no se fijó, cae un período después de aprobar (comportamiento previo).
                    $primeraCuota = $venta->fecha_primera_cuota
                        ? $venta->fecha_primera_cuota->copy()
                        : PlanesCredito::primeraCuotaPorDefecto($venta->plan_codigo);
                    $cronograma = PlanesCredito::cronograma($calc, $primeraCuota);
                    $tasaMora = PlanesCredito::tasaMoraDiaria($venta->plan_codigo);
                    $n = count($cronograma);

                    MovimientoCliente::create([
                        'cliente_id' => $venta->cliente_id,
                        'tipo' => 'debe',
                        'concepto' => "Venta {$venta->numero} — saldo financiado ({$n} cuotas, {$venta->modalidad})",
                        'monto' => $calc['total_financiado'],
                        'fecha' => now(),
                        'referencia' => $venta->numero,
                    ]);

                    foreach ($cronograma as $c) {
                        Cuota::create([
                            'venta_id' => $venta->id,
                            'cliente_id' => $venta->cliente_id,
                            'numero' => $c['numero'],
                            'fecha_vencimiento' => $c['fecha_vencimiento'],
                            'monto' => $c['monto'],
                            'capital' => $c['capital'],
                            'interes' => $c['interes'],
                            'tasa_mora' => $tasaMora,
                            'estado' => 'pendiente',
                            'cobrador' => $venta->cobrador,
                            'zona' => $venta->zona_cobranza,
                        ]);
                    }
                    $extra .= " Cargada a cuenta corriente ({$n} cuotas).";
                } else {
                    // Sin plan de financiación reconocido: deuda = total (comportamiento previo).
                    MovimientoCliente::create([
                        'cliente_id' => $venta->cliente_id,
                        'tipo' => 'debe',
                        'concepto' => "Venta {$venta->numero} ({$venta->medio_pago})",
                        'monto' => $venta->total,
                        'fecha' => now(),
                        'referencia' => $venta->numero,
                    ]);
                    $extra .= ' Cargada a su cuenta corriente.';
                }
            }

            $venta->update(['estado' => 'aprobada', 'aprobada_por' => auth()->id()]);
        });

        $this->mensaje = "Venta {$venta->numero} aprobada — stock descontado.{$extra}";
    }

    /** Abre el modal de entrega: una fila por unidad a entregar (pide el código de la caja). */
    public function entregar(int $id): void
    {
        $this->autorizar('entregar_venta');
        $venta = Venta::with('items.producto')->find($id);
        if (! $venta || $venta->estado !== 'aprobada' || $venta->entregado_at) {
            return;
        }
        $this->entregaVentaId = $venta->id;
        $this->entregaNumero = $venta->numero;
        $this->entCodigos = [];
        foreach ($venta->items as $it) {
            for ($k = 0; $k < (int) $it->cantidad; $k++) {
                $this->entCodigos[] = [
                    'producto_id' => $it->producto_id,
                    'desc' => $it->producto?->nombre ?? '—',
                    'codigo' => '',
                ];
            }
        }
        $this->resetValidation();
        $this->modalEntrega = true;
    }

    /** Confirma la entrega: valida cada código y marca las unidades como entregadas. */
    public function confirmarEntrega(): void
    {
        $this->autorizar('entregar_venta');
        $venta = Venta::with('items')->find($this->entregaVentaId);
        if (! $venta || $venta->estado !== 'aprobada' || $venta->entregado_at) {
            return;
        }
        $this->resetValidation();

        // 1) Códigos cargados y sin repetir.
        $vistos = [];
        foreach ($this->entCodigos as $i => $row) {
            $cod = trim((string) $row['codigo']);
            if ($cod === '') {
                $this->addError("entCodigos.{$i}.codigo", 'Cargá el código de la caja.');
            } elseif (in_array($cod, $vistos, true)) {
                $this->addError("entCodigos.{$i}.codigo", 'Código repetido.');
            } else {
                $vistos[] = $cod;
            }
        }
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        // 2) Resolver cada código a una unidad válida (en stock, misma sucursal, mismo producto).
        $unidades = [];
        foreach ($this->entCodigos as $i => $row) {
            $u = UnidadTrazable::where('codigo', trim($row['codigo']))->first();
            if (! $u) {
                $this->addError("entCodigos.{$i}.codigo", 'No existe ese código.');
            } elseif ($u->estado !== UnidadTrazable::EN_STOCK) {
                $this->addError("entCodigos.{$i}.codigo", "Esa caja no está disponible (estado {$u->estado}).");
            } elseif ((int) $u->local_id !== (int) $venta->local_id) {
                $this->addError("entCodigos.{$i}.codigo", 'Esa caja está en otra sucursal.');
            } elseif ((int) $u->producto_id !== (int) $row['producto_id']) {
                $this->addError("entCodigos.{$i}.codigo", "El código no corresponde a «{$row['desc']}».");
            } else {
                $unidades[] = $u;
            }
        }
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        DB::transaction(function () use ($venta, $unidades) {
            foreach ($unidades as $u) {
                $u->update(['estado' => UnidadTrazable::ENTREGADO, 'venta_id' => $venta->id, 'entregado_at' => now()]);
                $u->registrar('entrega', $venta->local_id, $venta->numero, 'Cliente ' . ($venta->cliente_nombre ?: '—'));
            }
            $venta->update(['entregado_at' => now()]);
        });

        $this->modalEntrega = false;
        $this->mensaje = "Venta {$venta->numero} entregada — " . count($unidades) . ' caja(s) con trazabilidad registrada.';
    }

    /** Abre el modal pidiendo el motivo (obligatorio) del rechazo. */
    public function rechazar(int $id): void
    {
        $this->autorizar('aprobar_ventas');
        if (! Venta::where('id', $id)->where('estado', 'pendiente')->exists()) {
            return;
        }
        $this->rechazarId = $id;
        $this->motivoRechazo = '';
        $this->resetValidation();
        $this->modalRechazo = true;
    }

    public function confirmarRechazo(): void
    {
        $this->autorizar('aprobar_ventas');
        $this->validate(
            ['motivoRechazo' => 'required|min:3'],
            ['motivoRechazo.required' => 'El motivo del rechazo es obligatorio.', 'motivoRechazo.min' => 'Indicá un motivo más descriptivo.'],
            ['motivoRechazo' => 'motivo'],
        );

        $venta = Venta::where('id', $this->rechazarId)->where('estado', 'pendiente')->first();
        if ($venta) {
            $venta->update(['estado' => 'rechazada', 'aprobada_por' => auth()->id(), 'motivo_rechazo' => $this->motivoRechazo]);
            $this->mensaje = "Venta {$venta->numero} rechazada. Motivo: {$this->motivoRechazo}";
        }

        $this->modalRechazo = false;
        $this->rechazarId = null;
        $this->motivoRechazo = '';
    }

    public function cerrarRechazo(): void
    {
        $this->modalRechazo = false;
        $this->rechazarId = null;
        $this->motivoRechazo = '';
        $this->resetValidation();
    }

    public function limpiar(): void
    {
        $this->reset(['buscar', 'estado', 'local', 'mensaje']);
        $this->estado = 'todos';
        $this->local = 'todos';
    }

    public function render()
    {
        // "Mis ventas" (propias) vs "Todas las ventas" (solo quien puede aprobar).
        $verTodas = $this->sub === 'todas' && $this->puede('aprobar_ventas');
        $soloPropias = ! $verTodas;

        $filas = Venta::with(['vendedor:id,name', 'local:id,nombre', 'cliente:id,nombre,tipo_doc,documento,riesgo,aprobado'])
            ->withCount('items')
            ->when($soloPropias, fn ($q) => $q->where('vendedor_id', auth()->id()))
            ->when($this->buscar !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('numero', 'like', "%{$this->buscar}%")
                ->orWhere('cliente_nombre', 'like', "%{$this->buscar}%")
                ->orWhereHas('vendedor', fn ($v) => $v->where('name', 'like', "%{$this->buscar}%"))))
            ->when($this->estado !== 'todos', fn ($q) => $q->where('estado', $this->estado))
            ->when($this->local !== 'todos', fn ($q) => $q->whereHas('local', fn ($l) => $l->where('nombre', $this->local)))
            ->orderByDesc('id')
            ->get()
            ->map(function (Venta $v) {
                $nombre = $v->vendedor?->name ?? '—';
                $ini = collect(explode(' ', $nombre))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') ?: 'NN';
                $cli = $v->cliente;

                return [
                    'id' => $v->id,
                    'num' => $v->numero,
                    'fecha' => $v->fecha?->format('d/m/Y'),
                    'vend' => $nombre,
                    'ini' => $ini,
                    'vv' => 'brand',
                    'local' => $v->local?->nombre ?? '—',
                    'cliente' => $v->cliente_nombre ?: ($cli?->nombre ?? '—'),
                    'cliente_doc' => $cli ? trim(($cli->tipo_doc ?: '') . ' ' . ($cli->documento ?: '')) : '',
                    'cliente_riesgo' => $cli?->riesgo ?? 'bajo',
                    'cliente_nuevo' => $cli ? ! $cli->aprobado : false,
                    'items' => $v->items_count,
                    'total' => (float) $v->total,
                    'medio' => $v->medio_pago,
                    'credito' => (bool) $v->credito,
                    'estado' => $v->estado,
                    'entregado' => (bool) $v->entregado_at,
                    'motivo_rechazo' => $v->motivo_rechazo,
                ];
            });

        // Las estadísticas también se acotan a lo propio para el vendedor.
        $scoped = fn () => Venta::when($soloPropias, fn ($q) => $q->where('vendedor_id', auth()->id()));

        // Detalle de ítems de la venta seleccionada (verificación de productos cargados).
        $detalle = null;
        if ($this->detalleId) {
            $v = Venta::with(['items.producto:id,nombre,codigo'])->find($this->detalleId);
            if ($v && (! $soloPropias || $v->vendedor_id === auth()->id())) {
                $detalle = [
                    'num' => $v->numero,
                    'items' => $v->items->map(fn ($it) => [
                        'nom' => $it->producto?->nombre ?? '—',
                        'cod' => $it->producto?->codigo ?? '',
                        'cant' => (int) $it->cantidad,
                        'precio' => (float) $it->precio_unitario,
                        'sugerido' => (bool) $it->sugerido,
                    ])->all(),
                ];
            }
        }

        return view('livewire.ventas.index', [
            'filas' => $filas,
            'verTodas' => $verTodas,
            'locales' => $this->locales(),
            'detalle' => $detalle,
            'stats' => [
                'pendientes' => $scoped()->where('estado', 'pendiente')->count(),
                'aprobadas' => $scoped()->where('estado', 'aprobada')->count(),
                'monto_aprobado' => (float) $scoped()->where('estado', 'aprobada')->sum('total'),
            ],
        ]);
    }
}
