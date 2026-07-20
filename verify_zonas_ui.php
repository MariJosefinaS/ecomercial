<?php
/**
 * Verificación Bloque 1 (UI) — Configuración → Zonas + modalidad mensual.
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Livewire\Configuracion\Index as Config;
use App\Models\User;
use App\Models\Zona;
use App\Support\PlanesCredito;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

$fail = 0;
$check = function (string $label, bool $ok, string $extra = '') use (&$fail) {
    echo ($ok ? 'OK    ' : 'ERROR ') . str_pad($label, 52) . $extra . "\n";
    if (! $ok) { $fail++; }
};

$dueno = User::where('email', 'dueno@ecomercial.com')->first();
Auth::login($dueno);

// 1. render del panel zonas
try {
    $comp0 = Livewire::test(Config::class)->set('sub', 'zonas');
    $html = $comp0->html();
    $check('render Configuración → Zonas', str_contains($html, 'Zonas de cobranza') && str_contains($html, 'Agregar zona'));
    $nombres = collect($comp0->get('zonas'))->pluck('nombre');
    $check('lista incluye las zonas sembradas', $nombres->contains('Rioja Este') && $nombres->contains('Distritos Chilecito'), $nombres->implode(', '));
    $check('muestra cobrador asignado', str_contains($html, 'Marcos Lima'));
} catch (\Throwable $e) {
    $check('render Configuración → Zonas', false, $e->getMessage());
}

// 2. CRUD zona (agregar → reasignar → eliminar) con rollback
DB::beginTransaction();
try {
    $marcos = User::where('name', 'Marcos Lima')->first();
    $ricardo = User::where('name', 'Ricardo Mendes')->first();
    $antes = Zona::count();

    Livewire::test(Config::class)
        ->set('sub', 'zonas')
        ->set('nuevaZonaNombre', 'Zona QA')
        ->set('nuevaZonaCobrador', $marcos->id)
        ->call('agregarZona');
    $z = Zona::where('nombre', 'Zona QA')->first();
    $check('agregarZona crea la zona con cobrador', $z && $z->cobrador_id === $marcos->id, $z?->cobrador?->name ?? '—');

    // reasignar cobrador vía guardarZonas
    $comp = Livewire::test(Config::class)->set('sub', 'zonas');
    $idx = collect($comp->get('zonas'))->search(fn ($r) => $r['id'] === $z->id);
    $comp->set("zonas.$idx.cobrador_id", $ricardo->id)->call('guardarZonas');
    $check('guardarZonas reasigna el cobrador', Zona::find($z->id)->cobrador_id === $ricardo->id, Zona::find($z->id)->cobrador?->name ?? '—');

    Livewire::test(Config::class)->set('sub', 'zonas')->call('eliminarZona', $z->id);
    $check('eliminarZona borra la zona', Zona::count() === $antes && ! Zona::find($z->id));
} catch (\Throwable $e) {
    $check('CRUD zona', false, $e->getMessage() . ' @ ' . $e->getLine());
} finally {
    DB::rollBack();
}

// 3. modalidad mensual
$d = Carbon::create(2026, 8, 10);
$check('sumarPeriodos mensual = +N meses', PlanesCredito::sumarPeriodos($d, 'mensual', 3)->isSameDay(Carbon::create(2026, 11, 10)));
$calcM = ['plazo' => 6, 'total_financiado' => 6000, 'saldo' => 6000, 'modalidad' => 'mensual'];
$cronM = PlanesCredito::cronograma($calcM, $d->copy());
$check('cronograma mensual · cuota Nº2 = +1 mes', $cronM[1]['fecha_vencimiento']->isSameDay($d->copy()->addMonth()));
$check('cronograma mensual · cuota Nº6 = +5 meses', $cronM[5]['fecha_vencimiento']->isSameDay($d->copy()->addMonths(5)));
$sumM = round(array_sum(array_column($cronM, 'monto')), 2);
$check('cronograma mensual · suma cuadra', abs($sumM - 6000) < 0.01, "\$$sumM");

echo $fail ? "\n❌ {$fail} con error\n" : "\n✅ Bloque 1 (UI) + mensual OK\n";
