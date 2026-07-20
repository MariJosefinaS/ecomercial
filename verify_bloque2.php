<?php
/**
 * Verificación Bloque 2 — Mi planilla del cobrador (por modalidad, apertura/cierre, auditoría,
 * cobro, export CSV, impresión, scoping).
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Livewire\Cobranza\Planilla;
use App\Models\PlanillaCobranza;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

$fail = 0;
$check = function (string $label, bool $ok, string $extra = '') use (&$fail) {
    echo ($ok ? 'OK    ' : 'ERROR ') . str_pad($label, 56) . $extra . "\n";
    if (! $ok) { $fail++; }
};

$dueno   = User::where('email', 'dueno@ecomercial.com')->first();
$marcos  = User::where('name', 'Marcos Lima')->first();      // Rioja Este (20 cuotas diarias)
$ricardo = User::where('name', 'Ricardo Mendes')->first();    // Distritos Chilecito (0 cuotas)
$hoy = \Illuminate\Support\Carbon::today()->toDateString();

// 1. Render como cobrador: ve su planilla, grupo 'diario'
Auth::login($marcos);
try {
    $comp = Livewire::test(Planilla::class);
    $grupos = $comp->viewData('grupos');
    $check('cobrador ve su planilla (grupo diario)', collect($grupos)->contains(fn ($g) => $g['modalidad'] === 'diario'), 'grupos=' . collect($grupos)->pluck('modalidad')->implode(','));
    $g = collect($grupos)->firstWhere('modalidad', 'diario');
    $check('planilla lista cuotas del día', $g && count($g['filas']) > 0, 'filas=' . ($g ? count($g['filas']) : 0));
    $check('totales esperado > 0', $g && $g['esperado'] > 0, '$' . ($g['esperado'] ?? 0));
    $check('estado inicial = sin_abrir', $g && $g['estado'] === 'sin_abrir');
} catch (\Throwable $e) {
    $check('render planilla cobrador', false, $e->getMessage() . ' @ ' . $e->getLine());
}

// 2. Ciclo abrir → cerrar → auditar (rollback)
DB::beginTransaction();
try {
    Auth::login($marcos);
    Livewire::test(Planilla::class)->call('abrir', 'diario');
    $p = PlanillaCobranza::where('cobrador_id', $marcos->id)->whereDate('fecha', $hoy)->where('modalidad', 'diario')->first();
    $check('abrir crea planilla (en_confeccion + hora)', $p && $p->estado === 'en_confeccion' && $p->hora_apertura !== null);

    Livewire::test(Planilla::class)->call('cerrar', 'diario');
    $p->refresh();
    $check('cerrar → pend_auditoria + hora_cierre + total', $p->estado === 'pend_auditoria' && $p->hora_cierre !== null && (float) $p->total_esperado > 0);

    // cobrador NO puede auditar (no tiene permiso) → sigue pend_auditoria
    Livewire::test(Planilla::class)->call('auditar', 'diario');
    $check('cobrador NO puede auditar', PlanillaCobranza::find($p->id)->estado === 'pend_auditoria');

    // admin audita
    Auth::login($dueno);
    Livewire::test(Planilla::class)->set('cobradorId', $marcos->id)->call('auditar', 'diario');
    $p->refresh();
    $check('admin audita → cerrada + auditor', $p->estado === 'cerrada' && $p->auditada_por === $dueno->id);
} catch (\Throwable $e) {
    $check('ciclo apertura/cierre/auditoría', false, $e->getMessage() . ' @ ' . $e->getLine());
} finally { DB::rollBack(); }

// 3. Cobrar una cuota mueve el "cobrado" (rollback)
DB::beginTransaction();
try {
    Auth::login($marcos);
    $g0 = collect(Livewire::test(Planilla::class)->viewData('grupos'))->firstWhere('modalidad', 'diario');
    $fila = collect($g0['filas'])->firstWhere('cobrada', false);   // una cuota AÚN no cobrada
    $cobradoAntes = $g0['cobrado'];
    Livewire::test(Planilla::class)->call('cobrar', $fila['id']);
    $cobradoDespues = collect(Livewire::test(Planilla::class)->viewData('grupos'))->firstWhere('modalidad', 'diario')['cobrado'];
    $check('cobrar aumenta el total cobrado', $cobradoDespues > $cobradoAntes, "antes=$cobradoAntes despues=$cobradoDespues");
} catch (\Throwable $e) {
    $check('cobrar en planilla', false, $e->getMessage() . ' @ ' . $e->getLine());
} finally { DB::rollBack(); }

// 4. Export CSV devuelve un StreamedResponse
Auth::login($marcos);
try {
    $resp = Livewire::test(Planilla::class)->call('exportarCsv', 'diario')->effects['download'] ?? null;
    // El download de Livewire viene en effects; alternativamente, invocamos el método directo:
    $direct = (new Planilla());
    $direct->cobradorId = $marcos->id; $direct->fecha = $hoy;
    $r = $direct->exportarCsv('diario');
    $check('exportarCsv devuelve descarga', $r instanceof \Symfony\Component\HttpFoundation\StreamedResponse);
} catch (\Throwable $e) {
    $check('export CSV', false, $e->getMessage() . ' @ ' . $e->getLine());
}

// 5. Vista de impresión renderiza
try {
    $cuotas = \App\Support\Planilla::cuotasDelDia($marcos->id, \Illuminate\Support\Carbon::today());
    $html = view('cobranza.planilla-imprimir', [
        'filas' => \App\Support\Planilla::filas($cuotas, \Illuminate\Support\Carbon::today(), 'diario'),
        'tot' => \App\Support\Planilla::totales($cuotas, \Illuminate\Support\Carbon::today(), 'diario'),
        'cobrador' => $marcos, 'fecha' => \Illuminate\Support\Carbon::today(), 'modalidad' => 'diario',
    ])->render();
    $check('vista de impresión renderiza (cupón f_030)', str_contains($html, 'Planilla de cobros') && str_contains($html, 'Firma del cobrador'));
} catch (\Throwable $e) {
    $check('impresión', false, $e->getMessage());
}

// 6. Scoping: Ricardo (0 cuotas) no ve nada
Auth::login($ricardo);
$gr = Livewire::test(Planilla::class)->viewData('grupos');
$check('Ricardo (sin cuotas) ve planilla vacía', count($gr) === 0);

echo $fail ? "\n❌ {$fail} con error\n" : "\n✅ BLOQUE 2 OK — planilla por modalidad + apertura/cierre + auditoría + cobro + CSV + impresión + scoping\n";
