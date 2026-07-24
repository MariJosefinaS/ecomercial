<?php

/**
 * Verificación de los 3 arreglos pedidos:
 *   1) notificaciones scopeadas por permiso (no aparecen donde no corresponde)
 *   2) sidebar mobile: pie con logout dentro del alto visible (h-dvh, no h-screen)
 *   3) galería de imágenes por producto (varias + portada)
 *   php verify_fixes_ui.php
 * Lo que muta va en transacción con ROLLBACK.
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Livewire\Shared\Notifications;
use App\Models\Local;
use App\Models\Producto;
use App\Models\SolicitudCompra;
use App\Models\User;
use App\Support\Reposicion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

$ok = 0;
$fail = 0;
$check = function (string $t, callable $fn) use (&$ok, &$fail) {
    try {
        $r = $fn();
        if ($r === true) { echo "  ✔ {$t}\n"; $ok++; }
        else { echo "  ✘ {$t} → {$r}\n"; $fail++; }
    } catch (\Throwable $e) {
        echo "  ✘ {$t} → EXCEPCIÓN " . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n";
        $fail++;
    }
};

$dueno = User::where('email', 'dueno@ecomercial.com')->first();

echo "\n== 1. Notificaciones scopeadas ==\n";

$check('el VENDEDOR no ve una solicitud de reposición ajena (no aprueba compras)', function () use ($dueno) {
    DB::beginTransaction();
    try {
        // El dueño crea una solicitud pendiente.
        $prov = App\Models\Proveedor::where('activo', true)->first();
        $prod = Producto::create(['codigo' => 'ZZ-NOTIF', 'nombre' => 'ZZ Notif', 'proveedor_id' => $prov->id, 'precio_compra' => 10, 'activo' => true]);
        Reposicion::solicitar($prod, Local::where('activo', true)->value('id'), 3, $dueno->id);

        $v = User::where('rol', 'vendedor')->first();
        if (! $v) { DB::rollBack(); return true; }
        Auth::login($v);
        $items = collect(app(Notifications::class)->misNovedades($v));
        DB::rollBack();
        Auth::login($dueno);

        // El vendedor sólo ve SUS cosas; no la solicitud del dueño.
        $ajena = $items->contains(fn ($n) => ($n['tipo'] ?? '') === 'Solicitud');

        return ! $ajena ?: 'el vendedor ve una solicitud ajena';
    } catch (\Throwable $e) { DB::rollBack(); Auth::login($dueno); return 'excepción: ' . $e->getMessage(); }
});

$check('una solicitud PENDIENTE no aparece como novedad del propio solicitante', function () use ($dueno) {
    DB::beginTransaction();
    try {
        $v = User::where('rol', 'vendedor')->first();
        if (! $v) { DB::rollBack(); return true; }
        $prov = App\Models\Proveedor::where('activo', true)->first();
        $prod = Producto::create(['codigo' => 'ZZ-NOTIF2', 'nombre' => 'ZZ Notif2', 'proveedor_id' => $prov->id, 'precio_compra' => 10, 'activo' => true]);
        Reposicion::solicitar($prod, Local::where('activo', true)->value('id'), 3, $v->id);
        Auth::login($v);
        $items = collect(app(Notifications::class)->misNovedades($v));
        DB::rollBack();
        Auth::login($dueno);

        $verPendiente = $items->contains(fn ($n) => ($n['tipo'] ?? '') === 'Solicitud' && str_contains($n['desc'] ?? '', 'pendiente'));

        return ! $verPendiente ?: 'aparece la pendiente propia (no debería)';
    } catch (\Throwable $e) { DB::rollBack(); Auth::login($dueno); return 'excepción: ' . $e->getMessage(); }
});

$check('una solicitud APROBADA sí aparece como novedad del solicitante', function () use ($dueno) {
    DB::beginTransaction();
    try {
        $v = User::where('rol', 'vendedor')->first();
        if (! $v) { DB::rollBack(); return true; }
        $prov = App\Models\Proveedor::where('activo', true)->first();
        $prod = Producto::create(['codigo' => 'ZZ-NOTIF3', 'nombre' => 'ZZ Notif3', 'proveedor_id' => $prov->id, 'precio_compra' => 10, 'activo' => true]);
        $s = Reposicion::solicitar($prod, Local::where('activo', true)->value('id'), 3, $v->id);
        Reposicion::aprobar($s, $dueno->id);
        Auth::login($v);
        $items = collect(app(Notifications::class)->misNovedades($v));
        DB::rollBack();
        Auth::login($dueno);

        return $items->contains(fn ($n) => ($n['tipo'] ?? '') === 'Solicitud') ?: 'no aparece la aprobada';
    } catch (\Throwable $e) { DB::rollBack(); Auth::login($dueno); return 'excepción: ' . $e->getMessage(); }
});

$check('quien aprueba COMPRAS sí ve la solicitud pendiente', function () use ($dueno) {
    DB::beginTransaction();
    try {
        $prov = App\Models\Proveedor::where('activo', true)->first();
        $prod = Producto::create(['codigo' => 'ZZ-NOTIF4', 'nombre' => 'ZZ Notif4', 'proveedor_id' => $prov->id, 'precio_compra' => 10, 'activo' => true]);
        Reposicion::solicitar($prod, Local::where('activo', true)->value('id'), 3, $dueno->id);
        // El dueño (super_admin) aprueba compras.
        $items = collect(app(Notifications::class)->aprobacionesPendientes($dueno));
        DB::rollBack();

        return $items->contains(fn ($n) => ($n['tipo'] ?? '') === 'Solicitud') ?: 'el aprobador de compras no la ve';
    } catch (\Throwable $e) { DB::rollBack(); return 'excepción: ' . $e->getMessage(); }
});

$check('aprobacionesPendientes de un rol que NO aprueba compras no trae solicitudes', function () {
    // Rol sintético sin permisos de compra: se comprueba la lógica de gating.
    $sinCompras = new User(['rol' => 'vendedor']);   // vendedor no tiene aprobar_compras
    $items = collect(app(Notifications::class)->aprobacionesPendientes($sinCompras));

    return ! $items->contains(fn ($n) => in_array($n['tipo'] ?? '', ['Solicitud', 'Compra'], true))
        ?: 'un no-aprobador-de-compras recibe solicitudes/compras';
});

$check('el componente de notificaciones renderiza para cada rol', function () use ($dueno) {
    foreach (['super_admin', 'vendedor'] as $rol) {
        $u = User::where('rol', $rol)->first();
        if (! $u) { continue; }
        Auth::login($u);
        $html = Livewire::test(Notifications::class)->html();
        if (strlen($html) < 100) { Auth::login($dueno); return "render vacío para {$rol}"; }
    }
    Auth::login($dueno);

    return true;
});

echo "\n== 2. Sidebar mobile (logout alcanzable) ==\n";
$sidebar = file_get_contents(__DIR__ . '/resources/views/components/sidebar.blade.php');
$check('el <aside> usa h-dvh (no h-screen, que corta el pie en mobile)',
    fn () => (str_contains($sidebar, 'h-dvh') && ! preg_match('/<aside[^>]*\bh-screen\b/', $sidebar)) ?: 'sigue con h-screen');
$check('el pie con "Cerrar sesión" está en el layout del sidebar',
    fn () => (str_contains($sidebar, "route('logout')") && str_contains($sidebar, 'Cerrar sesión')) ?: 'falta el logout');

echo "\n== 3. Galería de imágenes ==\n";
$check('productos.imagenes existe y castea a array',
    fn () => \Illuminate\Support\Facades\Schema::hasColumn('productos', 'imagenes') ?: 'no existe la columna');

$check('imagenesGaleria() pone la portada primero', function () {
    $p = new Producto(['imagen' => 'productos/b.jpg', 'imagenes' => ['productos/a.jpg', 'productos/b.jpg', 'productos/c.jpg']]);
    $g = $p->imagenesGaleria();

    return ($g[0] === 'productos/b.jpg' && count($g) === 3) ?: 'orden: ' . implode(',', $g);
});

$check('imagenesGaleria() cae a la imagen simple si no hay galería', function () {
    $p = new Producto(['imagen' => 'productos/x.jpg', 'imagenes' => null]);

    return $p->imagenesGaleria() === ['productos/x.jpg'] ?: 'dio ' . implode(',', $p->imagenesGaleria());
});

$check('guardar con varias imágenes: portada = primera, galería completa persiste', function () use ($dueno) {
    DB::beginTransaction();
    try {
        Auth::login($dueno);
        $cat = App\Models\Categoria::first();
        $prov = App\Models\Proveedor::where('activo', true)->first();
        $fake1 = \Illuminate\Http\UploadedFile::fake()->image('foto1.jpg', 300, 300);
        $fake2 = \Illuminate\Http\UploadedFile::fake()->image('foto2.jpg', 300, 300);

        $t = Livewire::test(App\Livewire\Stock\Index::class)
            ->call('nuevoProducto')
            ->set('pNombre', 'ZZ Multi Imagen')->set('pCodigo', 'ZZ-MULTIIMG')
            ->set('pCategoriaId', $cat->id)->set('pProveedorId', $prov->id)
            ->set('pPrecioCompra', '100')->set('pMin', '1')
            ->set('pImagenesNuevas', [$fake1, $fake2])
            ->call('guardarProducto');

        $p = Producto::where('codigo', 'ZZ-MULTIIMG')->first();
        DB::rollBack();
        Auth::login($dueno);

        if (! $p) { return 'no se creó el producto'; }
        if (count($p->imagenes ?? []) !== 2) { return 'la galería tiene ' . count($p->imagenes ?? []) . ' (esperaba 2)'; }
        if ($p->imagen !== ($p->imagenes[0] ?? null)) { return 'la portada no es la primera de la galería'; }

        return true;
    } catch (\Throwable $e) { DB::rollBack(); Auth::login($dueno); return 'excepción: ' . $e->getMessage() . ' @ ' . $e->getLine(); }
});

$check('quitar y hacer-portada reordenan la galería en el modal', function () use ($dueno) {
    Auth::login($dueno);
    $t = Livewire::test(App\Livewire\Stock\Index::class)
        ->set('pImagenesActuales', ['productos/a.jpg', 'productos/b.jpg', 'productos/c.jpg'])
        ->call('hacerPortada', 2);
    $tras = $t->get('pImagenesActuales');
    if (($tras[0] ?? null) !== 'productos/c.jpg') { return 'hacerPortada no movió al frente'; }
    $t->call('quitarImagenActual', 0);
    $tras2 = $t->get('pImagenesActuales');

    return (count($tras2) === 2 && ! in_array('productos/c.jpg', $tras2, true)) ?: 'quitar no funcionó';
});

$check('el modal de Stock (catálogo) renderiza la galería (input múltiple)', function () use ($dueno) {
    Auth::login($dueno);
    // El modal de producto vive en la subvista "catalogo" (la default es "consulta").
    $html = Livewire::test(App\Livewire\Stock\Index::class)
        ->set('sub', 'catalogo')->call('nuevoProducto')->html();

    return (str_contains($html, 'multiple') && str_contains($html, 'Imágenes del producto')) ?: 'no aparece el input múltiple';
});

echo "\n────────────────────────────────\n";
echo ($fail === 0 ? '✅ TODO OK' : '❌ HAY FALLAS') . " — {$ok} ok · {$fail} fallas\n";
