<?php

/**
 * Verificación de la CARTERA DE CHEQUES (propios + terceros, "para mañana", endoso).
 * Renderiza de verdad con Livewire::test()->html().
 *   php verify_cheques.php
 * Todo lo que muta va en transacción con ROLLBACK: la DB queda intacta.
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Cheque;
use App\Models\ChequeCliente;
use App\Models\Cliente;
use App\Models\MovimientoCaja;
use App\Models\PagoProveedor;
use App\Models\PedidoPago;
use App\Models\Proveedor;
use App\Models\User;
use App\Support\Cartera;
use App\Support\Pagos;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

$ok = 0;
$fail = 0;
$check = function (string $titulo, callable $fn) use (&$ok, &$fail) {
    try {
        $r = $fn();
        if ($r === true) {
            echo "  ✔ {$titulo}\n";
            $ok++;
        } else {
            echo "  ✘ {$titulo} → {$r}\n";
            $fail++;
        }
    } catch (\Throwable $e) {
        echo "  ✘ {$titulo} → EXCEPCIÓN " . get_class($e) . ': ' . $e->getMessage() . "\n      " . $e->getFile() . ':' . $e->getLine() . "\n";
        $fail++;
    }
};

$dueno = User::where('email', 'dueno@ecomercial.com')->first();
Auth::login($dueno);
$hoy = Carbon::today();

echo "\n== 1. Esquema del endoso ==\n";
$check('cheques_cliente.endosado_a_proveedor_id existe', fn () => Schema::hasColumn('cheques_cliente', 'endosado_a_proveedor_id') ?: 'no existe');
$check('cheques_cliente.endosado_at existe', fn () => Schema::hasColumn('cheques_cliente', 'endosado_at') ?: 'no existe');
$check('pedidos_pago.cheque_cliente_id existe', fn () => Schema::hasColumn('pedidos_pago', 'cheque_cliente_id') ?: 'no existe');
$check("el enum de estado acepta 'endosado'", function () {
    $col = DB::selectOne("SHOW COLUMNS FROM cheques_cliente LIKE 'estado'");

    return str_contains($col->Type, 'endosado') ?: 'tipo: ' . $col->Type;
});

echo "\n== 2. Cálculos de la cartera ==\n";
$check('kpis() devuelve todas las claves', function () use ($hoy) {
    $k = Cartera::kpis($hoy);
    $faltan = array_filter(['cartera_cant', 'cartera_monto', 'depositar_hoy', 'manana_ingreso', 'manana_egreso', 'propios_monto'],
        fn ($c) => ! array_key_exists($c, $k));

    return $faltan ? 'faltan: ' . implode(', ', $faltan) : true;
});

$check('"en cartera" = solo los pendientes (no depositados/rechazados)', function () {
    $enCartera = ChequeCliente::enCartera()->pluck('estado')->unique()->all();

    return ($enCartera === [] || $enCartera === ['pendiente']) ?: 'trajo estados: ' . implode(',', $enCartera);
});

$check('el KPI "para mañana" cuenta lo que vence mañana', function () use ($hoy) {
    DB::beginTransaction();
    try {
        $manana = $hoy->copy()->addDay();
        $antes = Cartera::kpis($hoy)['manana_egreso'];
        Cheque::create(['numero' => 'ZZ-MAÑ', 'banco' => 'Test', 'proveedor_id' => Proveedor::value('id'), 'monto' => 1234.56, 'fecha_vencimiento' => $manana, 'estado' => 'pendiente']);
        $despues = Cartera::kpis($hoy)['manana_egreso'];
        DB::rollBack();

        return abs(($despues - $antes) - 1234.56) < 0.01 ?: "antes={$antes} después={$despues}";
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

$check('el calendario agrupa por día y suma ingresos/egresos', function () use ($hoy) {
    $cal = Cartera::calendario(30, $hoy);
    if (empty($cal)) {
        return true;   // sin cheques pendientes no hay días con movimiento
    }
    $d = $cal[0];
    $sumaIng = round(array_sum(array_column($d['ingresos'], 'monto')), 2);

    return abs($sumaIng - $d['total_ingreso']) < 0.01 ?: "total_ingreso={$d['total_ingreso']} vs suma={$sumaIng}";
});

$check('los cheques atrasados caen en el día de HOY', function () use ($hoy) {
    DB::beginTransaction();
    try {
        ChequeCliente::create(['cliente_id' => Cliente::value('id'), 'numero' => 'ZZ-VIEJO', 'banco' => 'Test', 'monto' => 999, 'fecha_vencimiento' => $hoy->copy()->subDays(20), 'fecha_deposito' => $hoy->copy()->subDays(19), 'estado' => 'pendiente']);
        $cal = Cartera::calendario(30, $hoy);
        $diaHoy = collect($cal)->firstWhere(fn ($d) => $d['fecha']->isToday());
        $esta = collect($diaHoy['ingresos'] ?? [])->firstWhere('numero', 'ZZ-VIEJO');
        DB::rollBack();

        return ($esta && $esta['atrasado'] === true) ?: 'no apareció en HOY marcado como atrasado';
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

echo "\n== 3. Render de la pantalla ==\n";
foreach (['cartera', 'propios', 'calendario'] as $t) {
    $check("tab «{$t}» renderiza", function () use ($t) {
        $html = Livewire::test(App\Livewire\Tesoreria\Cheques::class)->call('setTab', $t)->html();

        return strlen($html) > 800 ?: 'html sospechosamente corto';
    });
}

$check('la pantalla muestra la alerta "Cheques para MAÑANA" si hay', function () use ($hoy) {
    $k = Cartera::kpis($hoy);
    $html = Livewire::test(App\Livewire\Tesoreria\Cheques::class)->html();
    $hay = $k['manana_egreso_cant'] > 0 || $k['manana_ingreso_cant'] > 0;

    return $hay ? (str_contains($html, 'Cheques para MAÑANA') ?: 'hay cheques mañana pero no salió la alerta') : true;
});

$check('los modales de alta abren con sus campos', function () {
    $h1 = Livewire::test(App\Livewire\Tesoreria\Cheques::class)->call('nuevoTercero')->html();
    $h2 = Livewire::test(App\Livewire\Tesoreria\Cheques::class)->call('nuevoPropio')->html();

    return (str_contains($h1, 'Ingresar cheque recibido') && str_contains($h2, 'Emitir cheque propio'))
        ? true : 'falta alguno de los dos modales';
});

$check('el alta valida los campos obligatorios', function () {
    Livewire::test(App\Livewire\Tesoreria\Cheques::class)
        ->call('nuevoTercero')->set('tNumero', '')->set('tMonto', '')
        ->call('guardarTercero')
        ->assertHasErrors(['tCliente', 'tNumero', 'tMonto', 'tVencimiento']);

    return true;
});

echo "\n== 4. Operaciones (transacción + rollback) ==\n";
$check('ingresar cheque de terceros calcula el depósito (venc + 1 hábil)', function () use ($hoy) {
    DB::beginTransaction();
    try {
        $venc = $hoy->copy()->addDays(10);
        Livewire::test(App\Livewire\Tesoreria\Cheques::class)
            ->call('nuevoTercero')
            ->set('tCliente', Cliente::value('id'))->set('tNumero', 'ZZ-NUEVO')
            ->set('tBanco', 'Banco Test')->set('tMonto', '5000')->set('tVencimiento', $venc->toDateString())
            ->call('guardarTercero');
        $ch = ChequeCliente::where('numero', 'ZZ-NUEVO')->first();
        $esperado = ChequeCliente::calcularDeposito($venc)->toDateString();
        $dio = $ch?->fecha_deposito?->toDateString();
        DB::rollBack();

        return ($ch && $dio === $esperado) ?: "depósito dio {$dio}, esperaba {$esperado}";
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

$check('depositar un cheque registra el INGRESO en caja por el monto exacto', function () {
    DB::beginTransaction();
    try {
        $ch = ChequeCliente::enCartera()->first();
        if (! $ch) {
            DB::rollBack();

            return true;
        }
        $antes = (float) MovimientoCaja::where('tipo', 'ingreso')->sum('monto');
        Livewire::test(App\Livewire\Tesoreria\Cheques::class)->call('depositar', $ch->id);
        $despues = (float) MovimientoCaja::where('tipo', 'ingreso')->sum('monto');
        $estado = ChequeCliente::find($ch->id)->estado;
        $delta = round($despues - $antes, 2);
        $monto = round((float) $ch->monto, 2);
        DB::rollBack();

        return ($estado === 'depositado' && abs($delta - $monto) < 0.01) ?: "estado={$estado} delta={$delta} monto={$monto}";
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

$check('rechazar un cheque YA depositado revierte el ingreso en caja', function () {
    DB::beginTransaction();
    try {
        $ch = ChequeCliente::enCartera()->first();
        if (! $ch) {
            DB::rollBack();

            return true;
        }
        $t = Livewire::test(App\Livewire\Tesoreria\Cheques::class)->call('depositar', $ch->id);
        $egAntes = (float) MovimientoCaja::where('tipo', 'egreso')->sum('monto');
        $t->call('pedirRechazo', $ch->id)->set('motivoRechazo', 'Sin fondos')->call('rechazar');
        $egDespues = (float) MovimientoCaja::where('tipo', 'egreso')->sum('monto');
        $estado = ChequeCliente::find($ch->id)->estado;
        $delta = round($egDespues - $egAntes, 2);
        $monto = round((float) $ch->monto, 2);
        DB::rollBack();

        return ($estado === 'rechazado' && abs($delta - $monto) < 0.01) ?: "estado={$estado} egresoDelta={$delta} monto={$monto}";
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

$check('debitar un cheque propio registra el EGRESO en caja', function () {
    DB::beginTransaction();
    try {
        $ch = Cheque::where('estado', 'pendiente')->first();
        if (! $ch) {
            DB::rollBack();

            return true;
        }
        $antes = (float) MovimientoCaja::where('tipo', 'egreso')->sum('monto');
        Livewire::test(App\Livewire\Tesoreria\Cheques::class)->call('debitar', $ch->id);
        $despues = (float) MovimientoCaja::where('tipo', 'egreso')->sum('monto');
        $estado = Cheque::find($ch->id)->estado;
        $delta = round($despues - $antes, 2);
        DB::rollBack();

        return ($estado === 'cobrado' && abs($delta - round((float) $ch->monto, 2)) < 0.01) ?: "estado={$estado} delta={$delta}";
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

echo "\n== 5. ENDOSO a proveedor (no mueve caja) ==\n";
$check('solicitar endoso crea un pedido de pago PENDIENTE de autorización', function () {
    DB::beginTransaction();
    try {
        $ch = ChequeCliente::enCartera()->first();
        $ob = PagoProveedor::whereIn('estado', ['pendiente', 'parcial'])->first();
        if (! $ch || ! $ob) {
            DB::rollBack();

            return true;   // sin datos para probar
        }
        Livewire::test(App\Livewire\Tesoreria\Cheques::class)
            ->call('pedirEndoso', $ch->id)->set('eObligacion', $ob->id)->call('endosar');
        $p = PedidoPago::where('cheque_cliente_id', $ch->id)->first();
        $estadoCheque = ChequeCliente::find($ch->id)->estado;
        DB::rollBack();

        return ($p && $p->estado === 'pendiente' && $estadoCheque === 'pendiente')
            ? true
            : 'pedido=' . ($p?->estado ?? 'null') . ' chequeSigue=' . $estadoCheque;
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

$check('al PROCESAR el endoso: el cheque sale de la cartera, se imputa la deuda y NO se mueve la caja', function () {
    DB::beginTransaction();
    try {
        $ch = ChequeCliente::enCartera()->first();
        $ob = PagoProveedor::whereIn('estado', ['pendiente', 'parcial'])->first();
        if (! $ch || ! $ob) {
            DB::rollBack();

            return true;
        }
        $pagadoAntes = (float) $ob->monto_pagado;
        $cajaAntes = (float) MovimientoCaja::sum('monto');

        Livewire::test(App\Livewire\Tesoreria\Cheques::class)
            ->call('pedirEndoso', $ch->id)->set('eObligacion', $ob->id)->call('endosar');
        $p = PedidoPago::where('cheque_cliente_id', $ch->id)->firstOrFail();
        Pagos::autorizar($p, 1);
        $r = Pagos::procesar($p->fresh(), 1);

        $chFin = ChequeCliente::find($ch->id);
        $obFin = PagoProveedor::find($ob->id);
        $cajaDespues = (float) MovimientoCaja::sum('monto');
        $imputado = round((float) $obFin->monto_pagado - $pagadoAntes, 2);
        $esperado = round(min((float) $ch->monto, max(0, (float) $ob->monto - $pagadoAntes)), 2);

        DB::rollBack();

        $errores = [];
        if (! ($r['ok'] ?? false)) {
            $errores[] = 'procesar no dio ok: ' . ($r['mensaje'] ?? '');
        }
        if ($chFin->estado !== 'endosado') {
            $errores[] = "estado del cheque = {$chFin->estado}";
        }
        if ($chFin->endosado_a_proveedor_id !== $ob->proveedor_id) {
            $errores[] = 'no quedó registrado el proveedor del endoso';
        }
        if (abs($imputado - $esperado) > 0.01) {
            $errores[] = "imputado {$imputado} != esperado {$esperado}";
        }
        if (abs($cajaDespues - $cajaAntes) > 0.01) {
            $errores[] = 'la caja se movió (no debería): delta ' . round($cajaDespues - $cajaAntes, 2);
        }

        return $errores ? implode(' · ', $errores) : true;
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
    }
});

$check('no se puede pedir dos veces el endoso del mismo cheque', function () {
    DB::beginTransaction();
    try {
        $ch = ChequeCliente::enCartera()->first();
        $ob = PagoProveedor::whereIn('estado', ['pendiente', 'parcial'])->first();
        if (! $ch || ! $ob) {
            DB::rollBack();

            return true;
        }
        $t = Livewire::test(App\Livewire\Tesoreria\Cheques::class);
        $t->call('pedirEndoso', $ch->id)->set('eObligacion', $ob->id)->call('endosar');
        $t->call('pedirEndoso', $ch->id)->set('eObligacion', $ob->id)->call('endosar');
        $cant = PedidoPago::where('cheque_cliente_id', $ch->id)->count();
        DB::rollBack();

        return $cant === 1 ?: "se crearon {$cant} pedidos";
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

echo "\n== 6. Tesorería sigue andando tras sacarle los tabs de cheques ==\n";
$check('Tesorería\Index renderiza (resumen · caja · proyección)', function () {
    foreach (['resumen', 'caja', 'proyeccion'] as $t) {
        $html = Livewire::test(App\Livewire\Tesoreria\Index::class)->set('tab', $t)->html();
        if (strlen($html) < 500) {
            return "el tab {$t} renderizó vacío";
        }
    }

    return true;
});

$check('?sub=depositar redirige a la pantalla de Cheques', function () {
    Livewire::withQueryParams(['sub' => 'depositar'])
        ->test(App\Livewire\Tesoreria\Index::class)
        ->assertRedirect(route('tesoreria.cheques'));

    return true;
});

echo "\n────────────────────────────────\n";
echo ($fail === 0 ? '✅ TODO OK' : '❌ HAY FALLAS') . " — {$ok} ok · {$fail} fallas\n";
