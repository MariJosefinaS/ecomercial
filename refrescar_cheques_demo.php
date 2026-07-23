<?php

/**
 * Re-fecha los cheques DEMO del seeder relativo a HOY, para que la cartera y el
 * calendario se vean vivos (el seeder original quedó fechado el día que se corrió).
 * Solo toca los cheques del seeder (números conocidos). NO toca datos cargados a mano.
 *   php refrescar_cheques_demo.php
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Cheque;
use App\Models\ChequeCliente;
use Illuminate\Support\Carbon;

$hoy = Carbon::today();

// [número => días desde hoy para el VENCIMIENTO]
$terceros = ['C-77231' => -1, 'C-90050' => 1, 'C-88200' => 14];
$propios = ['CH-5001' => 0, 'CH-5002' => 1, 'CH-5003' => 6];

foreach ($terceros as $num => $offset) {
    $ch = ChequeCliente::where('numero', $num)->first();
    if (! $ch) {
        continue;
    }
    $venc = $hoy->copy()->addDays($offset);
    $ch->update(['fecha_vencimiento' => $venc, 'fecha_deposito' => ChequeCliente::calcularDeposito($venc)]);
    echo "✔ {$num} (terceros) → vence {$venc->format('d/m/Y')} · deposita {$ch->fresh()->fecha_deposito->format('d/m/Y')}\n";
}

foreach ($propios as $num => $offset) {
    $ch = Cheque::where('numero', $num)->first();
    if (! $ch) {
        continue;
    }
    $venc = $hoy->copy()->addDays($offset);
    $ch->update(['fecha_vencimiento' => $venc, 'fecha_emision' => $hoy->copy()->subDays(5)]);
    echo "✔ {$num} (propio) → se debita {$venc->format('d/m/Y')}\n";
}

$k = App\Support\Cartera::kpis($hoy);
echo "\nEn cartera: \${$k['cartera_monto']} ({$k['cartera_cant']}) · A depositar hoy: \${$k['depositar_hoy']}"
    . " · Mañana entra \${$k['manana_ingreso']} / sale \${$k['manana_egreso']}\n";
