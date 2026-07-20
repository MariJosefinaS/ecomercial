<?php

/**
 * Verificador de renderizado de componentes Livewire (no sólo view:cache).
 * Uso: php _render.php
 * Bootea Laravel, loguea un usuario y hace ->html() en cada componente en try/catch.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

$dueno = User::where('email', 'dueno@ecomercial.com')->first();

/** Lista de componentes a testear: [clase, params, callbacks(opcional)]. */
$tests = [
    [\App\Livewire\Dashboard\TopSellers::class, []],
    [\App\Livewire\Dashboard\RecentActivity::class, []],
    [\App\Livewire\Dashboard\PriceDifferenceAlerts::class, []],
    [\App\Livewire\Dashboard\PendingApprovals::class, []],
    [\App\Livewire\Shared\Notifications::class, []],
    [\App\Livewire\Reportes\Index::class, []],
    [\App\Livewire\Usuarios\Index::class, []],
    [\App\Livewire\Ventas\Nueva::class, []],
    [\App\Livewire\Tesoreria\Index::class, []],
    [\App\Livewire\Devoluciones\Index::class, []],
    [\App\Livewire\Proveedores\Index::class, []],
    [\App\Livewire\Stock\Index::class, []],
    [\App\Livewire\Stock\Reposicion::class, []],
    [\App\Livewire\Ventas\Index::class, []],
    [\App\Livewire\Compras\Index::class, []],
    [\App\Livewire\Clientes\Index::class, []],
    [\App\Livewire\Configuracion\Index::class, []],
];

$only = $argv[1] ?? null; // filtrar por substring de clase
$fail = 0;

foreach ($tests as [$class, $params]) {
    if ($only && stripos($class, $only) === false) {
        continue;
    }
    Auth::login($dueno);
    try {
        $html = Livewire::test($class, $params)->html();
        $len = strlen($html);
        echo "OK    " . str_pad(class_basename($class), 22) . " ({$len} bytes)\n";
    } catch (\Throwable $e) {
        $fail++;
        echo "ERROR " . str_pad(class_basename($class), 22) . " :: " . $e->getMessage() . "\n";
        echo "      " . $e->getFile() . ':' . $e->getLine() . "\n";
    }
}

echo $fail ? "\n❌ {$fail} con error\n" : "\n✅ Todos OK\n";
