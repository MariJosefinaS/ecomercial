<?php

/**
 * Carga los domicilios demo en la DB de desarrollo SIN re-sembrar todo
 * (migrate:fresh --seed borraría lo cargado a mano: rol Cobrador, Josefina, etc.).
 * Idempotente: si el domicilio ya existe (mismo cliente + etiqueta) no lo duplica.
 *   php seed_domicilios_demo.php
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Cliente;
use App\Models\DomicilioCliente;
use App\Models\Zona;

$zona = fn (string $n) => Zona::where('nombre', 'like', "%{$n}%")->value('id');

$demo = [
    'Ferretería El Roble' => [
        ['etiqueta' => 'Negocio', 'direccion' => 'Av. San Nicolás 450', 'localidad' => 'La Rioja', 'provincia' => 'La Rioja', 'referencia' => 'Frente a la plaza, cortina verde', 'contacto' => 'Marta (encargada)', 'telefono' => '380-4111-222', 'latitud' => -29.4131, 'longitud' => -66.8558, 'uso' => 'ambos', 'es_principal' => true, 'zona' => 'Rioja Este'],
        ['etiqueta' => 'Depósito', 'direccion' => 'Ruta 5 Km 3, Parque Industrial', 'localidad' => 'La Rioja', 'provincia' => 'La Rioja', 'referencia' => 'Portón azul, entregar de 8 a 12', 'uso' => 'entrega', 'zona' => 'Rioja Este'],
    ],
    'Constructora Andina' => [
        ['etiqueta' => 'Casa', 'direccion' => 'Belgrano 1240', 'localidad' => 'Chilecito', 'provincia' => 'La Rioja', 'referencia' => 'Casa de rejas negras, entre Sarmiento y Mitre', 'contacto' => 'Sra. Pérez', 'uso' => 'ambos', 'es_principal' => true, 'zona' => 'Distritos Chilecito'],
        ['etiqueta' => 'Casa de la hija', 'direccion' => 'Los Álamos 87', 'localidad' => 'Chilecito', 'provincia' => 'La Rioja', 'referencia' => 'Cobrar acá los sábados', 'contacto' => 'Julieta', 'uso' => 'cobro', 'zona' => 'Distritos Chilecito'],
    ],
    'Obras del Norte' => [
        ['etiqueta' => 'Obra en curso', 'direccion' => 'Rivadavia 2100', 'localidad' => 'La Rioja', 'provincia' => 'La Rioja', 'referencia' => 'Preguntar por el capataz', 'uso' => 'entrega', 'es_principal' => true],
    ],
];

$creados = 0;
$saltados = 0;

foreach ($demo as $nombreCliente => $domicilios) {
    $c = Cliente::where('nombre', $nombreCliente)->first();
    if (! $c) {
        echo "⚠  No existe el cliente «{$nombreCliente}» — salteado.\n";
        continue;
    }
    foreach ($domicilios as $d) {
        if (DomicilioCliente::where('cliente_id', $c->id)->where('etiqueta', $d['etiqueta'])->exists()) {
            $saltados++;
            continue;
        }
        $zonaNombre = $d['zona'] ?? null;
        unset($d['zona']);
        DomicilioCliente::create($d + [
            'cliente_id' => $c->id,
            'zona_id' => $zonaNombre ? $zona($zonaNombre) : null,
            'activo' => true,
        ]);
        $creados++;
        echo "✔  {$nombreCliente} → {$d['etiqueta']}\n";
    }
}

echo "\nCreados: {$creados} · Ya existían: {$saltados} · Total en DB: " . DomicilioCliente::count() . "\n";
