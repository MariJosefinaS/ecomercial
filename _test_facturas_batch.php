<?php
/** Corre la extracción sobre TODAS las facturas de la carpeta FACTURAS y resume. */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FacturaScanner;

$dir = realpath(__DIR__ . '/../FACTURAS');
$archivos = array_values(array_filter(glob($dir . '/*'),
    fn ($f) => is_file($f) && preg_match('/\.(pdf|jpe?g|png|webp)$/i', $f)));

$prov = config('services.vision.provider');
$modelo = $prov === 'google' ? config('services.google_ai.model') : config('services.openrouter.model');
echo "Proveedor: {$prov} · Modelo: {$modelo} · " . count($archivos) . " factura(s)\n";
echo str_repeat('═', 70) . "\n";

$scanner = app(FacturaScanner::class);
foreach ($archivos as $f) {
    $mime = mime_content_type($f) ?: 'application/pdf';
    echo "\n📄 " . basename($f) . "\n";
    try {
        $t0 = microtime(true);
        $r = $scanner->extraer($f, $mime);
        $ms = round((microtime(true) - $t0) * 1000);
        $c = $r['cabecera'];
        $cmp = $c['comprobante'] ?? [];
        $prods = array_filter($r['lineas'], fn ($l) => ($l['tipo'] ?? '') === 'producto');
        $gastos = array_filter($r['lineas'], fn ($l) => ($l['tipo'] ?? '') === 'gasto');
        printf("   ✅ %d ms · %s · Comprob %s %s-%s · Fecha %s · Total %s\n",
            $ms, $c['proveedor'] ?? '?',
            $cmp['tipo'] ?? '?', $cmp['punto_venta'] ?? '?', $cmp['numero'] ?? '?',
            $c['fecha'] ?? '?', data_get($c, 'totales.total') ?? '?');
        printf("   Líneas: %d producto(s) + %d gasto(s)\n", count($prods), count($gastos));
        foreach ($prods as $p) {
            printf("     • [%s] %s  x%s @ %s\n", $p['codigo'] ?? '—',
                mb_substr($p['descripcion'] ?? '', 0, 45), $p['cantidad'] ?? '?', $p['p_unit'] ?? '?');
        }
        foreach ($gastos as $g) {
            printf("     · (gasto) %s @ %s\n", mb_substr($g['descripcion'] ?? '', 0, 40), $g['total'] ?? '?');
        }
    } catch (\Throwable $e) {
        echo "   ❌ " . $e->getMessage() . "\n";
    }
}
echo "\n" . str_repeat('═', 70) . "\nListo.\n";
