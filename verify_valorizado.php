<?php

/**
 * Verificación de STOCK VALORIZADO (plata inmovilizada a costo vs venta, por proveedor/sucursal).
 *   php verify_valorizado.php
 * Lo que muta va en transacción con ROLLBACK: la DB queda intacta.
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Local;
use App\Models\Producto;
use App\Models\StockLocal;
use App\Models\User;
use App\Support\StockValorizado as Motor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

$ok = 0;
$fail = 0;
$check = function (string $titulo, callable $fn) use (&$ok, &$fail) {
    try {
        $r = $fn();
        if ($r === true) {
            echo "  ✔ {$titulo}\n";
            $ok++;
        } else {
            echo "  ✘ {$titulo} → {$r}\n";
            $fail++;
        }
    } catch (\Throwable $e) {
        echo "  ✘ {$titulo} → EXCEPCIÓN " . get_class($e) . ': ' . $e->getMessage() . "\n      " . $e->getFile() . ':' . $e->getLine() . "\n";
        $fail++;
    }
};

$dueno = User::where('email', 'dueno@ecomercial.com')->first();
Auth::login($dueno);

echo "\n== 1. Cálculo ==\n";
$check('valor a costo = cantidad × precio de compra (caso controlado)', function () {
    DB::beginTransaction();
    try {
        $p = Producto::create(['codigo' => 'ZZ-VAL1', 'nombre' => 'ZZ Producto valorizado', 'activo' => true, 'precio_compra' => 100]);
        $local = Local::where('activo', true)->first();
        StockLocal::create(['producto_id' => $p->id, 'local_id' => $local->id, 'cantidad' => 7, 'stock_minimo' => 1, 'precio_venta' => 250]);

        $f = Motor::filas(null, null, null, 'ZZ Producto valorizado')->first();
        DB::rollBack();

        if (! $f) {
            return 'no lo encontró';
        }
        if (abs($f['valor_costo'] - 700) > 0.01) {
            return "valor a costo {$f['valor_costo']}, esperaba 700";
        }
        if (abs($f['valor_venta'] - 1750) > 0.01) {
            return "valor a venta {$f['valor_venta']}, esperaba 1750";
        }
        if (abs($f['margen'] - 1050) > 0.01) {
            return "margen {$f['margen']}, esperaba 1050";
        }
        // Margen % sobre la venta: 1050/1750 = 60%
        if (abs($f['margen_pct'] - 60) > 0.05) {
            return "margen% {$f['margen_pct']}, esperaba 60";
        }

        return true;
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

$check('un producto SIN costo se marca y no infla el valor a costo', function () {
    DB::beginTransaction();
    try {
        $p = Producto::create(['codigo' => 'ZZ-VAL2', 'nombre' => 'ZZ Sin costo', 'activo' => true, 'precio_compra' => 0]);
        $local = Local::where('activo', true)->first();
        StockLocal::create(['producto_id' => $p->id, 'local_id' => $local->id, 'cantidad' => 5, 'stock_minimo' => 1, 'precio_venta' => 300]);

        $filas = Motor::filas(null, null, null, 'ZZ Sin costo');
        $f = $filas->first();
        $t = Motor::totales($filas);
        DB::rollBack();

        if (! $f || ! $f['sin_costo']) {
            return 'no quedó marcado como sin costo';
        }
        if (abs($f['valor_costo']) > 0.01) {
            return "valor a costo {$f['valor_costo']}, esperaba 0";
        }
        if ($t['sin_costo_articulos'] !== 1) {
            return "sin_costo_articulos = {$t['sin_costo_articulos']}";
        }

        return true;
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

$check('"solo con stock" excluye los que tienen 0', function () {
    DB::beginTransaction();
    try {
        $p = Producto::create(['codigo' => 'ZZ-VAL3', 'nombre' => 'ZZ Sin unidades', 'activo' => true, 'precio_compra' => 50]);
        $local = Local::where('activo', true)->first();
        StockLocal::create(['producto_id' => $p->id, 'local_id' => $local->id, 'cantidad' => 0, 'stock_minimo' => 1, 'precio_venta' => 90]);

        $con = Motor::filas(null, null, null, 'ZZ Sin unidades', true)->count();
        $sin = Motor::filas(null, null, null, 'ZZ Sin unidades', false)->count();
        DB::rollBack();

        return ($con === 0 && $sin === 1) ?: "conFiltro={$con} sinFiltro={$sin}";
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

echo "\n== 2. Totales y agrupaciones ==\n";
$check('los totales son la suma exacta de los renglones', function () {
    $filas = Motor::filas();
    $t = Motor::totales($filas);
    $sumaCosto = round((float) $filas->sum('valor_costo'), 2);
    $sumaVenta = round((float) $filas->sum('valor_venta'), 2);

    return (abs($t['valor_costo'] - $sumaCosto) < 0.01 && abs($t['valor_venta'] - $sumaVenta) < 0.01)
        ?: "costo {$t['valor_costo']} vs {$sumaCosto} · venta {$t['valor_venta']} vs {$sumaVenta}";
});

$check('margen = venta − costo', function () {
    $t = Motor::totales(Motor::filas());

    return abs($t['margen'] - ($t['valor_venta'] - $t['valor_costo'])) < 0.01 ?: 'no cierra';
});

foreach (['proveedor', 'local', 'categoria'] as $por) {
    $check("agrupar por {$por} conserva el total a costo", function () use ($por) {
        $filas = Motor::filas();
        $t = Motor::totales($filas);
        $g = Motor::agrupado($filas, $por);
        $suma = round(array_sum(array_column($g, 'valor_costo')), 2);

        return abs($suma - $t['valor_costo']) < 0.02 ?: "grupos suman {$suma}, total {$t['valor_costo']}";
    });
}

$check('filtrar por sucursal acota los renglones a esa sucursal', function () {
    $local = Local::where('activo', true)->first();
    $filas = Motor::filas($local->id);
    $otros = $filas->where('local_id', '!=', $local->id)->count();

    return $otros === 0 ?: "se colaron {$otros} de otra sucursal";
});

$check('la suma de las sucursales da el total sin filtro', function () {
    $totalGlobal = Motor::totales(Motor::filas())['valor_costo'];
    $suma = 0.0;
    foreach (Local::where('activo', true)->pluck('id') as $id) {
        $suma += Motor::totales(Motor::filas($id))['valor_costo'];
    }

    return abs($suma - $totalGlobal) < 0.02 ?: "suma por sucursal {$suma} vs global {$totalGlobal}";
});

echo "\n== 3. Render y permisos ==\n";
foreach (['detalle', 'proveedor', 'sucursal', 'categoria'] as $t) {
    $check("tab «{$t}» renderiza", function () use ($t) {
        $html = Livewire::test(App\Livewire\Stock\Valorizado::class)->call('setTab', $t)->html();

        return strlen($html) > 800 ?: 'html sospechosamente corto';
    });
}

$check('la pantalla muestra los KPIs de costo, venta y margen', function () {
    $html = Livewire::test(App\Livewire\Stock\Valorizado::class)->html();

    return (str_contains($html, 'Valor a costo') && str_contains($html, 'Valor a venta') && str_contains($html, 'Margen potencial'))
        ? true : 'faltan KPIs';
});

$check('el VENDEDOR no accede (expone el precio de compra)', function () {
    $v = User::where('rol', 'vendedor')->first();
    if (! $v) {
        return true;
    }
    Auth::login($v);
    try {
        Livewire::test(App\Livewire\Stock\Valorizado::class)->assertForbidden();
        $r = true;
    } catch (\Throwable $e) {
        $r = 'el vendedor pudo entrar: ' . $e->getMessage();
    }
    Auth::login(User::where('email', 'dueno@ecomercial.com')->first());

    return $r;
});

$check('exportar devuelve una descarga CSV', function () {
    $r = Livewire::test(App\Livewire\Stock\Valorizado::class)->call('exportarCsv')->effects['download'] ?? null;
    $comp = new App\Livewire\Stock\Valorizado();
    $resp = $comp->exportarCsv();

    return $resp instanceof \Symfony\Component\HttpFoundation\StreamedResponse ?: 'no devolvió StreamedResponse';
});

echo "\n────────────────────────────────\n";
echo ($fail === 0 ? '✅ TODO OK' : '❌ HAY FALLAS') . " — {$ok} ok · {$fail} fallas\n";
