<?php
/**
 * Verificación Bloque 1 (data foundation) — Zonas + cobrador por zona.
 *   1. Zonas sembradas con cobrador.
 *   2. Cuota resuelve su cobrador ACTUAL vía la zona.
 *   3. Reasignar el cobrador de la zona mueve las cuotas (requisito 8) — con rollback.
 *   4. User::esCobrador() detecta al cobrador.
 *   5. Render de Cobranza / Ventas\Nueva / Clientes (nada se rompió con las nuevas columnas/relaciones).
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Cuota;
use App\Models\User;
use App\Models\Zona;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

$fail = 0;
$check = function (string $label, bool $ok, string $extra = '') use (&$fail) {
    echo ($ok ? 'OK    ' : 'ERROR ') . str_pad($label, 54) . $extra . "\n";
    if (! $ok) { $fail++; }
};

// 1. zonas
$check('4 zonas sembradas', Zona::count() === 4, (string) Zona::count());
$re = Zona::where('nombre', 'Rioja Este')->with('cobrador')->first();
$check('Rioja Este tiene cobrador', $re && $re->cobrador, $re?->cobrador?->name ?? '—');

// 2. cuota → cobrador actual vía zona
$cuota = Cuota::where('zona_id', $re->id)->first();
$check('cuota linkeada a zona resuelve cobrador', $cuota && $cuota->cobradorActual()?->id === $re->cobrador_id, $cuota?->cobradorActual()?->name ?? '—');

// 3. reasignar cobrador mueve las cuotas (rollback)
DB::beginTransaction();
try {
    $otro = User::where('name', 'Ricardo Mendes')->first();
    $re->update(['cobrador_id' => $otro->id]);
    $cuota->refresh()->load('zonaRel.cobrador');
    $check('reasignar zona → cuota sigue al nuevo cobrador', $cuota->cobradorActual()?->id === $otro->id, $cuota->cobradorActual()?->name ?? '—');
} finally {
    DB::rollBack();
}

// 4. esCobrador — se resuelve por la ZONA (el cobrador asignado cambia con el tiempo).
$conZona = \App\Models\Zona::whereNotNull('cobrador_id')->first()?->cobrador;
$check('quien tiene una zona asignada → esCobrador() = true', (bool) $conZona?->esCobrador(), $conZona?->name ?? 'nadie');
$check('Dueño esCobrador() = false', ! User::where('name', 'Dueño')->first()->esCobrador());

// 5. render
$dueno = User::where('email', 'dueno@ecomercial.com')->first();
foreach ([
    \App\Livewire\Cobranza\Index::class,
    \App\Livewire\Ventas\Nueva::class,
    \App\Livewire\Clientes\Index::class,
    \App\Livewire\Configuracion\Index::class,
] as $cls) {
    Auth::login($dueno);
    try {
        $len = strlen(Livewire::test($cls)->html());
        $check('render ' . class_basename(str_replace('\\', '/', $cls)), $len > 100, "({$len} bytes)");
    } catch (\Throwable $e) {
        $check('render ' . class_basename(str_replace('\\', '/', $cls)), false, $e->getMessage());
    }
}

echo $fail ? "\n❌ {$fail} con error\n" : "\n✅ Bloque 1 (data foundation) OK\n";
