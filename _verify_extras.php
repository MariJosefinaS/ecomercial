<?php
/**
 * Verificación Fase B — reasignación de "extras" en Recepción.
 * Extras = líneas del remito que no matchean la factura. El encargado puede:
 *   ignorar · vincular a un renglón · agregar a la factura (catálogo o nuevo).
 * Corre: php _verify_extras.php
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Livewire\Recepcion\Index;
use App\Models\Compra;
use App\Models\Producto;
use App\Models\StockLocal;
use App\Models\UnidadTrazable;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

$pass = 0; $fail = 0;
function ok($t) { global $pass; $pass++; echo "  ✅ $t\n"; }
function bad($t) { global $fail; $fail++; echo "  ❌ $t\n"; }

$dep = User::where('email', 'deposito@ecomercial.com')->first() ?? User::where('rol', 'super_admin')->first();
Auth::login($dep);
echo "Logueado: {$dep->name} (rol {$dep->rol}, local " . ($dep->local_id ?? '—') . ")\n\n";

$compra = Compra::with('items.producto')->whereIn('estado', ['pendiente', 'aprobada', 'parcial'])
    ->whereHas('items')->get()->first(fn ($c) => $c->tienePendiente());
if (! $compra) { bad('No hay compra con saldo pendiente para probar'); exit(1); }
echo "Compra: {$compra->numero} · proveedor {$compra->proveedor_id} · {$compra->items->count()} ítem(s)\n";

// ===== 1) Render con extras (UI interactiva) =====
echo "\n[1] Render de la UI de extras\n";
$t = Livewire::test(Index::class)->call('abrir', $compra->id);
$localId = $t->get('localId');
$rItems = $t->get('rItems');
echo "  localId destino = {$localId} · rItems = " . count($rItems) . "\n";

// Un producto existente que NO esté en la compra (para aislar el efecto del extra).
$idsEnCompra = $compra->items->pluck('producto_id')->all();
$prodExistente = Producto::where('activo', true)->whereNotIn('id', $idsEnCompra)->first()
    ?? Producto::where('activo', true)->first();

$extras = [
    [ // A) agregar como PRODUCTO NUEVO
        'codigo' => 'XTRA-NEW', 'descripcion' => 'PRODUCTO EXTRA NUEVO',
        'cantidad' => 3, 'costo' => '1500', 'candidatos' => [], 'sugerido_id' => null,
        'destino' => 'agregar', 'item_id' => null, 'prod_sel' => 'nuevo',
        'nuevo_nombre' => 'PRODUCTO EXTRA NUEVO ' . now()->timestamp, 'nuevo_codigo' => 'XTRA-NEW-' . now()->timestamp, 'nota' => 'alta en recepcion',
    ],
    [ // B) agregar como PRODUCTO EXISTENTE del catálogo
        'codigo' => 'XTRA-CAT', 'descripcion' => 'EXTRA CATALOGO',
        'cantidad' => 2, 'costo' => '900',
        'candidatos' => [['producto_id' => $prodExistente->id, 'nombre' => $prodExistente->nombre, 'score' => 0.4]],
        'sugerido_id' => $prodExistente->id,
        'destino' => 'agregar', 'item_id' => null, 'prod_sel' => (string) $prodExistente->id,
        'nuevo_nombre' => '', 'nuevo_codigo' => '', 'nota' => '',
    ],
    [ // C) vincular a un renglón de la factura
        'codigo' => 'XTRA-LINK', 'descripcion' => 'EXTRA VINCULADO',
        'cantidad' => 1, 'costo' => '0', 'candidatos' => [], 'sugerido_id' => null,
        'destino' => 'item', 'item_id' => $rItems[0]['item_id'], 'prod_sel' => '',
        'nuevo_nombre' => '', 'nuevo_codigo' => '', 'nota' => '',
    ],
];

$t->set('extras', $extras);
$html = $t->html();
str_contains($html, 'no figuran en la factura') ? ok('Aviso de extras presente') : bad('Falta el aviso de extras');
str_contains($html, 'Producto nuevo') ? ok('Opción "Producto nuevo" en el select') : bad('Falta la opción producto nuevo');
str_contains($html, 'Vincular a un renglón') ? ok('Opción "Vincular a un renglón"') : bad('Falta vincular a renglón');
str_contains($html, 'Nombre del producto') ? ok('Inputs de alta visibles (prod_sel=nuevo)') : bad('No se muestran inputs de alta');

// ===== 2) Confirmar y verificar efectos =====
echo "\n[2] Confirmar remito con extras\n";
$prodAntes = Producto::count();
$stockExistAntes = (int) (StockLocal::where('producto_id', $prodExistente->id)->where('local_id', $localId)->value('cantidad') ?? 0);
$unidadesAntes = UnidadTrazable::where('local_id', $localId)->count();
$r0pend = (int) $rItems[0]['pendiente'];

$t->call('confirmarRecepcion');
$errs = $t->errors();
$errs->isEmpty() ? ok('Sin errores de validación') : bad('Errores inesperados: ' . json_encode($errs->all()));

$prodDespues = Producto::count();
$prodDespues === $prodAntes + 1 ? ok("Producto nuevo creado (productos {$prodAntes}→{$prodDespues})") : bad("Productos {$prodAntes}→{$prodDespues} (esperaba +1)");

$nuevo = Producto::where('nombre', 'like', 'PRODUCTO EXTRA NUEVO %')->latest('id')->first();
if ($nuevo) {
    (int) $nuevo->proveedor_id === (int) $compra->proveedor_id ? ok('Producto nuevo hereda el proveedor de la compra') : bad('Proveedor del producto nuevo no coincide');
    $sn = (int) (StockLocal::where('producto_id', $nuevo->id)->where('local_id', $localId)->value('cantidad') ?? 0);
    $sn === 3 ? ok("Stock del producto nuevo = 3") : bad("Stock del producto nuevo = {$sn} (esperaba 3)");
} else { bad('No se encontró el producto nuevo'); }

$stockExistDespues = (int) (StockLocal::where('producto_id', $prodExistente->id)->where('local_id', $localId)->value('cantidad') ?? 0);
$stockExistDespues === $stockExistAntes + 2 ? ok("Extra→catálogo sumó al stock existente ({$stockExistAntes}→{$stockExistDespues})") : bad("Stock existente {$stockExistAntes}→{$stockExistDespues} (esperaba +2)");

$compra->refresh();
$ci = $compra->items()->where('producto_id', $nuevo?->id)->first();
$ci ? ok('Se creó un CompraItem para el producto nuevo') : bad('No se creó el CompraItem del extra nuevo');

// Renglón vinculado: debe quedar recibido (al menos 1).
$itVinc = $compra->items()->find($rItems[0]['item_id']);
$itVinc && $itVinc->recibidoTotal() >= 1 ? ok("Renglón vinculado recibió ≥1 (rec={$itVinc->recibidoTotal()}, pend orig={$r0pend})") : bad('El renglón vinculado no registró recepción');

$unidadesDespues = UnidadTrazable::where('local_id', $localId)->count();
// 3 (nuevo) + 2 (catálogo) + recepción normal de los rItems → al menos +5
$unidadesDespues >= $unidadesAntes + 5 ? ok("Trazabilidad: +" . ($unidadesDespues - $unidadesAntes) . " unidades (≥5 por extras)") : bad("Trazabilidad +" . ($unidadesDespues - $unidadesAntes) . " (esperaba ≥5)");

echo "\n──────────\nRESULTADO: {$pass} ✅  /  {$fail} ❌\n";
exit($fail ? 1 : 0);
