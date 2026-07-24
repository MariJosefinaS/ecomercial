<?php

/**
 * Verificación del CIRCUITO DE REPOSICIÓN de punta a punta:
 *   solicitud → aprobación → orden de compra → (flujo existente) recepción → stock.
 *   php verify_reposicion_circuito.php
 * Todo lo que muta va en transacción con ROLLBACK: la DB queda intacta.
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Compra;
use App\Models\CompraItem;
use App\Models\Local;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\SolicitudCompra;
use App\Models\User;
use App\Support\Reposicion;
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

/** Crea un producto de prueba con proveedor y costo. */
$productoTest = function (string $sufijo, ?int $proveedorId, float $costo = 100): Producto {
    return Producto::create([
        'codigo' => 'ZZ-REP-' . $sufijo, 'nombre' => 'ZZ Repo ' . $sufijo,
        'proveedor_id' => $proveedorId, 'precio_compra' => $costo, 'activo' => true,
    ]);
};

echo "\n== 1. Esquema ==\n";
$check('solicitudes_compra tiene las columnas del circuito', function () {
    $faltan = array_filter(['proveedor_id', 'compra_id', 'resuelta_por', 'resuelta_at', 'motivo_rechazo'],
        fn ($c) => ! Schema::hasColumn('solicitudes_compra', $c));

    return $faltan ? 'faltan: ' . implode(', ', $faltan) : true;
});
$check("el estado acepta 'convertida'", function () {
    $col = DB::selectOne("SHOW COLUMNS FROM solicitudes_compra LIKE 'estado'");

    return str_contains($col->Type, 'convertida') ?: 'tipo: ' . $col->Type;
});

