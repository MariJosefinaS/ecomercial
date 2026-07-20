<?php

/**
 * Verificación R4 — fecha de inicio de la 1ª cuota configurable.
 *  1. cronograma() pone la cuota Nº1 EXACTO en la fecha dada (diario y semanal).
 *  2. primeraCuotaPorDefecto() = hoy + 1 período.
 *  3. Render del wizard Ventas\Nueva (vista compila con el input nuevo).
 *  4. Integración: aprobar una venta a crédito con fecha_primera_cuota futura
 *     genera el cronograma arrancando en esa fecha (transacción + rollback).
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Livewire\Ventas\Index as VentasIndex;
use App\Models\Cliente;
use App\Models\Cuota;
use App\Models\Local;
use App\Models\Producto;
use App\Models\StockLocal;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Support\PlanesCredito;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

$fail = 0;
$check = function (string $label, bool $ok, string $extra = '') use (&$fail) {
    echo ($ok ? 'OK    ' : 'ERROR ') . str_pad($label, 52) . $extra . "\n";
    if (! $ok) {
        $fail++;
    }
};

// ---------- 1. cronograma() diario ----------
$calc = PlanesCredito::calcular('d30_020', 120000, 20); // 20 cuotas diarias
$inicio = Carbon::create(2026, 8, 1);
$cron = PlanesCredito::cronograma($calc, $inicio->copy());
$check('diario · cuota Nº1 vence en la fecha dada', $cron[0]['fecha_vencimiento']->isSameDay($inicio), $cron[0]['fecha_vencimiento']->toDateString());
$check('diario · cuota Nº2 = +1 día', $cron[1]['fecha_vencimiento']->isSameDay($inicio->copy()->addDay()));
$check('diario · última cuota = fecha + (n-1) días', $cron[19]['fecha_vencimiento']->isSameDay($inicio->copy()->addDays(19)));
$sum = round(array_sum(array_column($cron, 'monto')), 2);
$check('diario · suma de cuotas = total financiado', abs($sum - (float) $calc['total_financiado']) < 0.01, "\$$sum vs \${$calc['total_financiado']}");

// ---------- 2. cronograma() semanal ----------
$calcS = PlanesCredito::calcular('m5_13s', 120000, 13);
$cronS = PlanesCredito::cronograma($calcS, $inicio->copy());
$check('semanal · cuota Nº1 vence en la fecha dada', $cronS[0]['fecha_vencimiento']->isSameDay($inicio));
$check('semanal · cuota Nº2 = +1 semana', $cronS[1]['fecha_vencimiento']->isSameDay($inicio->copy()->addWeek()));

// ---------- 3. primeraCuotaPorDefecto ----------
$check('default diario = hoy + 1 día', PlanesCredito::primeraCuotaPorDefecto('d30_020')->isSameDay(Carbon::today()->addDay()));
$check('default semanal = hoy + 1 semana', PlanesCredito::primeraCuotaPorDefecto('m5_13s')->isSameDay(Carbon::today()->addWeek()));

// ---------- 4. Render del wizard ----------
$dueno = User::where('email', 'dueno@ecomercial.com')->first();
Auth::login($dueno);
try {
    $html = Livewire::test(VentasIndex::class)->html(); // no rompe listado
    $wizComp = Livewire::test(\App\Livewire\Ventas\Nueva::class)
        ->set('planCodigo', 'd30_020')   // dispara updatedPlanCodigo → prefill de la fecha
        ->set('paso', 3);                // paso Plan (donde vive el input)
    $wiz = $wizComp->html();
    $check('wizard render + input fecha 1ª cuota presente', str_contains($wiz, 'fechaPrimeraCuota'));
    $check('wizard · fecha prefilleada = hoy + 1 día', $wizComp->get('fechaPrimeraCuota') === Carbon::today()->addDay()->toDateString(), (string) $wizComp->get('fechaPrimeraCuota'));
} catch (\Throwable $e) {
    $check('wizard render', false, $e->getMessage());
}

// ---------- 5. Integración: aprobar con fecha futura ----------
DB::beginTransaction();
try {
    $cli = Cliente::where('aprobado', true)->first();
    $local = Local::where('activo', true)->orderBy('id')->first();
    $prod = Producto::whereHas('stock', fn ($q) => $q->where('local_id', $local->id)->where('cantidad', '>', 0))->first();
    $futura = Carbon::today()->addDays(10);

    $venta = Venta::create([
        'numero' => 'TEST-R4', 'local_id' => $local->id, 'vendedor_id' => $dueno->id,
        'cliente_id' => $cli->id, 'cliente_nombre' => $cli->nombre, 'medio_pago' => 'Pago diario',
        'credito' => true, 'fecha' => now(), 'total' => 120000, 'estado' => 'pendiente',
        'plan_codigo' => 'd30_020', 'plan_nombre' => 'test', 'modalidad' => 'diario',
        'anticipo' => 36000, 'saldo_financiado' => 84000, 'plazo' => 20, 'cuota' => 5000,
        'fecha_primera_cuota' => $futura, 'zona_cobranza' => 'Test', 'cobrador' => 'Test',
    ]);
    VentaItem::create(['venta_id' => $venta->id, 'producto_id' => $prod->id, 'cantidad' => 1, 'precio_unitario' => 120000]);

    Livewire::test(VentasIndex::class)->call('aprobar', $venta->id);

    $venta->refresh();
    $cuotas = Cuota::where('venta_id', $venta->id)->orderBy('numero')->get();
    $check('aprobación · se generaron 20 cuotas', $cuotas->count() === 20, (string) $cuotas->count());
    $check('aprobación · cuota Nº1 vence en la fecha elegida', $cuotas->first() && $cuotas->first()->fecha_vencimiento->isSameDay($futura), optional($cuotas->first())->fecha_vencimiento?->toDateString());
    $check('aprobación · venta quedó aprobada', $venta->estado === 'aprobada');
} catch (\Throwable $e) {
    $check('integración aprobar', false, $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
}
DB::rollBack();

echo $fail ? "\n❌ {$fail} con error\n" : "\n✅ R4 OK — fecha de 1ª cuota configurable\n";
