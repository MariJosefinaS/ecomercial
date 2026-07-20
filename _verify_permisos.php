<?php
/**
 * Verifica el cableado de permisos en botones (Usuarios, Tesorería, Proveedores)
 * + confirma los ya hechos en Ventas/Compras.
 * Livewire expone abort(403) como STATUS del test → se usa assertForbidden().
 * Uso: php _verify_permisos.php
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

$dueno  = User::where('email', 'dueno@ecomercial.com')->first();       // super_admin
$vend   = User::where('rol', 'vendedor')->first();                     // sin los permisos gateados
$adminL = User::where('rol', 'admin_local')->first();                  // tiene ver_ficha_proveedor
$provId = Proveedor::value('id') ?? 1;

$pass = 0; $fail = 0;

function call403($user, $class, $method, array $args, string $label): void {
    global $pass, $fail; Auth::login($user);
    try {
        Livewire::test($class)->call($method, ...$args)->assertForbidden();
        $pass++; echo "  OK  $label — 403\n";
    } catch (\PHPUnit\Framework\ExpectationFailedException $e) {
        $fail++; echo "  ✗   $label — NO dio 403 (falta gate)\n";
    } catch (\Throwable $e) {
        $fail++; echo "  ⚠   $label — bloqueado por otra excepción: " . substr($e->getMessage(), 0, 90) . "\n";
    }
}

function callOK($user, $class, $method, array $args, string $label): void {
    global $pass, $fail; Auth::login($user);
    try {
        Livewire::test($class)->call($method, ...$args)->assertSuccessful();
        $pass++; echo "  OK  $label — sin 403 (autorizado)\n";
    } catch (\Throwable $e) {
        $fail++; echo "  ✗   $label — " . substr($e->getMessage(), 0, 90) . "\n";
    }
}

function renderOK($user, $class, string $label): void {
    global $pass, $fail; Auth::login($user);
    try {
        $h = Livewire::test($class)->html();
        $pass++; echo "  OK  $label — render " . strlen($h) . " bytes\n";
    } catch (\Throwable $e) {
        $fail++; echo "  ✗   $label — " . substr($e->getMessage(), 0, 110) . "\n";
    }
}

echo "== 1) 403 para usuario SIN permiso (vendedor) ==\n";
call403($vend, \App\Livewire\Usuarios\Index::class,   'nuevoUsuario',     [],   'Usuarios.nuevoUsuario (gestionar_usuarios)');
call403($vend, \App\Livewire\Usuarios\Index::class,   'editarUsuario',    [1],  'Usuarios.editarUsuario (gestionar_usuarios)');
call403($vend, \App\Livewire\Usuarios\Index::class,   'guardarUsuario',   [],   'Usuarios.guardarUsuario (gestionar_usuarios)');
call403($vend, \App\Livewire\Usuarios\Index::class,   'toggleActivo',     [1],  'Usuarios.toggleActivo (gestionar_usuarios)');
call403($vend, \App\Livewire\Usuarios\Index::class,   'resetearPassword', [1],  'Usuarios.resetearPassword (reset_password)');
call403($vend, \App\Livewire\Tesoreria\Index::class,  'marcarDepositado', [1],  'Tesoreria.marcarDepositado (cargar_cheques)');
call403($vend, \App\Livewire\Tesoreria\Index::class,  'marcarDebitado',   [1],  'Tesoreria.marcarDebitado (cargar_cheques)');
call403($vend, \App\Livewire\Proveedores\Index::class,'abrir',            [1],  'Proveedores.abrir (ver_ficha_proveedor)');
call403($vend, \App\Livewire\Ventas\Index::class,     'aprobar',          [1],  'Ventas.aprobar (aprobar_ventas)');
call403($vend, \App\Livewire\Ventas\Index::class,     'rechazar',         [1],  'Ventas.rechazar (aprobar_ventas)');
call403($vend, \App\Livewire\Compras\Index::class,    'aprobar',          [1],  'Compras.aprobar (aprobar_compras)');
call403($vend, \App\Livewire\Compras\Index::class,    'recibir',          [1],  'Compras.recibir (aprobar_compras)');

echo "\n== 2) usuario CON permiso NO recibe 403 ==\n";
callOK($adminL, \App\Livewire\Proveedores\Index::class, 'abrir', [$provId], 'Proveedores.abrir como admin_local');

echo "\n== 3) render OK como super_admin (blade con @puede compila) ==\n";
foreach ([\App\Livewire\Usuarios\Index::class, \App\Livewire\Proveedores\Index::class] as $c) {
    renderOK($dueno, $c, class_basename($c));
}

echo "\n== 4) render OK como vendedor (botones ocultos, @puede/@else) ==\n";
foreach ([\App\Livewire\Usuarios\Index::class, \App\Livewire\Proveedores\Index::class] as $c) {
    renderOK($vend, $c, class_basename($c));
}

echo "\n" . ($fail ? "❌ $fail fallo(s), $pass OK\n" : "✅ TODO OK — $pass checks\n");
