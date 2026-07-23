<?php

/**
 * Verificación de DOMICILIOS MÚLTIPLES por cliente (entrega + cobro).
 * Renderiza de verdad con Livewire::test()->html() — view:cache NO detecta errores de runtime.
 *   php verify_domicilios.php
 * Las mutaciones van en transacción con ROLLBACK: la DB queda intacta.
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Cliente;
use App\Models\DomicilioCliente;
use App\Models\User;
use App\Models\Venta;
use App\Support\Planilla;
use Illuminate\Support\Carbon;
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
if (! $dueno) {
    exit("No está el usuario dueno@ecomercial.com\n");
}
Illuminate\Support\Facades\Auth::login($dueno);

echo "\n== 1. Esquema ==\n";
$check('tabla domicilios_cliente existe', fn () => Schema::hasTable('domicilios_cliente') ?: 'no existe');
$check('columnas clave presentes', function () {
    $faltan = array_filter(['cliente_id', 'etiqueta', 'direccion', 'localidad', 'provincia', 'referencia', 'contacto', 'telefono', 'zona_id', 'latitud', 'longitud', 'uso', 'es_principal', 'activo'],
        fn ($c) => ! Schema::hasColumn('domicilios_cliente', $c));

    return $faltan ? 'faltan: ' . implode(', ', $faltan) : true;
});
$check('ventas.domicilio_entrega_id existe', fn () => Schema::hasColumn('ventas', 'domicilio_entrega_id') ?: 'no existe');

echo "\n== 2. Lógica del modelo (en transacción, con rollback) ==\n";
DB::beginTransaction();
try {
    $cli = Cliente::first();

    $d1 = DomicilioCliente::create(['cliente_id' => $cli->id, 'etiqueta' => 'ZZ Test A', 'direccion' => 'Calle Falsa 123', 'localidad' => 'Springfield', 'provincia' => 'La Rioja', 'uso' => 'ambos', 'es_principal' => true]);
    $d2 = DomicilioCliente::create(['cliente_id' => $cli->id, 'etiqueta' => 'ZZ Test B', 'direccion' => 'Otra Calle 456', 'uso' => 'entrega']);

    $check('completa() arma dirección · localidad · provincia',
        fn () => $d1->completa() === 'Calle Falsa 123 · Springfield · La Rioja' ?: 'dio: ' . $d1->completa());

    $check('marcar principal sincroniza clientes.direccion',
        fn () => Cliente::find($cli->id)->direccion === 'Calle Falsa 123' ?: 'quedó: ' . Cliente::find($cli->id)->direccion);

    $d2->update(['es_principal' => true]);
    $check('un solo principal por cliente (el anterior se desmarca)', function () use ($cli, $d1) {
        $principales = DomicilioCliente::where('cliente_id', $cli->id)->where('es_principal', true)->count();
        $viejo = DomicilioCliente::find($d1->id)->es_principal;

        return ($principales === 1 && ! $viejo) ?: "principales={$principales} viejoSiguePrincipal=" . (int) $viejo;
    });

    $check('mapsUrl() sin geo busca por texto',
        fn () => str_contains($d1->fresh()->mapsUrl(), 'Calle+Falsa') ?: 'dio: ' . $d1->fresh()->mapsUrl());

    $d1->update(['latitud' => -29.1622, 'longitud' => -67.4966]);
    $check('mapsUrl() con geo usa coordenadas',
        fn () => str_contains($d1->fresh()->mapsUrl(), '29.1622') ?: 'dio: ' . $d1->fresh()->mapsUrl());

    $check('filtro por uso: entrega no incluye los "solo cobro"', function () use ($cli) {
        DomicilioCliente::create(['cliente_id' => $cli->id, 'etiqueta' => 'ZZ Solo cobro', 'direccion' => 'Cobro 1', 'uso' => 'cobro']);
        $entrega = DomicilioCliente::where('cliente_id', $cli->id)->activos()->whereIn('uso', ['ambos', 'entrega'])->pluck('etiqueta')->all();

        return ! in_array('ZZ Solo cobro', $entrega, true) ?: 'se coló el de solo cobro';
    });

    $check('domicilioDeCobro() ignora los "solo entrega"', function () use ($cli) {
        $c = Cliente::find($cli->id);
        $dom = $c->domicilioDeCobro();

        return ($dom && $dom->sirveParaCobro()) ?: 'devolvió: ' . ($dom?->etiqueta ?? 'null');
    });

    DB::rollBack();
    echo "  ↩ rollback OK (la DB quedó intacta)\n";
} catch (\Throwable $e) {
    DB::rollBack();
    echo '  ✘ EXCEPCIÓN en el bloque de lógica: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n";
    $fail++;
}

echo "\n== 3. Render — ficha del cliente, pestaña Domicilios ==\n";
$conDom = DomicilioCliente::query()->value('cliente_id');
$check('Clientes\Index renderiza la pestaña Domicilios', function () use ($conDom) {
    $html = Livewire::test(App\Livewire\Clientes\Index::class)
        ->call('abrir', $conDom)->call('setTab', 'domicilios')->html();

    return str_contains($html, 'Domicilios') && str_contains($html, 'Agregar domicilio')
        ? true : 'no aparece la pestaña / el botón';
});

$check('la pestaña lista los domicilios del cliente con etiqueta y uso', function () use ($conDom) {
    $etiqueta = DomicilioCliente::where('cliente_id', $conDom)->value('etiqueta');
    $html = Livewire::test(App\Livewire\Clientes\Index::class)
        ->call('abrir', $conDom)->call('setTab', 'domicilios')->html();

    return str_contains($html, e($etiqueta)) ?: "no aparece «{$etiqueta}»";
});

$check('el modal de alta de domicilio abre y trae el form', function () use ($conDom) {
    $html = Livewire::test(App\Livewire\Clientes\Index::class)
        ->call('abrir', $conDom)->call('setTab', 'domicilios')->call('nuevoDomicilio')->html();

    return (str_contains($html, 'Nuevo domicilio') && str_contains($html, 'Referencia (cómo llegar)') && str_contains($html, 'Zona de cobranza'))
        ? true : 'faltan campos en el modal';
});

$check('guardar domicilio valida los obligatorios', function () use ($conDom) {
    Livewire::test(App\Livewire\Clientes\Index::class)
        ->call('abrir', $conDom)->call('nuevoDomicilio')
        ->set('dEtiqueta', '')->set('dDireccion', '')
        ->call('guardarDomicilio')
        ->assertHasErrors(['dEtiqueta', 'dDireccion']);

    return true;
});

$check('alta + baja de domicilio desde la ficha (ciclo completo)', function () use ($conDom) {
    DB::beginTransaction();
    try {
        $antes = DomicilioCliente::where('cliente_id', $conDom)->count();
        $t = Livewire::test(App\Livewire\Clientes\Index::class)
            ->call('abrir', $conDom)->call('nuevoDomicilio')
            ->set('dEtiqueta', 'ZZ Casa de prueba')->set('dDireccion', 'Mitre 100')
            ->set('dLocalidad', 'Chilecito')->set('dUso', 'cobro')
            ->call('guardarDomicilio');
        $creado = DomicilioCliente::where('cliente_id', $conDom)->where('etiqueta', 'ZZ Casa de prueba')->first();
        if (! $creado) {
            DB::rollBack();

            return 'no se creó';
        }
        $t->call('eliminarDomicilio', $creado->id);
        $despues = DomicilioCliente::where('cliente_id', $conDom)->count();
        DB::rollBack();

        return $despues === $antes ?: "quedaron {$despues} en vez de {$antes}";
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

echo "\n== 4. Render — wizard de venta (domicilio de entrega) ==\n";
$check('Ventas\Nueva muestra el selector "¿Dónde se entrega?"', function () use ($conDom) {
    $html = Livewire::test(App\Livewire\Ventas\Nueva::class)->call('elegirCliente', $conDom)->html();

    return str_contains($html, '¿Dónde se entrega?') ?: 'no aparece el bloque';
});

$check('preselecciona el domicilio principal del cliente', function () use ($conDom) {
    $esperado = DomicilioCliente::where('cliente_id', $conDom)->activos()
        ->whereIn('uso', ['ambos', 'entrega'])->orderByDesc('es_principal')->orderBy('id')->value('id');
    $t = Livewire::test(App\Livewire\Ventas\Nueva::class)->call('elegirCliente', $conDom);

    return $t->get('domicilioEntregaId') === $esperado ?: 'quedó ' . var_export($t->get('domicilioEntregaId'), true) . " en vez de {$esperado}";
});

$check('los domicilios "solo cobro" NO aparecen como opción de entrega', function () use ($conDom) {
    $soloCobro = DomicilioCliente::where('cliente_id', $conDom)->where('uso', 'cobro')->value('etiqueta');
    if (! $soloCobro) {
        return true; // el cliente no tiene ninguno de solo-cobro
    }
    $t = Livewire::test(App\Livewire\Ventas\Nueva::class)->call('elegirCliente', $conDom);
    $opciones = collect($t->instance()->domiciliosEntrega)->pluck('etiqueta')->all();

    return ! in_array($soloCobro, $opciones, true) ?: "se coló «{$soloCobro}»";
});

$check('cambiarCliente() limpia el domicilio elegido', function () use ($conDom) {
    $t = Livewire::test(App\Livewire\Ventas\Nueva::class)->call('elegirCliente', $conDom)->call('cambiarCliente');

    return $t->get('domicilioEntregaId') === null ?: 'quedó ' . var_export($t->get('domicilioEntregaId'), true);
});

echo "\n== 5. Cobranza — la planilla usa el domicilio de COBRO ==\n";
$check('Planilla::filas expone domicilio/etiqueta/referencia/maps', function () {
    $cob = User::whereHas('zonasComoCobrador')->first() ?? User::find(7);
    if (! $cob) {
        return 'no hay cobrador con zona';
    }
    $hoy = Carbon::today();
    $cuotas = Planilla::cuotasDelDia($cob->id, $hoy);
    if ($cuotas->isEmpty()) {
        return true; // sin cuotas hoy: nada que comparar, el render se prueba abajo
    }
    $mod = Planilla::modalidadesPresentes($cuotas)->first();
    $filas = Planilla::filas($cuotas, $hoy, $mod);
    $f = $filas[0] ?? null;
    $faltan = array_filter(['domicilio', 'domicilio_etiqueta', 'referencia', 'maps'], fn ($k) => ! array_key_exists($k, $f ?? []));

    return $faltan ? 'faltan claves: ' . implode(', ', $faltan) : true;
});

$check('Cobranza\Planilla renderiza sin error', function () {
    $cob = User::whereHas('zonasComoCobrador')->first() ?? User::find(7);
    Illuminate\Support\Facades\Auth::login($cob);
    $html = Livewire::test(App\Livewire\Cobranza\Planilla::class)->html();
    Illuminate\Support\Facades\Auth::login(User::where('email', 'dueno@ecomercial.com')->first());

    return strlen($html) > 500 ?: 'html sospechosamente corto';
});

echo "\n== 6. Render — entrega de la venta muestra a dónde va ==\n";
$check('Ventas\Index renderiza (modal de entrega con domicilio)', function () {
    $v = Venta::where('estado', 'aprobada')->whereNull('entregado_at')->first();
    $t = Livewire::test(App\Livewire\Ventas\Index::class);
    if ($v) {
        $t->call('entregar', $v->id);
        $dom = $t->get('entregaDomicilio');
        if (! is_array($dom) || ! array_key_exists('etiqueta', $dom)) {
            return 'entregaDomicilio no se completó';
        }
    }
    $html = $t->html();

    return strlen($html) > 500 ?: 'html sospechosamente corto';
});

echo "\n────────────────────────────────\n";
echo ($fail === 0 ? "✅ TODO OK" : "❌ HAY FALLAS") . " — {$ok} ok · {$fail} fallas\n";