echo "\n== 2. Alta de la solicitud (fuente única) ==\n";
$check('solicitar() numera y hereda el proveedor del producto', function () use ($productoTest, $dueno) {
    DB::beginTransaction();
    try {
        $prov = Proveedor::where('activo', true)->first();
        $p = $productoTest('A', $prov->id);
        $local = Local::where('activo', true)->first();
        $s = Reposicion::solicitar($p, $local->id, 5, $dueno->id, 'test');
        DB::rollBack();

        return (str_starts_with($s->numero, 'SOL-') && $s->proveedor_id === $prov->id && $s->estado === 'pendiente' && $s->cantidad === 5)
            ?: "numero={$s->numero} prov={$s->proveedor_id} estado={$s->estado} cant={$s->cantidad}";
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

$check('los dos orígenes (Stock y EOQ) usan la misma serie de números', function () use ($productoTest, $dueno) {
    DB::beginTransaction();
    try {
        $prov = Proveedor::where('activo', true)->first();
        $local = Local::where('activo', true)->first();
        $a = Reposicion::solicitar($productoTest('B1', $prov->id), $local->id, 1, $dueno->id);
        $b = Reposicion::solicitar($productoTest('B2', $prov->id), $local->id, 1, $dueno->id);
        // Solo los dígitos: filter_var deja el "-" de "SOL-79" y lo lee como negativo.
        $nA = (int) preg_replace('/\D/', '', $a->numero);
        $nB = (int) preg_replace('/\D/', '', $b->numero);
        DB::rollBack();

        return $nB === $nA + 1 ?: "{$a->numero} luego {$b->numero}";
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

echo "\n== 3. Aprobar / rechazar / reabrir ==\n";
$check('aprobar deja la solicitud lista y registra quién y cuándo', function () use ($productoTest, $dueno) {
    DB::beginTransaction();
    try {
        $prov = Proveedor::where('activo', true)->first();
        $local = Local::where('activo', true)->first();
        $s = Reposicion::solicitar($productoTest('C', $prov->id), $local->id, 3, $dueno->id);
        Livewire::test(App\Livewire\Compras\Index::class)->call('aprobarSolicitud', $s->id);
        $f = SolicitudCompra::find($s->id);
        DB::rollBack();

        return ($f->estado === 'aprobada' && $f->resuelta_por === $dueno->id && $f->resuelta_at !== null)
            ?: "estado={$f->estado} por={$f->resuelta_por}";
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

$check('rechazar guarda el motivo', function () use ($productoTest, $dueno) {
    DB::beginTransaction();
    try {
        $prov = Proveedor::where('activo', true)->first();
        $local = Local::where('activo', true)->first();
        $s = Reposicion::solicitar($productoTest('D', $prov->id), $local->id, 3, $dueno->id);
        Livewire::test(App\Livewire\Compras\Index::class)
            ->call('pedirRechazoSolicitud', $s->id)->set('solMotivo', 'Hay stock suficiente')->call('rechazarSolicitud');
        $f = SolicitudCompra::find($s->id);
        DB::rollBack();

        return ($f->estado === 'rechazada' && $f->motivo_rechazo === 'Hay stock suficiente')
            ?: "estado={$f->estado} motivo={$f->motivo_rechazo}";
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

$check('no se puede aprobar dos veces (ni aprobar una rechazada)', function () use ($productoTest, $dueno) {
    DB::beginTransaction();
    try {
        $prov = Proveedor::where('activo', true)->first();
        $local = Local::where('activo', true)->first();
        $s = Reposicion::solicitar($productoTest('E', $prov->id), $local->id, 3, $dueno->id);
        $primera = Reposicion::aprobar($s, $dueno->id);
        $segunda = Reposicion::aprobar($s->fresh(), $dueno->id);
        $rechazo = Reposicion::rechazar($s->fresh(), $dueno->id, 'x');
        DB::rollBack();

        return ($primera && ! $segunda && ! $rechazo) ?: "1ª={$primera} 2ª={$segunda} rechazo={$rechazo}";
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

$check('reabrir vuelve a pendiente, pero NO si ya se convirtió', function () use ($productoTest, $dueno) {
    DB::beginTransaction();
    try {
        $prov = Proveedor::where('activo', true)->first();
        $local = Local::where('activo', true)->first();
        $s = Reposicion::solicitar($productoTest('F', $prov->id), $local->id, 3, $dueno->id);
        Reposicion::aprobar($s, $dueno->id);
        $reabre = Reposicion::reabrir($s->fresh());

        Reposicion::aprobar($s->fresh(), $dueno->id);
        Reposicion::convertirEnCompras([$s->id], $dueno->id);
        $reabreConvertida = Reposicion::reabrir($s->fresh());
        DB::rollBack();

        return ($reabre && ! $reabreConvertida) ?: "reabre={$reabre} reabreConvertida={$reabreConvertida}";
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

echo "\n== 4. Conversión en ORDEN DE COMPRA ==\n";
$check('una solicitud aprobada genera una orden de compra con su ítem', function () use ($productoTest, $dueno) {
    DB::beginTransaction();
    try {
        $prov = Proveedor::where('activo', true)->first();
        $local = Local::where('activo', true)->first();
        $p = $productoTest('G', $prov->id, 250);
        $s = Reposicion::solicitar($p, $local->id, 4, $dueno->id);
        Reposicion::aprobar($s, $dueno->id);
        $r = Reposicion::convertirEnCompras([$s->id], $dueno->id);

        $f = SolicitudCompra::find($s->id);
        $compra = Compra::find($f->compra_id);
        $item = CompraItem::where('compra_id', $compra?->id)->where('producto_id', $p->id)->first();
        DB::rollBack();

        if ($f->estado !== 'convertida') {
            return "la solicitud quedó en {$f->estado}";
        }
        if (! $compra || $compra->estado !== 'pendiente') {
            return 'no se creó la compra en estado pendiente';
        }
        if (! $item || $item->cantidad !== 4) {
            return 'el ítem no tiene la cantidad pedida';
        }
        if (abs((float) $compra->total - 1000) > 0.01) {
            return "total {$compra->total}, esperaba 1000 (4 × 250)";
        }

        return true;
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage() . ' @ ' . $e->getLine();
    }
});

$check('varias solicitudes del MISMO proveedor entran en UNA sola orden', function () use ($productoTest, $dueno) {
    DB::beginTransaction();
    try {
        $prov = Proveedor::where('activo', true)->first();
        $local = Local::where('activo', true)->first();
        $s1 = Reposicion::solicitar($productoTest('H1', $prov->id), $local->id, 2, $dueno->id);
        $s2 = Reposicion::solicitar($productoTest('H2', $prov->id), $local->id, 3, $dueno->id);
        Reposicion::aprobar($s1, $dueno->id);
        Reposicion::aprobar($s2, $dueno->id);
        $r = Reposicion::convertirEnCompras([$s1->id, $s2->id], $dueno->id);
        $compras = SolicitudCompra::whereIn('id', [$s1->id, $s2->id])->pluck('compra_id')->unique();
        $items = CompraItem::where('compra_id', $compras->first())->count();
        DB::rollBack();

        return ($compras->count() === 1 && count($r['compras']) === 1 && $items === 2)
            ?: 'compras=' . $compras->count() . " items={$items}";
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

$check('proveedores DISTINTOS generan órdenes separadas', function () use ($productoTest, $dueno) {
    DB::beginTransaction();
    try {
        $provs = Proveedor::where('activo', true)->limit(2)->get();
        if ($provs->count() < 2) {
            DB::rollBack();

            return true;
        }
        $local = Local::where('activo', true)->first();
        $s1 = Reposicion::solicitar($productoTest('I1', $provs[0]->id), $local->id, 2, $dueno->id);
        $s2 = Reposicion::solicitar($productoTest('I2', $provs[1]->id), $local->id, 2, $dueno->id);
        Reposicion::aprobar($s1, $dueno->id);
        Reposicion::aprobar($s2, $dueno->id);
        $r = Reposicion::convertirEnCompras([$s1->id, $s2->id], $dueno->id);
        DB::rollBack();

        return count($r['compras']) === 2 ?: 'generó ' . count($r['compras']) . ' orden(es)';
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

$check('el MISMO producto pedido dos veces suma cantidades en una sola línea', function () use ($productoTest, $dueno) {
    DB::beginTransaction();
    try {
        $prov = Proveedor::where('activo', true)->first();
        $local = Local::where('activo', true)->first();
        $p = $productoTest('J', $prov->id);
        $s1 = Reposicion::solicitar($p, $local->id, 3, $dueno->id);
        $s2 = Reposicion::solicitar($p, $local->id, 7, $dueno->id);
        Reposicion::aprobar($s1, $dueno->id);
        Reposicion::aprobar($s2, $dueno->id);
        Reposicion::convertirEnCompras([$s1->id, $s2->id], $dueno->id);
        $compraId = SolicitudCompra::find($s1->id)->compra_id;
        $items = CompraItem::where('compra_id', $compraId)->where('producto_id', $p->id)->get();
        DB::rollBack();

        return ($items->count() === 1 && $items->first()->cantidad === 10)
            ?: 'líneas=' . $items->count() . ' cant=' . ($items->first()->cantidad ?? '?');
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

$check('un producto SIN proveedor no se convierte y se avisa', function () use ($productoTest, $dueno) {
    DB::beginTransaction();
    try {
        $local = Local::where('activo', true)->first();
        $s = Reposicion::solicitar($productoTest('K', null), $local->id, 2, $dueno->id);
        Reposicion::aprobar($s, $dueno->id);
        $r = Reposicion::convertirEnCompras([$s->id], $dueno->id);
        $f = SolicitudCompra::find($s->id);
        DB::rollBack();

        return ($r['sin_proveedor'] === 1 && $f->estado === 'aprobada' && $f->compra_id === null)
            ?: "sin_proveedor={$r['sin_proveedor']} estado={$f->estado}";
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

$check('una solicitud PENDIENTE (sin aprobar) no se convierte', function () use ($productoTest, $dueno) {
    DB::beginTransaction();
    try {
        $prov = Proveedor::where('activo', true)->first();
        $local = Local::where('activo', true)->first();
        $s = Reposicion::solicitar($productoTest('L', $prov->id), $local->id, 2, $dueno->id);
        $r = Reposicion::convertirEnCompras([$s->id], $dueno->id);
        DB::rollBack();

        return $r['convertidas'] === 0 ?: 'convirtió una pendiente';
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

$check('no se convierte dos veces la misma solicitud', function () use ($productoTest, $dueno) {
    DB::beginTransaction();
    try {
        $prov = Proveedor::where('activo', true)->first();
        $local = Local::where('activo', true)->first();
        $s = Reposicion::solicitar($productoTest('M', $prov->id), $local->id, 2, $dueno->id);
        Reposicion::aprobar($s, $dueno->id);
        Reposicion::convertirEnCompras([$s->id], $dueno->id);
        $segunda = Reposicion::convertirEnCompras([$s->id], $dueno->id);
        $compras = Compra::whereHas('items', fn ($q) => $q->where('producto_id', $s->producto_id))->count();
        DB::rollBack();

        return ($segunda['convertidas'] === 0 && $compras === 1) ?: "2ª convirtió {$segunda['convertidas']}, compras={$compras}";
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

echo "\n== 5. Circuito COMPLETO hasta el stock ==\n";
$check('solicitud → aprobar → orden → aprobar compra → recibir suma al STOCK', function () use ($productoTest, $dueno) {
    DB::beginTransaction();
    try {
        $prov = Proveedor::where('activo', true)->first();
        $local = Local::where('activo', true)->first();
        $p = $productoTest('N', $prov->id);
        App\Models\StockLocal::create(['producto_id' => $p->id, 'local_id' => $local->id, 'cantidad' => 10, 'stock_minimo' => 2, 'precio_venta' => 500]);

        // 1) solicitud → 2) aprobación → 3) orden de compra
        $s = Reposicion::solicitar($p, $local->id, 6, $dueno->id);
        Reposicion::aprobar($s, $dueno->id);
        Reposicion::convertirEnCompras([$s->id], $dueno->id);
        $compraId = SolicitudCompra::find($s->id)->compra_id;

        // 4) el flujo ya existente de Compras: aprobar y recibir
        $t = Livewire::test(App\Livewire\Compras\Index::class);
        $t->call('aprobar', $compraId);
        $t->call('recibir', $compraId);

        $stock = App\Models\StockLocal::where('producto_id', $p->id)->where('local_id', $local->id)->value('cantidad');
        $estadoCompra = Compra::find($compraId)->estado;
        DB::rollBack();

        if ($estadoCompra !== 'recibida') {
            return "la compra quedó en {$estadoCompra}";
        }
        if ((int) $stock !== 16) {
            return "el stock quedó en {$stock}, esperaba 16 (10 + 6)";
        }

        return true;
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage() . ' @ ' . $e->getLine();
    }
});

echo "\n== 6. Render y permisos ==\n";
$check('el tab de Solicitudes renderiza', function () {
    $html = Livewire::test(App\Livewire\Compras\Index::class)->call('setTab', 'solicitudes')->html();

    return (str_contains($html, 'Solicitudes de reposición') && str_contains($html, 'solicitud')) ?: 'no renderiza el tab';
});

$check('el tab de Órdenes sigue renderizando', function () {
    $html = Livewire::test(App\Livewire\Compras\Index::class)->call('setTab', 'ordenes')->html();

    return str_contains($html, 'Listado de compras') ?: 'se rompió el tab original';
});

$check('el botón "Generar orden de compra" aparece cuando hay aprobadas', function () use ($productoTest, $dueno) {
    DB::beginTransaction();
    try {
        $prov = Proveedor::where('activo', true)->first();
        $local = Local::where('activo', true)->first();
        $s = Reposicion::solicitar($productoTest('O', $prov->id), $local->id, 2, $dueno->id);
        Reposicion::aprobar($s, $dueno->id);
        $html = Livewire::test(App\Livewire\Compras\Index::class)->call('setTab', 'solicitudes')->html();
        DB::rollBack();

        return str_contains($html, 'Generar orden de compra') ?: 'no aparece el botón';
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

$check('el VENDEDOR no puede aprobar una solicitud', function () use ($productoTest, $dueno) {
    DB::beginTransaction();
    try {
        $prov = Proveedor::where('activo', true)->first();
        $local = Local::where('activo', true)->first();
        $s = Reposicion::solicitar($productoTest('P', $prov->id), $local->id, 2, $dueno->id);
        $v = User::where('rol', 'vendedor')->first();
        if (! $v) {
            DB::rollBack();

            return true;
        }
        Auth::login($v);
        $r = true;
        try {
            Livewire::test(App\Livewire\Compras\Index::class)->call('aprobarSolicitud', $s->id)->assertForbidden();
        } catch (\Throwable $e) {
            $r = 'el vendedor pudo aprobar: ' . $e->getMessage();
        }
        Auth::login(User::where('email', 'dueno@ecomercial.com')->first());
        $estado = SolicitudCompra::find($s->id)->estado;
        DB::rollBack();

        return $r === true ? ($estado === 'pendiente' ?: "quedó en {$estado}") : $r;
    } catch (\Throwable $e) {
        DB::rollBack();

        return 'excepción: ' . $e->getMessage();
    }
});

echo "\n────────────────────────────────\n";
echo ($fail === 0 ? '✅ TODO OK' : '❌ HAY FALLAS') . " — {$ok} ok · {$fail} fallas\n";
