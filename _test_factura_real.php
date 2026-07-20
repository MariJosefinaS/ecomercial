<?php
/**
 * Prueba la extracción REAL contra una factura de la carpeta FACTURAS.
 * Corre: php _test_factura_real.php  ["nombre parcial del archivo"]
 * Requiere OPENROUTER_API_KEY en .env.
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FacturaScanner;

$dir = realpath(__DIR__ . '/../FACTURAS');
$filtro = $argv[1] ?? '';
$archivos = array_values(array_filter(glob($dir . '/*'), function ($f) use ($filtro) {
    return is_file($f) && ($filtro === '' || stripos(basename($f), $filtro) !== false)
        && preg_match('/\.(pdf|jpe?g|png|webp)$/i', $f);
}));

if (! $archivos) { fwrite(STDERR, "No hay facturas en $dir\n"); exit(1); }
$archivo = $archivos[0];
$mime = mime_content_type($archivo) ?: 'application/pdf';
$prov = config('services.vision.provider');
$modelo = $prov === 'google' ? config('services.google_ai.model') : config('services.openrouter.model');
echo "Proveedor: {$prov} · Modelo: {$modelo}\n";
echo "Archivo: " . basename($archivo) . " ($mime)\n\n";

$t0 = microtime(true);
try {
    $r = app(FacturaScanner::class)->extraer($archivo, $mime);
} catch (\Throwable $e) {
    fwrite(STDERR, "❌ " . $e->getMessage() . "\n");
    exit(1);
}
$ms = round((microtime(true) - $t0) * 1000);

echo "✅ Extracción OK en {$ms} ms\n\n";
echo "== CABECERA ==\n" . json_encode($r['cabecera'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
echo "== LÍNEAS (" . count($r['lineas']) . ") ==\n";
foreach ($r['lineas'] as $l) {
    printf("  [%s] %s%s  x%s @ %s = %s\n",
        $l['tipo'] ?? '?',
        ($l['codigo'] ?? '') ? $l['codigo'] . ' · ' : '',
        $l['descripcion'] ?? '',
        $l['cantidad'] ?? '?', $l['p_unit'] ?? '?', $l['total'] ?? '?');
}
