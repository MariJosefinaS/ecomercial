<?php

/**
 * Verificación — modelo de costeo parametrizable (conceptos con ámbito + cascada).
 * Corre:  php _verify_costeo.php
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Livewire\Configuracion\Index as ConfigIndex;
use App\Livewire\Proveedores\Index as ProvIndex;
use App\Livewire\Stock\Index as StockIndex;
use App\Models\ConceptoPrecio;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Support\Costeo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

$ok = 0; $fail = 0;
function check(string $label, bool $cond, string $extra = ''): void {
    global $ok, $fail;
    echo ($cond ? "  OK   " : "  FAIL ") . $label . ($extra ? " — {$extra}" : '') . "\n";
    $cond ? $ok++ : $fail++;
}
function near(float $a, float $b): bool { return abs($a - $b) < 0.02; }

// Snapshots de conceptos por producto (override) para probar cascada pura.
function snap(string $ambito, float ...$pcts): array {
    $out = [];
    foreach ($pcts as $i => $p) {
        $out[] = ['id' => 900 + $i, 'nombre' => "c{$i}", 'ambito' => $ambito, 'aplica' => true, 'porcentaje' => $p, 'orden' => $i + 1];
    }
    return $out;
}

Auth::login(User::where('email', 'dueno@ecomercial.com')->first());

echo "\n== Datos / migración ==\n";
check('Campo especial proveedores.remarque_pct ELIMINADO', ! Schema::hasColumn('proveedores', 'remarque_pct'));
check('Campo especial productos.remarque_pct ELIMINADO', ! Schema::hasColumn('productos', 'remarque_pct'));
check('Columna conceptos_precio.ambito EXISTE', Schema::hasColumn('conceptos_precio', 'ambito'));

$remarcar = ConceptoPrecio::where('nombre', 'Remarcar')->first();
check('"Remarcar" es concepto ACTIVO de ámbito VENTA', $remarcar && $remarcar->activo && $remarcar->ambito === 'venta', $remarcar ? "ambito={$remarcar->ambito} activo={$remarcar->activo}" : 'no existe');
$flete = ConceptoPrecio::where('nombre', 'Flete')->first();
check('"Flete" es concepto de ámbito COSTO', $flete && $flete->ambito === 'costo');

$herr = Proveedor::where('nombre', 'Herramientas del Norte')->with('conceptos')->first();
$hogar = Proveedor::where('nombre', 'Hogar y Deco S.R.L.')->with('conceptos')->first();
$remHerr = $herr->conceptos->firstWhere('nombre', 'Remarcar');
$remHogar = $hogar->conceptos->firstWhere('nombre', 'Remarcar');
check('Herramientas: Remarcar 35 (pivot venta), sin IVA', $remHerr && (float) $remHerr->pivot->porcentaje === 35.0 && ! $herr->costea_con_iva);
check('Hogar: Remarcar 50 (pivot venta), costea con IVA 21', $remHogar && (float) $remHogar->pivot->porcentaje === 50.0 && $hogar->costea_con_iva && (float) $hogar->iva_pct === 21.0);
check('Herramientas tiene Flete (costo) en su pivot', (bool) $herr->conceptos->firstWhere('nombre', 'Flete'));

echo "\n== Motor Costeo (cascada) ==\n";
// Herramientas: Flete 5%, sin IVA. costo(1000)=1050 ; venta = 1050 × 1.35 = 1417.5
$cHerr = Costeo::costo(1000, $herr);
check('costo(1000) Herramientas = 1050 (Flete 5%, sin IVA)', near($cHerr, 1050), (string) $cHerr);
check('precioVenta Herramientas = 1417.50 (Remarcar 35% como concepto venta)', near(Costeo::precioVenta($cHerr, $herr), 1417.5), (string) Costeo::precioVenta($cHerr, $herr));
// Hogar: Flete 5% + IVA 21%. costo(1000)=1270.5 ; venta = ×1.5 = 1905.75
$cHogar = Costeo::costo(1000, $hogar);
check('costo(1000) Hogar = 1270.50 (Flete 5% + IVA 21%)', near($cHogar, 1270.5), (string) $cHogar);
check('precioVenta Hogar = 1905.75 (Remarcar 50%)', near(Costeo::precioVenta($cHogar, $hogar), 1905.75), (string) Costeo::precioVenta($cHogar, $hogar));
$d = Costeo::desglose(1000, $hogar);
check('desglose Hogar: costo=1270.50 y venta=1905.75', near($d['costo'], 1270.5) && near($d['precio_venta'], 1905.75));

// CASCADA ≠ SUMA: dos conceptos de costo 5% y 3% sobre 1000 → 1000×1.05×1.03 = 1081.50 (no 1080)
$costoCascada = Costeo::costo(1000, $herr, snap('costo', 5, 3));
check('Cascada costo (5% luego 3%) = 1081.50, NO 1080 (suma)', near($costoCascada, 1081.5), (string) $costoCascada);
// Dos conceptos de venta 50% y 10% sobre 1000 → 1000×1.5×1.1 = 1650 (no 1600)
$ventaCascada = Costeo::precioVenta(1000, $herr, snap('venta', 50, 10));
check('Cascada venta (50% luego 10%) = 1650.00, NO 1600 (suma)', near($ventaCascada, 1650), (string) $ventaCascada);

echo "\n== Productos sembrados ==\n";
$lamp = Producto::where('codigo', 'HOGAR-LAMP')->first(); // proveedor Hogar, neto 1200
check('HOGAR-LAMP tiene precio_neto=1200', near((float) $lamp->precio_neto, 1200), (string) $lamp->precio_neto);
check('HOGAR-LAMP precio_compra = costo (1200×1.05×1.21=1524.60)', near((float) $lamp->precio_compra, 1524.6), (string) $lamp->precio_compra);

echo "\n== Render Livewire (sin errores de runtime) ==\n";
try {
    $c = Livewire::test(StockIndex::class)->call('nuevoProducto')
        ->set('pProveedorId', $hogar->id)   // dispara updatedPProveedorId → carga conceptos del proveedor
        ->set('pPrecioCompra', '1000');
    $inst = $c->instance();
    $conceptos = collect($inst->pConceptos);
    check('Stock modal: carga conceptos del proveedor (Flete costo + Remarcar venta)',
        $conceptos->contains(fn ($x) => $x['ambito'] === 'costo') && $conceptos->contains(fn ($x) => $x['ambito'] === 'venta'),
        'n=' . $conceptos->count());
    check('Stock modal: costo computado = 1270.50', near((float) $inst->costo, 1270.5), (string) $inst->costo);
    check('Stock modal: precio venta computado = 1905.75', near((float) $inst->precioVenta, 1905.75), (string) $inst->precioVenta);

    // Quitar el concepto de venta (Remarcar) → el precio de venta cae al costo.
    $iVenta = $conceptos->search(fn ($x) => $x['ambito'] === 'venta');
    $c->call('quitarConceptoDeProducto', $iVenta);
    check('Stock modal: al quitar Remarcar, precio de venta = costo (1270.50)', near((float) $c->instance()->precioVenta, 1270.5), (string) $c->instance()->precioVenta);

    check('Stock\\Index render OK', strlen($c->html()) > 0);
} catch (\Throwable $e) {
    check('Stock\\Index modal/cómputo OK', false, $e->getMessage());
}

try {
    $p = Livewire::test(ProvIndex::class)->call('editarProveedor', $hogar->id)
        ->assertSet('fCosteaIva', true);
    check('Proveedores\\Index modal costeo OK', strlen($p->html()) > 0);
    // Solapa conceptos del proveedor: Remarcar aparece con ámbito venta.
    $pc = Livewire::test(ProvIndex::class)->call('abrir', $hogar->id)->set('tab', 'conceptos');
    $tieneVenta = collect($pc->instance()->conceptosProv)->contains(fn ($x) => ($x['ambito'] ?? '') === 'venta');
    check('Proveedores\\Index conceptos: Remarcar listado como venta', $tieneVenta);
} catch (\Throwable $e) {
    check('Proveedores\\Index OK', false, $e->getMessage());
}

try {
    $cfg = Livewire::test(ConfigIndex::class)->set('sub', 'conceptos');
    $tieneAmbito = collect($cfg->instance()->conceptos)->every(fn ($x) => isset($x['ambito']));
    check('Configuracion (conceptos) render + ámbito OK', strlen($cfg->html()) > 0 && $tieneAmbito);
} catch (\Throwable $e) {
    check('Configuracion render OK', false, $e->getMessage());
}

echo "\n----\n  {$ok} OK · {$fail} FAIL\n";
