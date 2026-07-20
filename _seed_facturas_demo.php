<?php
/**
 * Datos de prueba para el escaneo de facturas: crea, a partir de las 3 facturas
 * de ejemplo (carpeta FACTURAS), un proveedor + productos + una orden de compra
 * APROBADA que les corresponde, para poder abrirla en Recepción y escanear.
 * Idempotente (re-ejecutable). Corre: php _seed_facturas_demo.php
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Compra, CompraItem, Local, Producto, Proveedor, StockLocal};

$LOCAL = Local::first()->id;      // Local A
$USUARIO = 1;                     // dueño (super_admin)
$CAT = 4;                         // Maquinaria

/** Crea proveedor + productos + compra aprobada. */
function armar(string $ocNumero, string $factura, array $prov, array $items, int $local, int $usuario, int $cat): void
{
    $proveedor = Proveedor::firstOrCreate(['nombre' => $prov['nombre']], [
        'rubro' => 'Equipamiento', 'cuit' => $prov['cuit'] ?? null,
        'dias_entrega' => 5, 'activo' => true,
    ]);

    // Productos del catálogo (match por código).
    $resueltos = [];
    foreach ($items as $it) {
        $p = Producto::firstOrCreate(['codigo' => $it['codigo']], [
            'nombre' => $it['nombre'], 'unidad' => 'unidad', 'activo' => true,
            'categoria_id' => $cat, 'proveedor_id' => $proveedor->id,
            'precio_compra' => $it['precio_compra_previo'],
        ]);
        // Stock base 0 en el local, para ver el incremento al confirmar.
        StockLocal::firstOrCreate(
            ['producto_id' => $p->id, 'local_id' => $local],
            ['cantidad' => 0, 'stock_minimo' => 2, 'precio_venta' => round($it['precio_compra_previo'] * 1.4, 2)]
        );
        $resueltos[] = ['p' => $p, 'cant' => $it['cant_pedido'], 'costo' => $it['precio_compra_previo']];
    }

    if (Compra::where('numero', $ocNumero)->exists()) {
        echo "  • {$ocNumero} ya existía (factura: {$factura})\n";

        return;
    }

    $total = array_sum(array_map(fn ($r) => $r['cant'] * $r['costo'], $resueltos));
    $nrosFactura = ['OC-NEBA-01' => 'A 0002-00107814', 'OC-LILIANA-01' => 'A 0031-00054593', 'OC-ARE-01' => 'A 0003-00001609'];
    $iva = round($total * 0.21, 2);
    $compra = Compra::create([
        'numero' => $ocNumero, 'proveedor_id' => $proveedor->id, 'local_id' => $local,
        'usuario_id' => $usuario, 'fecha' => now()->toDateString(),
        'fecha_estimada' => now()->addDays(3)->toDateString(), 'total' => $total,
        'factura_numero' => $nrosFactura[$ocNumero] ?? null,
        'desglose' => ['subtotal' => $total, 'iva21' => $iva, 'iva105' => 0, 'flete' => 0, 'total' => $total + $iva],
        'estado' => 'aprobada',
    ]);
    foreach ($resueltos as $r) {
        CompraItem::create([
            'compra_id' => $compra->id, 'producto_id' => $r['p']->id,
            'cantidad' => $r['cant'], 'costo_unitario' => $r['costo'], 'estado_item' => 'ok',
        ]);
    }
    echo "  ✅ {$ocNumero} creada · {$proveedor->nombre} · " . count($resueltos) . " ítem(s) → escaneá: {$factura}\n";
}

echo "Sembrando datos de prueba para escaneo de facturas...\n";

// 1) NEBA — Red del Interior ACE (1 producto + 3 gastos en la factura).
//    Trampa: precio previo distinto al de la factura (536797.92) → demo "diferencia de precio".
armar('OC-NEBA-01', 'FA NEBA RED ACE.pdf',
    ['nombre' => 'Red del Interior ACE', 'cuit' => '30-70857966-8'],
    [['codigo' => 'EVC330', 'nombre' => 'EXHIBIDORA NEBA 330 LTS', 'cant_pedido' => 4, 'precio_compra_previo' => 500000]],
    $LOCAL, $USUARIO, $CAT);

// 2) Liliana S.R.L. (1 producto).
armar('OC-LILIANA-01', 'Comprobante 0030003924 .PDF',
    ['nombre' => 'Liliana S.R.L.', 'cuit' => '30-00000000-0'],
    [['codigo' => 'WBCVWB04', 'nombre' => 'VERTICAL EFACTOR INFRARROJO 600/1200W-WB', 'cant_pedido' => 24, 'precio_compra_previo' => 16900]],
    $LOCAL, $USUARIO, $CAT);

// 3) ARE de Edgardo Ciampone (4 productos).
//    Trampa: la RALLADORA se pidió 3 pero la factura dice 2 → demo "discrepancia de cantidad" + recepción parcial.
armar('OC-ARE-01', 'FC A 0003-00001609.pdf',
    ['nombre' => 'ARE de Edgardo Ciampone', 'cuit' => '20-00000000-1'],
    [
        ['codigo' => '00031', 'nombre' => 'RALLADORA INDUSTRIAL RPM10', 'cant_pedido' => 3, 'precio_compra_previo' => 342829],
        ['codigo' => '00002', 'nombre' => 'ANAFE INDUSTRIAL C12', 'cant_pedido' => 1, 'precio_compra_previo' => 118107],
        ['codigo' => '00001', 'nombre' => 'ANAFE INDUSTRIAL C24', 'cant_pedido' => 2, 'precio_compra_previo' => 140248],
        ['codigo' => '00063', 'nombre' => 'ANAFE INDUSTRIAL C17 (MECHERO ESTRELLA)', 'cant_pedido' => 1, 'precio_compra_previo' => 127217],
    ],
    $LOCAL, $USUARIO, $CAT);

echo "\nListo. Entrá a /recepcion (deposito@), abrí una OC y escaneá la factura indicada.\n";
