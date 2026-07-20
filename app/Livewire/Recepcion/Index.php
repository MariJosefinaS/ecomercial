<?php

namespace App\Livewire\Recepcion;

use App\Livewire\Concerns\AutorizaPermisos;
use App\Models\ActivityLog;
use App\Models\Compra;
use App\Models\FacturaEscaneo;
use App\Models\Local;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Remito;
use App\Models\RemitoItem;
use App\Models\StockLocal;
use App\Models\UnidadTrazable;
use App\Support\Costeo;
use App\Support\FacturaScanner;
use App\Support\MatcheoFactura;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Recepción de mercadería POR REMITO (Encargado de Depósito).
 *
 * Una factura/compra puede entregarse en varios remitos (parciales y/o a
 * distintas sucursales). Acá el encargado registra UN remito: elige la
 * sucursal destino, escanea el remito (lo que llegó, sin precios) que se
 * matchea contra la factura, marca lo que llegó/defectuoso, y confirma.
 * El stock impacta la sucursal del remito; el saldo no entregado queda
 * pendiente para un remito posterior.
 */
#[Layout('components.layouts.app')]
#[Title('Recepción de mercadería — E.Comercial')]
class Index extends Component
{
    use AutorizaPermisos;
    use WithFileUploads;

    public ?int $sel = null;              // compra (factura) que se está recibiendo

    #[Url]
    public ?string $highlight = null;     // nº de compra a resaltar (desde la campana de avisos)

    public ?int $localId = null;          // sucursal destino del remito
    public string $numeroRemito = '';
    public ?string $mensaje = null;
    public ?int $ultimoRemito = null;     // para imprimir etiquetas tras confirmar

    /** @var array<int,array<string,mixed>> */
    public array $rItems = [];

    // Escaneo del remito (Fase B).
    public $factura = null;               // archivo subido (foto/PDF del remito)
    public ?string $scanMsg = null;
    public ?string $scanError = null;
    public ?int $escaneoId = null;
    /** Líneas del remito que NO matchearon ningún ítem de la factura. */
    public array $extras = [];

    public function abrir(int $compraId): void
    {
        $this->autorizar('ver_recepcion');
        $compra = Compra::with(['items.producto', 'items.remitoItems', 'proveedor'])->find($compraId);
        if (! $compra || ! $compra->tienePendiente()) {
            return;
        }

        $this->sel = $compra->id;
        // La sucursal viene de la asignada al usuario (no se puede cambiar);
        // si el usuario no tiene sucursal fija, usa la primaria de la compra.
        $this->localId = auth()->user()?->local_id ?? $compra->local_id;
        $this->numeroRemito = '';
        $this->mensaje = null;
        $this->ultimoRemito = null;
        $this->reset(['factura', 'scanMsg', 'scanError', 'escaneoId', 'extras']);

        $this->rItems = $compra->items
            ->filter(fn ($it) => $it->pendiente() > 0)
            ->map(function ($it) use ($compra) {
                $pend = $it->pendiente();

                return [
                    'item_id' => $it->id,
                    'producto_id' => $it->producto_id,
                    'cod' => $it->producto?->codigo ?? '—',
                    'sku' => $it->producto?->sku ?? '',
                    'desc' => $it->producto?->nombre ?? '—',
                    'facturado' => (int) $it->cantidad,
                    'yaRecibido' => $it->recibidoTotal(),
                    'pendiente' => $pend,
                    'estado' => 'ok',         // ok | parcial | defectuoso | no_llego
                    'llego' => $pend,         // cuántos llegaron (para "parcial")
                    'defectuosos' => 0,       // cuántos defectuosos (para "defectuoso")
                    'costo' => (string) (float) $it->costo_unitario,  // NETO de la factura
                    'costoDepo' => Costeo::costo((float) $it->costo_unitario, $compra->proveedor, $it->producto?->conceptos),
                    'nota' => '',
                    'match' => null,
                    'sinFactura' => false,
                ];
            })->values()->all();
    }

