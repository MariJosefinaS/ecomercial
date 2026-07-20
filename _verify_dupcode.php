<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Livewire\Recepcion\Index;
use App\Models\{Compra,Producto,User};
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
Auth::login(User::where('email','deposito@ecomercial.com')->first());
$compra = Compra::with('items')->whereHas('items')->get()->first(fn($c)=>$c->tienePendiente());
if (!$compra) { echo "❌ No hay compra con pendiente\n"; exit(1); }
echo "Compra: {$compra->numero} (la validación retorna antes de la transacción, no muta)\n";
$existente = Producto::whereNotNull('codigo')->first();
$t = Livewire::test(Index::class)->call('abrir',$compra->id);
$t->set('extras', [[
    'codigo'=>$existente->codigo,'descripcion'=>'DUP','cantidad'=>1,'costo'=>'0','candidatos'=>[],'sugerido_id'=>null,
    'destino'=>'agregar','item_id'=>null,'prod_sel'=>'nuevo','nuevo_nombre'=>'DUP TEST','nuevo_codigo'=>$existente->codigo,'nota'=>'',
]]);
$t->call('confirmarRecepcion');
$err = $t->errors();
echo ($err->has('extras.0.nuevo_codigo') ? "✅ Código duplicado bloqueado (sin 500): " . $err->first('extras.0.nuevo_codigo') : "❌ No bloqueó el código duplicado") . "\n";
echo (Producto::where('nombre','DUP TEST')->doesntExist() ? "✅ No creó el producto duplicado\n" : "❌ Creó el producto igual\n");
