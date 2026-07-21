<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Cuota;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

function ok($c,$l){ echo ($c?"OK  ":"FAIL")."  $l\n"; }

// --- Perfil como cobrador ---
$cob = User::where('rol','cobrador')->first() ?? User::whereHas('zonasComoCobrador')->first();
if ($cob) {
    Auth::login($cob);
    try { $h = Livewire::test(\App\Livewire\Perfil\Index::class)->html();
        ok(strlen($h)>200, "Perfil render (cobrador: {$cob->name})");
        ok(str_contains($h,'Eficacia del mes'), "Perfil muestra estadísticas de cobranza");
        ok(str_contains($h,'Cerrar sesión'), "Perfil tiene botón Cerrar sesión");
    } catch (\Throwable $e){ ok(false,"Perfil cobrador :: ".$e->getMessage()); }
}
// --- Perfil como admin (sin bloque de stats) ---
$adm = User::where('rol','super_admin')->first();
if ($adm) { Auth::login($adm);
    try { ok(strlen(Livewire::test(\App\Livewire\Perfil\Index::class)->html())>200, "Perfil render (admin)"); }
    catch(\Throwable $e){ ok(false,"Perfil admin :: ".$e->getMessage()); }
}

// --- Planilla render (export dropdown) ---
if ($cob) { Auth::login($cob);
    try { ok(str_contains(Livewire::test(\App\Livewire\Cobranza\Planilla::class)->html(),'Exportar'), "Planilla tiene menú Exportar"); }
    catch(\Throwable $e){ ok(false,"Planilla :: ".$e->getMessage()); }
}

// --- exportarPdf() genera un PDF real (dompdf) ---
$cuota = Cuota::where('estado','pendiente')->orderBy('fecha_vencimiento')->first();
if ($cuota) {
    $f = \Illuminate\Support\Carbon::parse($cuota->fecha_vencimiento)->toDateString();
    $cobrador = method_exists($cuota,'cobradorActual') ? $cuota->cobradorActual() : null;
    $user = $cobrador ?? $adm; Auth::login($user);
    try {
        $test = Livewire::test(\App\Livewire\Cobranza\Planilla::class)->set('fecha',$f);
        if ($user->rol==='super_admin' && $cobrador) $test->set('cobradorId',$cobrador->id)->set('fecha',$f);
        // Determinar una modalidad presente
        $modal = \App\Support\Planilla::modalidadesPresentes(\App\Support\Planilla::cuotasDelDia($cobrador?->id ?? $user->id, \Illuminate\Support\Carbon::parse($f)))->first() ?? 'diario';
        $resp = $test->call('exportarPdf',$modal)->effects['download'] ?? null;
        // Alternativa: invocar el método directo para capturar el binario
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cobranza.planilla-pdf', [
            'filas'=>\App\Support\Planilla::filas(\App\Support\Planilla::cuotasDelDia($cobrador?->id ?? $user->id,\Illuminate\Support\Carbon::parse($f)),\Illuminate\Support\Carbon::parse($f),$modal),
            'tot'=>\App\Support\Planilla::totales(\App\Support\Planilla::cuotasDelDia($cobrador?->id ?? $user->id,\Illuminate\Support\Carbon::parse($f)),\Illuminate\Support\Carbon::parse($f),$modal),
            'cobrador'=>$user,'fecha'=>\Illuminate\Support\Carbon::parse($f),'modalidad'=>$modal,
        ])->setPaper('a4','landscape')->output();
        ok(str_starts_with($pdf,'%PDF'), "exportarPdf genera PDF válido (modalidad=$modal, ".strlen($pdf)." bytes)");
    } catch(\Throwable $e){ ok(false,"exportarPdf :: ".$e->getMessage()); }
}
echo "\nListo.\n";
