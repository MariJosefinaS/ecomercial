<?php
/**
 * Verificación FINAL Bloque 1 — dropdown zona→cobrador, eliminar/baja usuario, scoping de cobranza.
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Livewire\Cobranza\Index as Cobranza;
use App\Livewire\Usuarios\Index as Usuarios;
use App\Livewire\Ventas\Nueva;
use App\Models\Cliente;
use App\Models\User;
use App\Models\Zona;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

$fail = 0;
$check = function (string $label, bool $ok, string $extra = '') use (&$fail) {
    echo ($ok ? 'OK    ' : 'ERROR ') . str_pad($label, 56) . $extra . "\n";
    if (! $ok) { $fail++; }
};

$dueno   = User::where('email', 'dueno@ecomercial.com')->first();
// Cobrador de Rioja Este resuelto por la ZONA (el asignado cambia: hoy es Josefina).
$marcos = \App\Models\Zona::where('nombre', 'like', '%Rioja Este%')->first()?->cobrador
    ?? User::whereHas('zonasComoCobrador')->first();
$ricardo = User::where('name', 'Ricardo Mendes')->first();     // cobrador de Distritos Chilecito (0 cuotas)
$riojaEste = Zona::where('nombre', 'Rioja Este')->first();

// ===== 1. Dropdown zona → auto-cobrador (Nota de Pedido) =====
Auth::login($dueno);
try {
    $comp = Livewire::test(Nueva::class)
        ->set('planCodigo', 'd30_020')
        ->set('paso', 3)
        ->set('zonaId', $riojaEste->id);
    // El cobrador esperado es el que la zona tenga asignado HOY (cambia con las reasignaciones).
    $esperado = $riojaEste->cobrador?->name;
    $check('elegir zona auto-completa el cobrador', $comp->get('cobrador') === $esperado, $comp->get('cobrador') . " (esperado: {$esperado})");
    $check('elegir zona setea el nombre de zona', $comp->get('zonaCobranza') === 'Rioja Este');
    $check('render wizard con select de zona', str_contains($comp->html(), 'wire:model.live="zonaId"'));
} catch (\Throwable $e) {
    $check('dropdown zona→cobrador', false, $e->getMessage());
}

// Integración: confirmar una venta a crédito con zona → venta.zona_id + cliente adopta zona.
DB::beginTransaction();
try {
    $cli = Cliente::whereNull('zona_id')->where('aprobado', true)->first()
        ?? Cliente::create(['nombre' => 'Cliente QA', 'tipo_doc' => 'DNI', 'documento' => '20111222', 'aprobado' => true]);
    $prod = \App\Models\Producto::whereHas('stock', fn ($q) => $q->where('cantidad', '>', 0))->first();
    $local = \App\Models\Local::where('activo', true)->first();

    $v = Livewire::test(Nueva::class)
        ->set('vLocal', $local->nombre)
        ->set('cliId', $cli->id)->set('cliNombre', $cli->nombre)
        ->set('items', [['producto_id' => $prod->id, 'cod' => $prod->codigo, 'desc' => $prod->nombre, 'cant' => 1, 'precio' => '100000', 'sugerido' => false]])
        ->set('planCodigo', 'd30_020')->set('plazo', 20)->set('anticipo', 30000)->set('cuota', 5000)
        ->set('zonaId', $riojaEste->id)
        ->set('fechaPrimeraCuota', now()->addDay()->toDateString())
        ->call('confirmar');
    $venta = \App\Models\Venta::where('cliente_id', $cli->id)->latest('id')->first();
    $check('venta creada guarda zona_id', $venta && $venta->zona_id === $riojaEste->id, 'zona_id=' . ($venta->zona_id ?? 'null'));
    $check('cliente adopta la zona de su venta', Cliente::find($cli->id)->zona_id === $riojaEste->id);
} catch (\Throwable $e) {
    $check('integración confirmar con zona', false, $e->getMessage() . ' @ ' . $e->getLine());
} finally {
    DB::rollBack();
}

// ===== 2. Eliminar / baja de usuario =====
// 2a. usuario sin historial → se elimina
DB::beginTransaction();
try {
    Auth::login($dueno);
    $u1 = User::create(['name' => 'QA Borrable', 'email' => 'qa_borrable@x.com', 'password' => Hash::make('password'), 'rol' => 'vendedor', 'activo' => true]);
    Livewire::test(Usuarios::class)->call('eliminarUsuario', $u1->id);
    $check('usuario sin historial → eliminado', ! User::find($u1->id));
} catch (\Throwable $e) {
    $check('eliminar usuario sin historial', false, $e->getMessage());
} finally { DB::rollBack(); }

// 2b. usuario cobrador de una zona → se elimina y la zona queda sin cobrador
DB::beginTransaction();
try {
    Auth::login($dueno);
    $u2 = User::create(['name' => 'QA Cobrador', 'email' => 'qa_cob@x.com', 'password' => Hash::make('password'), 'rol' => 'vendedor', 'activo' => true]);
    $z = Zona::create(['nombre' => 'Zona QA Del', 'cobrador_id' => $u2->id, 'activo' => true]);
    Livewire::test(Usuarios::class)->call('eliminarUsuario', $u2->id);
    $check('eliminar cobrador → zona queda sin cobrador', ! User::find($u2->id) && Zona::find($z->id)->cobrador_id === null);
} catch (\Throwable $e) {
    $check('eliminar cobrador de zona', false, $e->getMessage());
} finally { DB::rollBack(); }

// 2c. usuario con historial (ventas) → cae a baja (activo=false), no se borra
DB::beginTransaction();
try {
    Auth::login($dueno);
    Livewire::test(Usuarios::class)->call('eliminarUsuario', $ricardo->id);
    $r = User::find($ricardo->id);
    $check('usuario con historial → baja (no borrado)', $r && $r->activo === false, $r ? ('activo=' . var_export($r->activo, true)) : 'borrado');
} catch (\Throwable $e) {
    $check('baja por historial', false, $e->getMessage());
} finally { DB::rollBack(); }

// 2d. no se puede borrar el propio usuario logueado (protege también al super_admin)
Auth::login($dueno);
$msg = Livewire::test(Usuarios::class)->call('eliminarUsuario', $dueno->id)->get('mensaje');
$check('no borra tu propio usuario', User::find($dueno->id) !== null && str_contains((string) $msg, 'propio'), (string) $msg);

// ===== 3. Tablero de SUPERVISIÓN de cobranza =====
// Desde 2026-07-20 el tablero vive en Tesorería y exige `supervisar_cobranza`:
// el cobrador de calle NO entra (su pantalla es "Mi planilla", cubierta en verify_bloque2).
Auth::login($dueno);
$aDueno = count(Livewire::test(Cobranza::class)->viewData('atrasadas'));
$check('dueño (global) ve cuotas atrasadas', $aDueno > 0, "atrasadas=$aDueno");

Auth::login($marcos);
try {
    Livewire::test(Cobranza::class)->assertForbidden();
    $check('el cobrador NO accede al tablero de supervisión (403)', true);
} catch (\Throwable $e) {
    $check('el cobrador NO accede al tablero de supervisión (403)', false, $e->getMessage());
}
Auth::login($dueno);
$check('el supervisor sí renderiza el tablero', strlen(Livewire::test(Cobranza::class)->html()) > 800);

echo $fail ? "\n❌ {$fail} con error\n" : "\n✅ BLOQUE 1 COMPLETO — dropdown + eliminar/baja usuario + scoping\n";