    public function cerrar(): void
    {
        $this->reset([
            'sel', 'localId', 'numeroRemito', 'rItems',
            'factura', 'scanMsg', 'scanError', 'escaneoId', 'extras',
        ]);
    }

    /** Al cambiar el estado del renglón, limpia el campo que no corresponde. */
    public function updated(string $property): void
    {
        if (preg_match('/^rItems\.(\d+)\.estado$/', $property, $m)) {
            $i = (int) $m[1];
            $est = $this->rItems[$i]['estado'] ?? 'ok';
            if ($est !== 'parcial') {
                $this->rItems[$i]['llego'] = $est === 'no_llego' ? 0 : ($this->rItems[$i]['pendiente'] ?? 0);
            }
            if ($est !== 'defectuoso') {
                $this->rItems[$i]['defectuosos'] = 0;
            }
            $this->resetValidation("rItems.{$i}.nota");
        }
    }

    /**
     * Escanea el remito subido: visión → matching contra la factura →
     * superpone lo que llegó por renglón. NO suma stock acá.
     */
    public function escanearFactura(): void
    {
        $this->autorizar('recepcionar');
        $this->validate(
            ['factura' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:15360'],
            [],
            ['factura' => 'remito'],
        );

        $compra = Compra::with('items.producto')->find($this->sel);
        if (! $compra) {
            return;
        }

        $this->scanMsg = $this->scanError = null;
        $path = $this->factura->store('facturas', 'public');
        $abs = storage_path('app/public/' . $path);

        $escaneo = FacturaEscaneo::create([
            'compra_id' => $compra->id,
            'archivo' => $path,
            'modelo' => config('services.vision.provider') === 'google'
                ? config('services.google_ai.model') : config('services.openrouter.model'),
            'estado' => 'procesando',
            'creado_por' => auth()->id(),
        ]);

        try {
            $ext = app(FacturaScanner::class)->extraer($abs, $this->factura->getMimeType());
            $lineas = MatcheoFactura::resolver($compra, $ext['lineas']);

            $escaneo->update(['estado' => 'listo', 'cabecera' => $ext['cabecera'], 'lineas' => $lineas]);
            $this->escaneoId = $escaneo->id;
            $this->aplicarEscaneo($ext['cabecera'], $lineas);
            $this->reset('factura');

            $this->scanMsg = count($this->extras)
                ? 'Remito leído. Hay ' . count($this->extras) . ' producto(s) que no figuran en la factura — revisalos.'
                : 'Remito leído. Revisá lo que llegó y confirmá.';
        } catch (Throwable $e) {
            $escaneo->update(['estado' => 'error', 'error' => $e->getMessage()]);
            $this->scanError = 'No se pudo leer el remito: ' . $e->getMessage();
        }
    }

    /**
     * Vuelca lo extraído del remito sobre rItems (cuánto llegó por renglón) y el
     * nº de remito de la cabecera. Separa los productos que no están en la factura.
     *
     * @param  array<string,mixed>  $cabecera
     * @param  array<int,array<string,mixed>>  $lineas
     */
    private function aplicarEscaneo(array $cabecera, array $lineas): void
    {
        $this->extras = [];
        $porItem = [];

        foreach ($lineas as $l) {
            if (($l['tipo'] ?? 'producto') === 'gasto') {
                continue; // los gastos viven en la factura, no en el remito
            }
            $itemId = data_get($l, 'match.compra_item_id');
            if ($itemId) {
                $porItem[$itemId] = $l;
            } else {
                // Línea del remito que no matcheó la factura: queda como "extra"
                // con estado de trabajo para que el encargado decida qué hacer.
                $cant = max(1, (int) round((float) ($l['cantidad'] ?? 1)));
                $this->extras[] = [
                    'codigo' => $l['codigo'] ?? null,
                    'descripcion' => (string) ($l['descripcion'] ?? ''),
                    'cantidad' => $cant,
                    'costo' => (string) (float) ($l['p_unit'] ?? 0),
                    'candidatos' => array_values((array) data_get($l, 'match.candidatos', [])),
                    'sugerido_id' => data_get($l, 'match.producto_id'),
                    // Estado de trabajo (lo edita el encargado):
                    'destino' => 'ignorar',          // ignorar | item | agregar
                    'item_id' => null,               // renglón de la factura (destino=item)
                    'prod_sel' => '',                // producto_id | 'nuevo' (destino=agregar)
                    'nuevo_nombre' => (string) ($l['descripcion'] ?? ''),
                    'nuevo_codigo' => (string) ($l['codigo'] ?? ''),
                    'nota' => '',
                ];
            }
        }

        foreach ($this->rItems as $i => $r) {
            if (isset($porItem[$r['item_id']])) {
                $l = $porItem[$r['item_id']];
                $llego = (int) round((float) ($l['cantidad'] ?? $r['pendiente']));
                $llego = max(0, min($llego, $r['pendiente']));
                $this->rItems[$i]['match'] = data_get($l, 'match.confianza');
                $this->rItems[$i]['sinFactura'] = false;
                if ($llego >= $r['pendiente']) {
                    $this->rItems[$i]['estado'] = 'ok';
                    $this->rItems[$i]['llego'] = $r['pendiente'];
                } else {
                    $this->rItems[$i]['estado'] = 'parcial';
                    $this->rItems[$i]['llego'] = $llego;
                }
            } else {
                // El renglón de la factura no vino en este remito → queda pendiente.
                $this->rItems[$i]['estado'] = 'no_llego';
                $this->rItems[$i]['llego'] = 0;
                $this->rItems[$i]['sinFactura'] = true;
            }
        }

        // Nº de remito desde la cabecera (campo "remito" del documento).
        $rem = data_get($cabecera, 'remito');
        if ($rem && $this->numeroRemito === '') {
            $this->numeroRemito = (string) $rem;
        }
    }

    /**
     * Confirma el remito: crea Remito + RemitoItems, suma al stock de la sucursal
     * lo recibido en buen estado, actualiza el saldo de la factura y su estado.
     */
    public function confirmarRecepcion(): void
    {
        $this->autorizar('recepcionar');

        $compra = Compra::with('items', 'proveedor')->find($this->sel);
        if (! $compra) {
            return;
        }

        // Si el usuario tiene sucursal asignada, se fuerza (no puede recibir en otra).
        if ($fija = auth()->user()?->local_id) {
            $this->localId = $fija;
        }

        $this->validate(
            ['localId' => 'required|exists:locales,id'],
            [],
            ['localId' => 'sucursal'],
        );

        // Extras "Vincular a un renglón": se pliegan sobre el rItem elegido
        // (corrige un matcheo automático fallido) ANTES de validar los renglones.
        foreach ($this->extras as $e => $ex) {
            if (($ex['destino'] ?? 'ignorar') !== 'item') {
                continue;
            }
            $itemId = (int) ($ex['item_id'] ?? 0);
            $idx = collect($this->rItems)->search(fn ($r) => (int) $r['item_id'] === $itemId);
            if ($idx === false) {
                $this->addError("extras.{$e}.item_id", 'Elegí a qué renglón de la factura corresponde.');

                continue;
            }
            $this->sumarRecibidoARenglon((int) $idx, max(1, (int) $ex['cantidad']));
        }

        // Líneas a AGREGAR a la factura (producto del catálogo o alta nueva).
        $nuevas = [];
        foreach ($this->extras as $e => $ex) {
            if (($ex['destino'] ?? 'ignorar') !== 'agregar') {
                continue;
            }
            $cant = (int) ($ex['cantidad'] ?? 0);
            if ($cant < 1) {
                $this->addError("extras.{$e}.cantidad", 'Indicá la cantidad recibida (al menos 1).');

                continue;
            }
            $sel = (string) ($ex['prod_sel'] ?? '');
            if ($sel === '') {
                $this->addError("extras.{$e}.prod_sel", 'Elegí el producto o marcá "Producto nuevo".');

                continue;
            }
            $crear = $sel === 'nuevo';
            $nombre = trim((string) ($ex['nuevo_nombre'] ?? ''));
            $codigo = trim((string) ($ex['nuevo_codigo'] ?? '')) ?: null;
            if ($crear && $nombre === '') {
                $this->addError("extras.{$e}.nuevo_nombre", 'El producto nuevo necesita un nombre.');

                continue;
            }
            // El código es único: si ya existe, avisar (no romper con un 500).
            if ($crear && $codigo && Producto::where('codigo', $codigo)->exists()) {
                $this->addError("extras.{$e}.nuevo_codigo", 'Ese código ya existe en el catálogo. Usá otro o dejalo vacío.');

                continue;
            }
            $nuevas[] = [
                'crear' => $crear,
                'producto_id' => $crear ? null : (int) $sel,
                'nombre' => $nombre,
                'codigo' => $codigo,
                'cantidad' => $cant,
                'costo' => (float) ($ex['costo'] ?? 0),
                'nota' => trim((string) ($ex['nota'] ?? '')) ?: null,
            ];
        }

        // Validación por renglón según el estado elegido.
        foreach ($this->rItems as $i => $r) {
            $pend = (int) $r['pendiente'];
            $est = $r['estado'] ?? 'ok';

            if ($est === 'parcial') {
                $llego = (int) $r['llego'];
                if ($llego < 1) {
                    $this->addError("rItems.{$i}.llego", 'Indicá cuántos llegaron (al menos 1).');
                } elseif ($llego > $pend) {
                    $this->addError("rItems.{$i}.llego", "No puede superar lo pendiente ({$pend}).");
                }
            } elseif ($est === 'defectuoso') {
                $def = (int) $r['defectuosos'];
                if ($def < 1) {
                    $this->addError("rItems.{$i}.defectuosos", 'Indicá cuántos defectuosos (al menos 1).');
                } elseif ($def > $pend) {
                    $this->addError("rItems.{$i}.defectuosos", "No puede superar lo pendiente ({$pend}).");
                }
            }
            if ($est !== 'ok' && trim((string) $r['nota']) === '') {
                $this->addError("rItems.{$i}.nota", 'Indicá una observación (qué llegó / qué falla / por qué no llegó).');
            }
        }
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        // Nada que registrar si en este remito no llegó absolutamente nada.
        $algoLlego = count($nuevas) > 0
            || collect($this->rItems)->contains(fn ($r) => $this->recibidaDe($r) > 0);
        if (! $algoLlego) {
            $this->addError('rItems', 'Este remito no registra ninguna entrega. Marcá al menos un renglón como llegado.');

            return;
        }

        $diferencias = [];
        $incidencias = [];
        $remitoId = null;

        DB::transaction(function () use ($compra, $nuevas, &$diferencias, &$incidencias, &$remitoId) {
            $remito = Remito::create([
                'compra_id' => $compra->id,
                'local_id' => $this->localId,
                'numero' => $this->numeroRemito ?: null,
                'estado' => 'recibido',
                'factura_escaneo_id' => $this->escaneoId,
                'recibido_por' => auth()->id(),
                'recibido_at' => now(),
            ]);
            $remitoId = $remito->id;

            // Correlativo del código de trazabilidad: reinicia por proveedor + día +
            // sucursal (Liliana 001..N; NEBA ese día arranca de nuevo en 001).
            $fechaCod = now()->format('ymd');
            $seq = UnidadTrazable::where('proveedor_id', $compra->proveedor_id)
                ->where('local_id', $this->localId)
                ->whereDate('created_at', today())
                ->count();
            $refRemito = $remito->numero ?: ('Remito #' . $remito->id);

            foreach ($this->rItems as $r) {
                $recibida = $this->recibidaDe($r);        // lo que trajo este remito
                $def = $r['estado'] === 'defectuoso' ? (int) $r['defectuosos'] : 0;
                $okQty = max(0, $recibida - $def);
                $neto = (float) $r['costo'];              // costo de la factura = NETO de la línea

                if ($recibida <= 0 && $def <= 0) {
                    continue; // este renglón no vino en este remito → sigue pendiente
                }

                // El motor calcula el COSTO puesto en depósito (precio_compra) a partir del neto.
                $prod = Producto::find($r['producto_id']);
                $costo = $prod
                    ? $this->aplicarCosteoAlRecibir($prod, $compra->proveedor, $neto, $diferencias)
                    : $neto;

                if ($okQty > 0) {
                    StockLocal::firstOrCreate(
                        ['producto_id' => $r['producto_id'], 'local_id' => $this->localId],
                        ['cantidad' => 0, 'stock_minimo' => 0, 'precio_venta' => 0]
                    )->increment('cantidad', $okQty);
                }

                $estItem = $def > 0 ? ($okQty > 0 ? 'parcial' : 'defectuoso') : 'ok';
                $ri = RemitoItem::create([
                    'remito_id' => $remito->id,
                    'compra_item_id' => $r['item_id'],
                    'producto_id' => $r['producto_id'],
                    'cantidad_recibida' => $recibida,
                    'cantidad_defectuosa' => $def,
                    'estado_item' => $estItem,
                    'costo_unitario' => $costo,
                    'nota' => $r['nota'] ?: null,
                ]);

                // Trazabilidad: una unidad por cada caja OK.
                // Código: TRZ-{AAMMDD}-{idProveedor}-{idSucursal}-{correlativoPorLote}.
                for ($u = 1; $u <= $okQty; $u++) {
                    // Ancho fijo (prov 3, sucursal 2, seq 4) → el guion cae siempre
                    // en el mismo lugar (permite auto-formato al tipear el código).
                    $codigo = sprintf('TRZ-%s-%03d-%02d-%04d', $fechaCod, $compra->proveedor_id, $this->localId, ++$seq);
                    $unidad = UnidadTrazable::create([
                        'codigo' => $codigo,
                        'remito_item_id' => $ri->id,
                        'producto_id' => $r['producto_id'],
                        'proveedor_id' => $compra->proveedor_id,
                        'local_id' => $this->localId,
                        'costo' => $costo,
                        'estado' => UnidadTrazable::EN_STOCK,
                    ]);
                    $unidad->registrar('recepcion', $this->localId, $refRemito, 'Factura ' . $compra->numero);
                }

                if ($def > 0) {
                    $incidencias[] = "Defectuoso (x{$def}): {$r['desc']}" . ($r['nota'] ? " ({$r['nota']})" : '');
                }
            }

            // Líneas EXTRA que el encargado decidió agregar a la factura
            // (producto del catálogo o alta nueva): se crea un ítem de compra,
            // se recibe completo y entra al stock con su trazabilidad.
            foreach ($nuevas as $n) {
                $neto = (float) $n['costo'];                 // costo de la factura = NETO

                if ($n['crear']) {
                    $prod = Producto::create([
                        'nombre' => $n['nombre'],
                        'codigo' => $n['codigo'],
                        'proveedor_id' => $compra->proveedor_id,
                        'precio_neto' => $neto,
                        'precio_compra' => Costeo::costo($neto, $compra->proveedor),
                        'activo' => true,
                    ]);
                } else {
                    $prod = Producto::find($n['producto_id']);
                    if (! $prod) {
                        continue;
                    }
                    $this->aplicarCosteoAlRecibir($prod, $compra->proveedor, $neto, $diferencias);
                }

                $cant = (int) $n['cantidad'];
                $costo = (float) $prod->precio_compra;       // costo puesto en depósito (motor)

                $ci = $compra->items()->create([
                    'producto_id' => $prod->id,
                    'cantidad' => $cant,
                    'cantidad_recibida' => $cant,
                    'cantidad_defectuosa' => 0,
                    'cantidad_faltante' => 0,
                    'estado_item' => 'ok',
                    'costo_unitario' => $neto,               // en la factura se guarda el neto
                    'nota_recepcion' => $n['nota'] ?: 'Agregado en recepción (no figuraba en el pedido).',
                ]);

                $sl = StockLocal::firstOrCreate(
                    ['producto_id' => $prod->id, 'local_id' => $this->localId],
                    ['cantidad' => 0, 'stock_minimo' => 0, 'precio_venta' => 0]
                );
                // Sin precio de venta en esta sucursal → sugerirlo con el motor (conceptos de venta).
                if ((float) $sl->precio_venta <= 0) {
                    $sl->precio_venta = Costeo::precioVenta($costo, $compra->proveedor, $prod->conceptos);
                    $sl->save();
                }
                $sl->increment('cantidad', $cant);

                $ri = RemitoItem::create([
                    'remito_id' => $remito->id,
                    'compra_item_id' => $ci->id,
                    'producto_id' => $prod->id,
                    'cantidad_recibida' => $cant,
                    'cantidad_defectuosa' => 0,
                    'estado_item' => 'ok',
                    'costo_unitario' => $costo,
                    'nota' => $n['nota'] ?: null,
                ]);

                for ($u = 1; $u <= $cant; $u++) {
                    $codigo = sprintf('TRZ-%s-%03d-%02d-%04d', $fechaCod, $compra->proveedor_id, $this->localId, ++$seq);
                    UnidadTrazable::create([
                        'codigo' => $codigo,
                        'remito_item_id' => $ri->id,
                        'producto_id' => $prod->id,
                        'proveedor_id' => $compra->proveedor_id,
                        'local_id' => $this->localId,
                        'costo' => $costo,
                        'estado' => UnidadTrazable::EN_STOCK,
                    ])->registrar('recepcion', $this->localId, $refRemito, 'Factura ' . $compra->numero . ' · agregado en recepción');
                }

                $incidencias[] = ($n['crear'] ? 'Producto nuevo' : 'Agregado') . " (x{$cant}): {$prod->nombre}";
            }

            // Rollups en los ítems de la factura + estado de la factura.
            $compra->load('items.remitoItems');
            $pendientesRestantes = 0;
            foreach ($compra->items as $it) {
                $rec = (int) $it->remitoItems->sum('cantidad_recibida');
                $defT = (int) $it->remitoItems->sum('cantidad_defectuosa');
                $falt = max(0, (int) $it->cantidad - $rec);
                $pendientesRestantes += $falt;
                $it->update([
                    'cantidad_recibida' => $rec,
                    'cantidad_defectuosa' => $defT,
                    'cantidad_faltante' => $falt,
                    'estado_item' => $falt === 0 ? ($defT > 0 ? 'parcial' : 'ok') : ($rec > 0 ? 'parcial' : 'faltante'),
                ]);
            }
            $compra->update([
                'estado' => $pendientesRestantes === 0 ? 'recibida' : 'parcial',
                'recibido_por' => auth()->id(),
                'recibido_at' => now(),
                'fecha_llegada' => now()->toDateString(),
            ]);

            ActivityLog::create([
                'tipo' => 'stock',
                'titulo' => 'Ingreso de mercadería (remito)',
                'detalle' => "Remito de {$compra->numero} recibido por " . (auth()->user()?->name ?? 'depósito')
                    . ' en ' . (Local::find($this->localId)?->nombre ?? 'sucursal')
                    . ($pendientesRestantes > 0 ? " · quedan {$pendientesRestantes} u. pendientes." : ' · factura completa.')
                    . (count($incidencias) ? ' Incidencias: ' . implode('; ', $incidencias) : ''),
                'usuario_id' => auth()->id(),
                'local_id' => $this->localId,
            ]);

            foreach ($diferencias as [$nombre, $ant, $nuevo, $ventaSug]) {
                ActivityLog::create([
                    'tipo' => 'alerta',
                    'titulo' => 'Diferencia de costo de compra',
                    'detalle' => "«{$nombre}»: el costo puesto en depósito pasó de $" . number_format($ant, 2, ',', '.')
                        . ' a $' . number_format($nuevo, 2, ',', '.')
                        . '. Precio de venta sugerido: $' . number_format($ventaSug, 2, ',', '.')
                        . " (compra {$compra->numero}).",
                    'usuario_id' => auth()->id(),
                    'local_id' => $this->localId,
                ]);
            }
        });

        if ($this->escaneoId) {
            FacturaEscaneo::where('id', $this->escaneoId)->update(['estado' => 'aplicado']);
        }

        $compra->refresh();
        $this->ultimoRemito = $remitoId;
        $this->mensaje = $compra->estado === 'recibida'
            ? 'Remito registrado. Factura completa — stock actualizado.'
            : 'Remito registrado. Stock actualizado; la factura queda con saldo pendiente.';
        $this->reset(['sel', 'localId', 'numeroRemito', 'rItems', 'factura', 'scanMsg', 'scanError', 'escaneoId', 'extras']);
    }

    /**
     * Pliega un extra "vinculado a un renglón" sobre el rItem: suma `$qty` a lo ya
     * recibido del renglón (corrige un matcheo fallido), respetando el pendiente.
     */
    private function sumarRecibidoARenglon(int $idx, int $qty): void
    {
        $r = $this->rItems[$idx];
        $pend = (int) $r['pendiente'];
        $nuevo = min($pend, $this->recibidaDe($r) + $qty);

        if ($nuevo >= $pend) {
            $this->rItems[$idx]['estado'] = 'ok';
            $this->rItems[$idx]['llego'] = $pend;
        } else {
            $this->rItems[$idx]['estado'] = 'parcial';
            $this->rItems[$idx]['llego'] = $nuevo;
        }
    }

    /**
     * Engancha el motor de costeo al recibir: el costo unitario del renglón es el NETO de
     * la factura → calcula el COSTO puesto en depósito (precio_compra) con los conceptos de
     * costo del proveedor (+ IVA si corresponde), actualiza el producto (neto + costo) y
     * registra la diferencia de costo (con precio de venta sugerido) si cambió. Devuelve el
     * costo puesto en depósito.
     *
     * @param  array<int,array{0:string,1:float,2:float,3:float}>  $diferencias
     */
    private function aplicarCosteoAlRecibir(Producto $prod, ?Proveedor $proveedor, float $neto, array &$diferencias): float
    {
        if ($neto <= 0) {
            return (float) $prod->precio_compra;
        }

        $landed = Costeo::costo($neto, $proveedor, $prod->conceptos);
        $anterior = (float) $prod->precio_compra;
        if (abs($landed - $anterior) >= 0.01 && $anterior > 0) {
            $diferencias[] = [$prod->nombre, $anterior, $landed, Costeo::precioVenta($landed, $proveedor, $prod->conceptos)];
        }

        $prod->precio_neto = $neto;
        $prod->precio_compra = $landed;
        $prod->save();

        return $landed;
    }

    /** Cuántas unidades trajo este remito según el estado del renglón. */
    private function recibidaDe(array $r): int
    {
        return match ($r['estado'] ?? 'ok') {
            'ok' => (int) $r['pendiente'],
            'parcial' => max(0, min((int) $r['llego'], (int) $r['pendiente'])),
            'defectuoso' => (int) $r['pendiente'],   // llegaron todas, N defectuosas
            default => 0,                             // no_llego
        };
    }

    public function render()
    {
        // Facturas/compras con saldo pendiente (aprobadas o parcialmente recibidas).
        $porRecibir = Compra::with(['proveedor:id,nombre', 'local:id,nombre', 'items.remitoItems'])
            ->whereIn('estado', ['pendiente', 'aprobada', 'parcial'])
            ->orderBy('fecha_estimada')->orderByDesc('id')->get()
            ->filter(fn (Compra $c) => $c->tienePendiente())
            ->values();

        $remitosRecientes = Remito::with(['compra:id,numero', 'compra.proveedor:id,nombre', 'local:id,nombre', 'recibidoPor:id,name'])
            ->orderByDesc('recibido_at')->limit(8)->get();

        $compra = $this->sel
            ? Compra::with(['proveedor:id,nombre', 'local:id,nombre'])->find($this->sel)
            : null;

        return view('livewire.recepcion.index', [
            'porRecibir' => $porRecibir,
            'remitosRecientes' => $remitosRecientes,
            'compra' => $compra,
            'locales' => Local::where('activo', true)->orderBy('id')->get(['id', 'nombre']),
            'sucursalFija' => (bool) auth()->user()?->local_id,
        ]);
    }
}
