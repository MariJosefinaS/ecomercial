<?php
/**
 * Cierre Fase B: prueba el FLUJO COMPLETO de recepción con escaneo REAL.
 * abrir OC -> subir factura real -> escanearFactura (Gemini real) -> confirmarRecepcion -> suma stock.
 * Corre: php _verify_flujo_completo.php ["OC-NEBA-01"]  (default NEBA)
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Livewire\Recepcion\Index;
use App\Models\{Compra, User, StockLocal, Remito, ActivityLog};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

$ocNumero = $argv[1] ?? 'OC-NEBA-01';
$mapaFactura = [
    'OC-NEBA-01' => 'FA NEBA RED ACE.pdf',
    'OC-LILIANA-01' => 'Comprobante 0030003924 .PDF',
    'OC-ARE-01' => 'FC A 0003-00001609.pdf',
];
$archivo = realpath(__DIR__ . '/../FACTURAS/' . ($mapaFactura[$ocNumero] ?? ''));

$ok = 0; $fail = 0;
function check($cond, $msg) { global $ok, $fail; if ($cond) { $ok++; echo "  ✅ $msg\n"; } else { $fail++; echo "  ❌ $msg\n"; } }

echo "== Flujo completo de recepción con escaneo real: $ocNumero ==\n";
if (! $archivo || ! is_file($archivo)) { fwrite(STDERR, "No encontré la factura para $ocNumero\n"); exit(1); }
echo "Factura: " . basename($archivo) . "\n\n";

$compra = Compra::with('items.producto')->where('numero', $ocNumero)->first();
if (! $compra) { fwrite(STDERR, "No existe la OC $ocNumero (corré _seed_facturas_demo.php)\n"); exit(1); }

// Login como encargado de depósito (tiene ver_recepcion + recepcionar).
$deposito = User::where('email', 'deposito@ecomercial.com')->first();
if (! $deposito) { fwrite(STDERR, "Falta el usuario deposito@ecomercial.com\n"); exit(1); }
Auth::login($deposito);
$localDestino = $deposito->local_id ?? $compra->local_id;

// Stock ANTES (por producto del pedido, en la sucursal destino).
$antes = [];
foreach ($compra->items as $it) {
    $antes[$it->producto_id] = (int) (StockLocal::where('producto_id', $it->producto_id)
        ->where('local_id', $localDestino)->value('cantidad') ?? 0);
}
$remitosAntes = Remito::count();
$alertasAntes = ActivityLog::where('tipo', 'alerta')->count();

// Archivo real como upload de Livewire (contenido real para que Gemini lo lea).
$upload = UploadedFile::fake()->createWithContent(basename($archivo), file_get_contents($archivo));

$t0 = microtime(true);
$comp = Livewire::test(Index::class)
    ->call('abrir', $compra->id)
    ->set('factura', $upload)
    ->call('escanearFactura');
$ms = round((microtime(true) - $t0) * 1000);

$scanError = $comp->get('scanError');
$rItems = $comp->get('rItems');
$extras = $comp->get('extras');
$numeroRemito = $comp->get('numeroRemito');

echo "-- Escaneo ($ms ms) --\n";
check($scanError === null, "escaneo sin error" . ($scanError ? " ($scanError)" : ''));
check(is_array($rItems) && count($rItems) > 0, "renglones cargados (" . count($rItems ?? []) . ")");
echo "     nº de remito de la cabecera: '" . $numeroRemito . "'" . ($numeroRemito === '' ? " (el documento no trae campo remito → se carga a mano)" : '') . "\n";
$conLlegada = array_filter($rItems ?? [], fn ($r) => ($r['llego'] ?? 0) > 0);
check(count($conLlegada) > 0, "al menos un renglón con cantidad llegada");
foreach ($rItems ?? [] as $r) {
    echo "     · {$r['cod']} {$r['desc']}: estado={$r['estado']} llegó={$r['llego']}/{$r['pendiente']} match=" . ($r['match'] ?? '—') . "\n";
}
if (is_array($extras) && count($extras)) echo "     (extras sin match: " . count($extras) . ")\n";

// Simular al encargado: observación obligatoria en renglones no-OK (parcial/
// defectuoso/no llegó) + nº de remito a mano si el documento no lo trae.
foreach ($rItems ?? [] as $i => $r) {
    if (($r['estado'] ?? 'ok') !== 'ok') {
        $comp->set("rItems.$i.nota", 'Observación de recepción (verificación automatizada).');
    }
}
if ($numeroRemito === '') {
    $numeroRemito = 'R-DEMO-' . $compra->id;
    $comp->set('numeroRemito', $numeroRemito);
}

// Confirmar recepción.
$comp->call('confirmarRecepcion');
$mensaje = $comp->get('mensaje');
$errores = $comp->errors();

echo "\n-- Confirmación --\n";
check($errores->isEmpty(), "confirmar sin errores de validación" . ($errores->isEmpty() ? '' : ' (' . implode('; ', $errores->all()) . ')'));
check($mensaje !== null, "mensaje de confirmación: " . ($mensaje ?? '—'));
check(Remito::count() === $remitosAntes + 1, "se creó 1 remito");

// Stock DESPUÉS.
echo "\n-- Stock (sucursal $localDestino) --\n";
$compra->refresh()->load('items.producto');
foreach ($compra->items as $it) {
    $ahora = (int) (StockLocal::where('producto_id', $it->producto_id)
        ->where('local_id', $localDestino)->value('cantidad') ?? 0);
    $llego = collect($rItems)->firstWhere('item_id', $it->id)['llego'] ?? 0;
    $esperado = $antes[$it->producto_id] + $llego;
    check($ahora === $esperado, "{$it->producto?->codigo} {$it->producto?->nombre}: {$antes[$it->producto_id]} +{$llego} = {$ahora} (esperado {$esperado})");
}

// Efectos colaterales.
$alertasDespues = ActivityLog::where('tipo', 'alerta')->count();
echo "\n-- Efectos --\n";
echo "     alertas de diferencia de precio nuevas: " . ($alertasDespues - $alertasAntes) . "\n";
echo "     estado de la factura: " . $compra->estado . "\n";

echo "\n== RESULTADO: $ok OK · $fail FALLA ==\n";
exit($fail ? 1 : 0);
