<?php

/**
 * Verificación del MÓDULO FISCAL: comprobantes (Factura A/B/C, NC, Recibo, OP),
 * numeración correlativa, IVA, y cuenta corriente con Saldo Vencido / A Vencer.
 *   php verify_fiscal.php
 * Todo lo que muta va en transacción con ROLLBACK: la DB queda intacta.
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\Cuota;
use App\Models\Devolucion;
use App\Models\MovimientoCliente;
use App\Models\PagoProveedor;
use App\Models\PedidoPago;
use App\Models\User;
use App\Models\Venta;
use App\Support\Comprobantes as Fiscal;
use App\Support\CuentaCorriente;
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

echo "\n== 1. Esquema ==\n";
$check('tabla comprobantes existe', fn () => Schema::hasTable('comprobantes') ?: 'no existe');
$check('clientes.tipo_iva e ingresos_brutos existen', function () {
    $faltan = array_filter(['tipo_iva', 'ingresos_brutos'], fn ($c) => ! Schema::hasColumn('clientes', $c));

    return $faltan ? 'faltan: ' . implode(', ', $faltan) : true;
});
$check('movimientos_cliente tiene comprobante_id y fecha_vencimiento', function () {
    $faltan = array_filter(['comprobante_id', 'fecha_vencimiento'], fn ($c) => ! Schema::hasColumn('movimientos_cliente', $c));

    return $faltan ? 'faltan: ' . implode(', ', $faltan) : true;
});
$check('el backfill dejó fecha_vencimiento en todos los movimientos viejos',
    fn () => MovimientoCliente::whereNull('fecha_vencimiento')->count() === 0 ?: MovimientoCliente::whereNull('fecha_vencimiento')->count() . ' sin vencimiento');

echo "\n== 2. Letra según condición de IVA ==\n";
$check('cliente Responsable Inscripto → Factura A', function () {
    $c = new Cliente(['tipo_iva' => 'responsable_inscripto']);

    return Fiscal::letraPara($c) === 'A' ?: 'dio ' . Fiscal::letraPara($c);
});
foreach (['monotributo', 'consumidor_final', 'exento'] as $cond) {
    $check("cliente {$cond} → Factura B", function () use ($cond) {
        $c = new Cliente(['tipo_iva' => $cond]);

        return Fiscal::letraPara($c) === 'B' ?: 'dio ' . Fiscal::letraPara($c);
    });
}
$check('si la EMPRESA es monotributista emite siempre C', function () {
    DB::beginTransaction();
    try {
        App\Models\Parametro::set('condicion_iva_empresa', 'monotributo');
        $letra = Fiscal::letraPara(new Cliente(['tipo_iva' => 'responsable_inscripto']));
        DB::rollBack();

        return $letra === 'C' ?: "dio {$letra}";
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

echo "\n== 3. Desagregado de IVA (precios finales) ==\n";
$check('total 1210 con IVA 21% → neto 1000 + iva 210', function () {
    $d = Fiscal::desagregar(1210, 21);

    return (abs($d['neto'] - 1000) < 0.01 && abs($d['iva'] - 210) < 0.01)
        ?: "neto={$d['neto']} iva={$d['iva']}";
});
$check('neto + iva siempre reconstruye el total', function () {
    foreach ([100, 1234.56, 99999.99, 0.01] as $t) {
        $d = Fiscal::desagregar($t, 21);
        if (abs($d['neto'] + $d['iva'] - $t) > 0.011) {
            return "con total {$t}: {$d['neto']}+{$d['iva']} != {$t}";
        }
    }

    return true;
});
$check('IVA 0% → todo neto', function () {
    $d = Fiscal::desagregar(500, 0);

    return (abs($d['neto'] - 500) < 0.01 && abs($d['iva']) < 0.01) ?: "neto={$d['neto']} iva={$d['iva']}";
});

echo "\n== 4. Numeración correlativa ==\n";
$check('los números avanzan de a uno por tipo+letra+punto de venta', function () {
    DB::beginTransaction();
    try {
        $cli = Cliente::first();
        $a = Fiscal::emitir('factura', ['letra' => 'B', 'cliente_id' => $cli->id, 'concepto' => 'ZZ test 1', 'total' => 100]);
        $b = Fiscal::emitir('factura', ['letra' => 'B', 'cliente_id' => $cli->id, 'concepto' => 'ZZ test 2', 'total' => 200]);
        $r = ($b->numero === $a->numero + 1);
        DB::rollBack();

        return $r ?: "primero {$a->numero}, segundo {$b->numero}";
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});
$check('cada letra lleva su propia serie', function () {
    DB::beginTransaction();
    try {
        $cli = Cliente::first();
        $a = Fiscal::emitir('factura', ['letra' => 'A', 'cliente_id' => $cli->id, 'concepto' => 'ZZ A', 'total' => 100]);
        $b = Fiscal::emitir('factura', ['letra' => 'B', 'cliente_id' => $cli->id, 'concepto' => 'ZZ B', 'total' => 100]);
        $maxA = (int) Comprobante::where('tipo', 'factura')->where('letra', 'A')->max('numero');
        $maxB = (int) Comprobante::where('tipo', 'factura')->where('letra', 'B')->max('numero');
        $r = ($a->numero === $maxA && $b->numero === $maxB);
        DB::rollBack();

        return $r ?: "A={$a->numero}/{$maxA} B={$b->numero}/{$maxB}";
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});
$check('el formato del número es 0001-00000001', function () {
    return Fiscal::formatoNumero(1, 1) === '0001-00000001' ?: 'dio ' . Fiscal::formatoNumero(1, 1);
});

echo "\n== 5. Emisión desde el circuito ==\n";
$check('aprobar una venta emite la FACTURA y la imputa a la cuenta corriente', function () {
    DB::beginTransaction();
    try {
        $v = Venta::where('estado', 'pendiente')->whereNotNull('cliente_id')->first();
        if (! $v) {
            DB::rollBack();

            return true;   // no hay ventas pendientes para probar
        }
        Livewire::test(App\Livewire\Ventas\Index::class)->call('aprobar', $v->id);
        $f = Comprobante::where('venta_id', $v->id)->where('tipo', 'factura')->first();
        $mov = MovimientoCliente::where('referencia', $v->numero)->where('tipo', 'debe')->latest('id')->first();
        $letraEsperada = Fiscal::letraPara($v->cliente);
        DB::rollBack();

        if (! $f) {
            return 'no se emitió factura';
        }
        if ($f->letra !== $letraEsperada) {
            return "letra {$f->letra}, esperaba {$letraEsperada}";
        }
        if (abs((float) $f->total - (float) $v->total) > 0.01) {
            return 'el total de la factura no coincide con la venta';
        }
        // Solo las ventas a crédito / cta cte generan movimiento (el contado se cobra al instante).
        if ($v->credito && (! $mov || $mov->comprobante_id !== $f->id)) {
            return 'el movimiento de cta cte no quedó linkeado al comprobante';
        }

        return true;
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage() . ' @ ' . $e->getLine();
    }
});

$check('no se factura dos veces la misma venta (idempotente)', function () {
    DB::beginTransaction();
    try {
        $v = Venta::whereNotNull('cliente_id')->first();
        $a = Fiscal::facturaDeVenta($v);
        $b = Fiscal::facturaDeVenta($v);
        $cant = Comprobante::where('venta_id', $v->id)->where('tipo', 'factura')->count();
        DB::rollBack();

        return ($a && $b && $a->id === $b->id && $cant === 1) ?: "se emitieron {$cant}";
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

$check('cobrar una cuota emite el RECIBO y lo linkea al haber', function () {
    DB::beginTransaction();
    try {
        $cuota = Cuota::where('estado', 'pendiente')->first();
        if (! $cuota) {
            DB::rollBack();

            return true;
        }
        $r = App\Support\Cobranza::cobrarCuota($cuota);
        $rec = Comprobante::where('cobro_id', $r['cobro_id'] ?? 0)->where('tipo', 'recibo')->first();
        $mov = MovimientoCliente::where('cliente_id', $cuota->cliente_id)->where('tipo', 'haber')->latest('id')->first();
        DB::rollBack();

        if (! $rec) {
            return 'no se emitió recibo';
        }
        if (! $mov || $mov->comprobante_id !== $rec->id) {
            return 'el haber no quedó linkeado al recibo';
        }

        return true;
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage() . ' @ ' . $e->getLine();
    }
});

$check('aprobar una devolución emite la NOTA DE CRÉDITO', function () {
    DB::beginTransaction();
    try {
        $d = Devolucion::where('estado', 'pendiente')->whereNotNull('cliente_id')->first();
        if (! $d) {
            DB::rollBack();

            return true;
        }
        Livewire::test(App\Livewire\Devoluciones\Index::class)->call('aprobar', $d->id);
        $nc = Comprobante::where('devolucion_id', $d->id)->where('tipo', 'nota_credito')->first();
        DB::rollBack();

        return ($nc && abs((float) $nc->total - (float) $d->monto) < 0.01) ?: 'no se emitió la NC o el monto no coincide';
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage() . ' @ ' . $e->getLine();
    }
});

$check('procesar un pago emite la ORDEN DE PAGO (sin IVA)', function () {
    DB::beginTransaction();
    try {
        $ob = PagoProveedor::whereIn('estado', ['pendiente', 'parcial'])->first();
        if (! $ob) {
            DB::rollBack();

            return true;
        }
        $p = Pagos::solicitar([
            'tipo' => 'proveedor', 'proveedor_id' => $ob->proveedor_id, 'obligacion_id' => $ob->id,
            'beneficiario' => 'ZZ Test', 'concepto' => 'ZZ pago de prueba', 'importe' => 100, 'medio' => 'efectivo',
        ], 1);
        Pagos::autorizar($p, 1);
        $r = Pagos::procesar($p->fresh(), 1);
        $op = Comprobante::where('pedido_pago_id', $p->id)->where('tipo', 'orden_pago')->first();
        DB::rollBack();

        if (! $op) {
            return 'no se emitió la OP';
        }
        if ((float) $op->iva != 0.0) {
            return 'la OP no debería tener IVA';
        }

        return ($r['ok'] ?? false) ?: 'procesar no dio ok';
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage() . ' @ ' . $e->getLine();
    }
});

echo "\n== 6. Cuenta corriente: Vencido / A vencer ==\n";
$check('INVARIANTE en datos reales: vencido + a vencer − a favor = saldo, para TODOS los clientes', function () {
    foreach (Cliente::pluck('id') as $id) {
        $r = CuentaCorriente::resumen($id);
        $calc = round($r['vencido'] + $r['a_vencer'] - $r['a_favor'], 2);
        if (abs($calc - $r['saldo']) > 0.02) {
            return "cliente {$id}: vencido {$r['vencido']} + a vencer {$r['a_vencer']} − a favor {$r['a_favor']} = {$calc} ≠ saldo {$r['saldo']}";
        }
    }

    return true;
});

$check('un crédito se enveje por sus CUOTAS (no por la fecha de la factura)', function () use ($hoy) {
    DB::beginTransaction();
    try {
        $v = Venta::where('credito', true)->whereHas('cuotas')->with('cuotas')->first();
        if (! $v || ! $v->cliente_id) {
            DB::rollBack();

            return true;
        }
        $r = CuentaCorriente::resumen($v->cliente_id);
        // Con cuotas vencidas e impagas, el vencido no puede ser cero.
        $vencidas = $v->cuotas->where('estado', 'pendiente')->filter(fn ($c) => $c->fecha_vencimiento->lt($hoy))->count();
        DB::rollBack();

        return ($vencidas === 0 || $r['vencido'] > 0) ?: "hay {$vencidas} cuotas vencidas pero vencido = 0";
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

$check('una deuda con vencimiento pasado cae en VENCIDO', function () {
    DB::beginTransaction();
    try {
        $cli = Cliente::create(['nombre' => 'ZZ Fiscal Test', 'tipo_doc' => 'DNI', 'documento' => '11111111', 'riesgo' => 'bajo', 'activo' => true, 'aprobado' => true]);
        MovimientoCliente::create(['cliente_id' => $cli->id, 'tipo' => 'debe', 'concepto' => 'ZZ deuda vieja', 'monto' => 1000,
            'fecha' => Carbon::today()->subDays(60), 'fecha_vencimiento' => Carbon::today()->subDays(30)]);
        $r = CuentaCorriente::resumen($cli->id);
        DB::rollBack();

        return (abs($r['vencido'] - 1000) < 0.01 && abs($r['a_vencer']) < 0.01)
            ?: "vencido={$r['vencido']} a_vencer={$r['a_vencer']}";
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

$check('una deuda a futuro cae en A VENCER', function () {
    DB::beginTransaction();
    try {
        $cli = Cliente::create(['nombre' => 'ZZ Fiscal Test 2', 'tipo_doc' => 'DNI', 'documento' => '22222222', 'riesgo' => 'bajo', 'activo' => true, 'aprobado' => true]);
        MovimientoCliente::create(['cliente_id' => $cli->id, 'tipo' => 'debe', 'concepto' => 'ZZ deuda futura', 'monto' => 800,
            'fecha' => Carbon::today(), 'fecha_vencimiento' => Carbon::today()->addDays(20)]);
        $r = CuentaCorriente::resumen($cli->id);
        DB::rollBack();

        return (abs($r['a_vencer'] - 800) < 0.01 && abs($r['vencido']) < 0.01)
            ?: "vencido={$r['vencido']} a_vencer={$r['a_vencer']}";
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

$check('el pago se imputa FIFO: cancela primero lo más viejo', function () {
    DB::beginTransaction();
    try {
        $cli = Cliente::create(['nombre' => 'ZZ Fiscal Test 3', 'tipo_doc' => 'DNI', 'documento' => '33333333', 'riesgo' => 'bajo', 'activo' => true, 'aprobado' => true]);
        MovimientoCliente::create(['cliente_id' => $cli->id, 'tipo' => 'debe', 'concepto' => 'ZZ vieja', 'monto' => 500,
            'fecha' => Carbon::today()->subDays(60), 'fecha_vencimiento' => Carbon::today()->subDays(40)]);
        MovimientoCliente::create(['cliente_id' => $cli->id, 'tipo' => 'debe', 'concepto' => 'ZZ futura', 'monto' => 700,
            'fecha' => Carbon::today(), 'fecha_vencimiento' => Carbon::today()->addDays(15)]);
        // Paga 500: debería cancelar la vieja (vencida) y dejar los 700 a vencer.
        MovimientoCliente::create(['cliente_id' => $cli->id, 'tipo' => 'haber', 'concepto' => 'ZZ pago', 'monto' => 500,
            'fecha' => Carbon::today(), 'fecha_vencimiento' => Carbon::today()]);
        $r = CuentaCorriente::resumen($cli->id);
        DB::rollBack();

        return (abs($r['vencido']) < 0.01 && abs($r['a_vencer'] - 700) < 0.01 && abs($r['saldo'] - 700) < 0.01)
            ?: "vencido={$r['vencido']} a_vencer={$r['a_vencer']} saldo={$r['saldo']}";
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

$check('pagar de más deja saldo A FAVOR (no vencido negativo)', function () {
    DB::beginTransaction();
    try {
        $cli = Cliente::create(['nombre' => 'ZZ Fiscal Test 4', 'tipo_doc' => 'DNI', 'documento' => '44444444', 'riesgo' => 'bajo', 'activo' => true, 'aprobado' => true]);
        MovimientoCliente::create(['cliente_id' => $cli->id, 'tipo' => 'debe', 'concepto' => 'ZZ deuda', 'monto' => 300,
            'fecha' => Carbon::today()->subDays(10), 'fecha_vencimiento' => Carbon::today()->subDays(5)]);
        MovimientoCliente::create(['cliente_id' => $cli->id, 'tipo' => 'haber', 'concepto' => 'ZZ pago grande', 'monto' => 500,
            'fecha' => Carbon::today(), 'fecha_vencimiento' => Carbon::today()]);
        $r = CuentaCorriente::resumen($cli->id);
        DB::rollBack();

        return (abs($r['vencido']) < 0.01 && abs($r['a_favor'] - 200) < 0.01 && abs($r['saldo'] + 200) < 0.01)
            ?: "vencido={$r['vencido']} a_favor={$r['a_favor']} saldo={$r['saldo']}";
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

$check('la grilla trae saldo acumulado y marca los vencidos', function () use ($hoy) {
    $cli = MovimientoCliente::value('cliente_id');
    $filas = CuentaCorriente::movimientos($cli, $hoy);
    if (empty($filas)) {
        return true;
    }
    $f = $filas[0];
    $faltan = array_filter(['fecha', 'comprobante', 'concepto', 'debe', 'haber', 'saldo', 'vencimiento', 'vencido'],
        fn ($k) => ! array_key_exists($k, $f));

    return $faltan ? 'faltan columnas: ' . implode(', ', $faltan) : true;
});

echo "\n== 7. Render ==\n";
$check('Comprobantes\Index renderiza los 5 tabs', function () {
    foreach (['todos', 'factura', 'nota_credito', 'recibo', 'orden_pago'] as $t) {
        $html = Livewire::test(App\Livewire\Comprobantes\Index::class)->call('setTab', $t)->html();
        if (strlen($html) < 800) {
            return "el tab {$t} renderizó vacío";
        }
    }

    return true;
});

$check('la ficha del cliente muestra Saldo vencido / A vencer', function () {
    $cli = MovimientoCliente::value('cliente_id');
    $html = Livewire::test(App\Livewire\Clientes\Index::class)->call('abrir', $cli)->call('setTab', 'cuenta')->html();

    return (str_contains($html, 'Saldo vencido') && str_contains($html, 'A vencer') && str_contains($html, 'F. Carga'))
        ? true : 'no aparecen los totales / la grilla nueva';
});

$check('la pestaña Comprobantes del cliente renderiza', function () {
    $cli = MovimientoCliente::value('cliente_id');
    $html = Livewire::test(App\Livewire\Clientes\Index::class)->call('abrir', $cli)->call('setTab', 'comprobantes')->html();

    return str_contains($html, 'Comprobante') ?: 'no renderiza la pestaña';
});

$check('el modal del cliente pide la condición de IVA', function () {
    $html = Livewire::test(App\Livewire\Clientes\Index::class)->call('nuevoCliente')->html();

    return str_contains($html, 'Condición de IVA') ?: 'no aparece el campo';
});

$check('Configuración muestra y guarda los parámetros fiscales', function () {
    DB::beginTransaction();
    try {
        $t = Livewire::test(App\Livewire\Configuracion\Index::class)->set('sub', 'parametros');
        $html = $t->html();
        if (! str_contains($html, 'Datos fiscales de la empresa')) {
            DB::rollBack();

            return 'no aparece el panel fiscal';
        }
        $t->set('fiscPuntoVenta', '7')->set('fiscIvaPct', '10.5')->call('guardarFiscal');
        $pv = Fiscal::puntoVenta();
        $iva = Fiscal::ivaPct();
        DB::rollBack();

        return ($pv === 7 && abs($iva - 10.5) < 0.01) ?: "pv={$pv} iva={$iva}";
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

$check('el PDF del comprobante se genera', function () {
    DB::beginTransaction();
    try {
        $cli = Cliente::first();
        $c = Fiscal::emitir('factura', ['letra' => 'A', 'cliente_id' => $cli->id, 'concepto' => 'ZZ PDF', 'total' => 1210, 'fecha_vencimiento' => Carbon::today()->addDays(30)]);
        $c->load('cliente', 'proveedor', 'venta.items.producto', 'emisor:id,name');
        $pdf = Barryvdh\DomPDF\Facade\Pdf::loadView('comprobantes.pdf', Fiscal::datosPdf($c))->setPaper('a4', 'portrait')->output();
        DB::rollBack();

        return str_starts_with($pdf, '%PDF') ?: 'no salió un PDF válido';
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage() . ' @ ' . $e->getLine();
    }
});

echo "\n== 8. Anulación ==\n";
$check('anular deja el número usado y desvincula el movimiento', function () {
    DB::beginTransaction();
    try {
        $cli = Cliente::first();
        $c = Fiscal::emitir('factura', ['letra' => 'B', 'cliente_id' => $cli->id, 'concepto' => 'ZZ anular', 'total' => 100]);
        $m = MovimientoCliente::create(['cliente_id' => $cli->id, 'tipo' => 'debe', 'concepto' => 'ZZ anular', 'monto' => 100,
            'fecha' => Carbon::today(), 'fecha_vencimiento' => Carbon::today(), 'comprobante_id' => $c->id]);
        Fiscal::anular($c, 'Error de carga', 1);
        $sig = Fiscal::emitir('factura', ['letra' => 'B', 'cliente_id' => $cli->id, 'concepto' => 'ZZ siguiente', 'total' => 100]);
        $movFin = MovimientoCliente::find($m->id);
        $estado = Comprobante::find($c->id)->estado;
        DB::rollBack();

        if ($estado !== 'anulado') {
            return "estado {$estado}";
        }
        if ($sig->numero !== $c->numero + 1) {
            return 'el número se reutilizó';
        }
        if ($movFin->comprobante_id !== null) {
            return 'el movimiento sigue linkeado';
        }

        return true;
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

$check('los anulados no suman en los totales del período', function () {
    DB::beginTransaction();
    try {
        $cli = Cliente::first();
        $c = Fiscal::emitir('factura', ['letra' => 'B', 'cliente_id' => $cli->id, 'concepto' => 'ZZ suma', 'total' => 12345, 'fecha' => Carbon::today()]);
        $antes = Livewire::test(App\Livewire\Comprobantes\Index::class)->get('totales') ?? null;
        $t1 = Livewire::test(App\Livewire\Comprobantes\Index::class);
        $conEl = $t1->viewData('totales')['facturado'];
        Fiscal::anular($c, 'test', 1);
        $sinEl = Livewire::test(App\Livewire\Comprobantes\Index::class)->viewData('totales')['facturado'];
        DB::rollBack();

        return abs(($conEl - $sinEl) - 12345) < 0.01 ?: "con={$conEl} sin={$sinEl}";
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

echo "\n────────────────────────────────\n";
echo ($fail === 0 ? '✅ TODO OK' : '❌ HAY FALLAS') . " — {$ok} ok · {$fail} fallas\n";
