<?php
/**
 * Verificación Fase B (escaneo de factura en Recepción).
 * Corre: php _render_recepcion.php
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Livewire\Recepcion\Index;
use App\Models\Compra;
use App\Models\User;
use App\Support\MatcheoFactura;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

function ok($t) { echo "  ✅ $t\n"; }
function bad($t) { echo "  ❌ $t\n"; }

$dep = User::where('email', 'deposito@ecomercial.com')->first();
Auth::login($dep);
echo "Logueado: {$dep->name} (rol {$dep->rol})\n\n";

$compra = Compra::with('items.producto')->whereIn('estado', ['pendiente', 'aprobada'])->whereHas('items')->first();
if (! $compra) { bad('No hay compra pendiente/aprobada con ítems para probar'); exit(1); }
echo "Compra de prueba: {$compra->numero} · {$compra->items->count()} ítem(s)\n";

// ---- 1) Motor de matching ----
echo "\n[1] MatcheoFactura\n";

// (a) línea que DEBE matchear alto: uso el código+nombre del 1er ítem del pedido.
$it = $compra->items->first();
$prod = $it->producto;
$lineaExacta = [
    'tipo' => 'producto',
    'codigo' => $prod->codigo,
    'descripcion' => $prod->nombre,
    'cantidad' => $it->cantidad,
    'p_unit' => 1234.5,
    'total' => 1234.5 * $it->cantidad,
];

// (b) línea de la factura REAL de ejemplo (no debería estar en el pedido) + un gasto.
$lineaReal = ['tipo' => 'producto', 'codigo' => 'EVC330', 'descripcion' => 'EXHIBIDORA NEBA 330 LTS', 'cantidad' => 4, 'p_unit' => 536797.92, 'total' => 2147191.68];
$gasto = ['tipo' => 'gasto', 'codigo' => null, 'descripcion' => 'GESTIONES TRANSPORTES DESCON', 'cantidad' => 1, 'p_unit' => 18360.66, 'total' => 18360.66];

$res = MatcheoFactura::resolver($compra, [$lineaExacta, $lineaReal, $gasto]);

$m0 = $res[0]['match'];
$m0['confianza'] === MatcheoFactura::ALTA ? ok("Línea exacta → confianza ALTA ({$m0['motivo']})") : bad("Línea exacta → {$m0['confianza']} (esperaba alta)");
$m0['compra_item_id'] === $it->id ? ok('Vinculó al ítem correcto del pedido') : bad("compra_item_id={$m0['compra_item_id']} esperaba {$it->id}");

$m1 = $res[1]['match'];
echo "  ℹ EVC330 (factura real) → confianza {$m1['confianza']}, candidatos: " . count($m1['candidatos']) . "\n";

$res[2]['match'] === null ? ok('Gasto NO se matchea (match=null)') : bad('Gasto debería tener match=null');

// ---- 2) Render del componente ----
echo "\n[2] Render Livewire\n";
try {
    $t = Livewire::test(Index::class);
    $t->html();
    ok('Render inicial (lista por recibir)');

    $t->call('abrir', $compra->id);
    $html = $t->html();
    str_contains($html, 'Escanear factura') ? ok('Panel de escaneo presente') : bad('Falta el panel de escaneo');
    str_contains($html, 'A ingresar') ? ok('Cantidad read-only (label "A ingresar")') : bad('No aparece la cantidad read-only');
    str_contains($html, 'Confirmar recepción') ? ok('Botón confirmar presente') : bad('Falta botón confirmar');

    // Escanear sin archivo → error de validación, sin romper el componente.
    $t->call('escanearFactura')->assertHasErrors('factura');
    ok('escanearFactura sin archivo → valida (no rompe)');
} catch (\Throwable $e) {
    bad('Excepción en render: ' . $e->getMessage());
    echo $e->getTraceAsString() . "\n";
}

echo "\nListo.\n";
