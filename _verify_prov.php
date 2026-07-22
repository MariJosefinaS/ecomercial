<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\User; use App\Models\Compra; use App\Models\Proveedor; use App\Models\PagoProveedor; use App\Models\MovimientoCaja;
use Illuminate\Support\Facades\Auth; use Illuminate\Support\Facades\DB; use Livewire\Livewire;
function ok($c,$l){ echo ($c?"OK  ":"FAIL")."  $l\n"; }
$adm = User::where('rol','super_admin')->first(); Auth::login($adm);

DB::beginTransaction();
try {
    // Compra recibida SIN factura → no debe generar deuda
    $prov = Proveedor::first();
    $compra = Compra::create(['numero'=>'OC-TEST','proveedor_id'=>$prov->id,'local_id'=>\App\Models\Local::value('id'),'total'=>50000,'estado'=>'recibida','fecha'=>now()]);
    $deudaAntes = PagoProveedor::where('proveedor_id',$prov->id)->get()->sum(fn($p)=>max(0,(float)$p->monto-(float)$p->monto_pagado));

    // 1) Cargar factura → genera obligación (deuda)
    $t = Livewire::test(\App\Livewire\Proveedores\Index::class)
        ->call('pedirCargarFactura',$compra->id)
        ->set('facNumero','A-0001-999')->set('facVencimiento',now()->addDays(30)->toDateString())
        ->call('cargarFactura');
    $ob = PagoProveedor::where('compra_id',$compra->id)->first();
    ok($ob && (float)$ob->monto==50000.0, "Cargar factura → obligación creada por 50000 (deuda nace con la factura, no el remito)");
    ok($compra->fresh()->factura_numero==='A-0001-999', "compra.factura_numero seteado");

    // 2) No duplica obligación
    $t->call('pedirCargarFactura',$compra->id)->set('facNumero','X')->set('facVencimiento',now()->toDateString())->call('cargarFactura');
    ok(PagoProveedor::where('compra_id',$compra->id)->count()===1, "No duplica obligación de la misma compra");

    // 3) Registrar pago parcial → egreso caja + baja saldo
    $egAntes = (float) MovimientoCaja::where('tipo','egreso')->sum('monto');
    Livewire::test(\App\Livewire\Proveedores\Index::class)
        ->call('pedirPagoProveedor',$ob->id)->set('pagoMonto','20000')->set('pagoMedio','transferencia')
        ->call('registrarPagoProveedor');
    $ob->refresh();
    ok((float)$ob->monto_pagado==20000.0 && $ob->estado==='parcial', "Pago parcial: monto_pagado=20000, estado parcial");
    $egDesp = (float) MovimientoCaja::where('tipo','egreso')->sum('monto');
    ok(abs(($egDesp-$egAntes)-20000)<0.01, "Egreso en caja por el pago = 20000");

    // 4) No se puede pagar más que el saldo
    $t2 = Livewire::test(\App\Livewire\Proveedores\Index::class)->call('pedirPagoProveedor',$ob->id)->set('pagoMonto','999999')->call('registrarPagoProveedor');
    $t2->assertHasErrors('pagoMonto');
    ok(true, "No permite pagar más que el saldo (validación)");

    // 5) Pago del resto → estado pagado
    Livewire::test(\App\Livewire\Proveedores\Index::class)->call('pedirPagoProveedor',$ob->id)->set('pagoMonto','30000')->call('registrarPagoProveedor');
    ok($ob->fresh()->estado==='pagado', "Pago total → estado pagado");

    // 6) Ficha render con obligaciones + cargar factura
    $h = Livewire::test(\App\Livewire\Proveedores\Index::class)->call('abrir',$prov->id)->set('tab','pagos')->html();
    ok(str_contains($h,'Facturas a pagar'), "Ficha proveedor: sección 'Facturas a pagar' render");

    DB::rollBack(); echo "\n(rollback OK)\n";
} catch(\Throwable $e){ DB::rollBack(); ok(false,$e->getMessage()." @ ".basename($e->getFile()).":".$e->getLine()); }
echo "\nListo.\n";
