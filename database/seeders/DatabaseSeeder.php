<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Categoria;
use App\Models\Cheque;
use App\Models\ChequeCliente;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\CompraItem;
use App\Models\ConceptoPrecio;
use App\Models\Cuota;
use App\Models\Devolucion;
use App\Models\DomicilioCliente;
use App\Models\Local;
use App\Models\MovimientoCaja;
use App\Models\MovimientoCliente;
use App\Models\PagoProveedor;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\SolicitudCompra;
use App\Models\StockLocal;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Support\PlanesCredito;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $hoy = Carbon::today();

        // ===== Locales =====
        $localA = Local::create(['nombre' => 'Local A', 'direccion' => 'Chilecito', 'telefono' => '3825-000000']);
        $localB = Local::create(['nombre' => 'Local B', 'direccion' => 'La Rioja', 'telefono' => '380-000000']);

        // ===== Usuarios / roles =====
        $dueno = User::create(['name' => 'Dueño', 'email' => 'dueno@ecomercial.com', 'password' => Hash::make('password'), 'rol' => 'super_admin', 'telefono' => '380-1112222', 'ultimo_acceso' => now()->subMinutes(2)]);
        $adminA = User::create(['name' => 'Admin Local A', 'email' => 'adminA@ecomercial.com', 'password' => Hash::make('password'), 'rol' => 'admin_local', 'local_id' => $localA->id, 'ultimo_acceso' => now()->subHour()]);
        $adminB = User::create(['name' => 'Admin Local B', 'email' => 'adminB@ecomercial.com', 'password' => Hash::make('password'), 'rol' => 'admin_local', 'local_id' => $localB->id, 'ultimo_acceso' => now()->subDay()]);
        $ricardo = User::create(['name' => 'Ricardo Mendes', 'email' => 'ricardo@ecomercial.com', 'password' => Hash::make('password'), 'rol' => 'vendedor', 'local_id' => $localA->id, 'ultimo_acceso' => now()->subMinutes(15)]);
        $ana = User::create(['name' => 'Ana Silva', 'email' => 'ana@ecomercial.com', 'password' => Hash::make('password'), 'rol' => 'vendedor', 'local_id' => $localA->id, 'ultimo_acceso' => now()->subHours(3)]);
        $carlos = User::create(['name' => 'Carlos Pereira', 'email' => 'carlos@ecomercial.com', 'password' => Hash::make('password'), 'rol' => 'vendedor', 'local_id' => $localB->id, 'ultimo_acceso' => now()->subDay()]);
        $marcos = User::create(['name' => 'Marcos Lima', 'email' => 'marcos@ecomercial.com', 'password' => Hash::make('password'), 'rol' => 'vendedor', 'local_id' => $localA->id, 'ultimo_acceso' => now()->subMinutes(40)]);
        $sofia = User::create(['name' => 'Sofía Gómez', 'email' => 'sofia@ecomercial.com', 'password' => Hash::make('password'), 'rol' => 'empleado', 'local_id' => $localB->id, 'activo' => false, 'ultimo_acceso' => now()->subWeeks(2)]);
        $deposito = User::create(['name' => 'Diego Depósito', 'email' => 'deposito@ecomercial.com', 'password' => Hash::make('password'), 'rol' => 'deposito', 'local_id' => $localA->id, 'ultimo_acceso' => now()->subHours(1)]);

        // ===== Zonas de cobranza (cobrador por zona; nombres del sistema real del cliente) =====
        $zRiojaEste = \App\Models\Zona::create(['nombre' => 'Rioja Este', 'local_id' => $localA->id, 'cobrador_id' => $marcos->id]);
        \App\Models\Zona::create(['nombre' => 'Distritos Chilecito', 'local_id' => $localA->id, 'cobrador_id' => $ricardo->id]);
        \App\Models\Zona::create(['nombre' => 'Local Chilecito', 'local_id' => $localA->id, 'cobrador_id' => $ana->id]);
        \App\Models\Zona::create(['nombre' => 'Rioja Sur', 'local_id' => $localB->id, 'cobrador_id' => $carlos->id]);

        // ===== Proveedores =====
        // Costeo: costea_con_iva = si el IVA entra al costo (RI con crédito fiscal = false).
        // El remarque/ganancia es ahora el concepto "Remarcar" (ámbito venta), con su % por proveedor.
        $aceroSur = Proveedor::create(['nombre' => 'Acero Sur S.A.', 'rubro' => 'Construcción', 'cuit' => '30-11111111-1', 'telefono' => '011-4000-0000', 'email' => 'ventas@acerosur.com', 'dias_entrega' => 7, 'costea_con_iva' => false]);
        $herramientas = Proveedor::create(['nombre' => 'Herramientas del Norte', 'rubro' => 'Herramientas', 'cuit' => '30-22222222-2', 'dias_entrega' => 3, 'costea_con_iva' => false]);
        $hogar = Proveedor::create(['nombre' => 'Hogar y Deco S.R.L.', 'rubro' => 'Hogar', 'cuit' => '30-33333333-3', 'telefono' => '380-4555-000', 'dias_entrega' => 5, 'costea_con_iva' => true, 'iva_pct' => 21]);

        // Conceptos a cobrar de cada proveedor: Flete (costo) común + Remarcar (venta) con % propio.
        $flete = ConceptoPrecio::where('nombre', 'Flete')->first();
        $remarcar = ConceptoPrecio::where('nombre', 'Remarcar')->first();
        $remarquePorProv = [$aceroSur->id => 40, $herramientas->id => 35, $hogar->id => 50];
        foreach ([$aceroSur, $herramientas, $hogar] as $prov) {
            $prov->conceptos()->sync([
                $flete->id => ['porcentaje' => $flete->porcentaje],
                $remarcar->id => ['porcentaje' => $remarquePorProv[$prov->id]],
            ]);
        }

        // ===== Categorías =====
        $catHerr = Categoria::create(['nombre' => 'Herramientas', 'icono' => 'handyman']);
        $catConstr = Categoria::create(['nombre' => 'Construcción', 'icono' => 'straighten']);
        $catAlmac = Categoria::create(['nombre' => 'Almacenamiento', 'icono' => 'warehouse']);
        $catMaq = Categoria::create(['nombre' => 'Maquinaria', 'icono' => 'home_repair_service']);
        $catHogar = Categoria::create(['nombre' => 'Hogar', 'icono' => 'chair']);

        // ===== Productos + stock/precio por local (con DIFERENCIAS de precio) =====
        // [codigo, nombre, categoria, proveedor, precioCompra, precioA, precioB, cantA, cantB, min]  (null = NO existe en ese local)
        $defs = [
            ['DRILL-XL200', 'Taladro Industrial XL-200', $catHerr, $herramientas, 880.00, 1240.00, 1380.00, 12, 0, 5],
            ['HIERRO-T', 'Barras de Hierro (T)', $catConstr, $aceroSur, 620.00, 890.00, 810.00, 40, 30, 10],
            ['STORAGE-HC', 'Estantería de Alta Capacidad', $catAlmac, $aceroSur, 3100.00, 4500.00, 4100.00, 8, 6, 3],
            ['MIXER-500', 'Hormigonera 500L', $catMaq, $herramientas, 1500.00, 2100.00, 2350.00, 4, 2, 2],
            ['MECHA-HSS', 'Mechas HSS x100', $catHerr, $herramientas, 55.00, 81.00, 81.00, 100, 3, 20],
            // Sólo en Local A (no existe en B) — para probar filtro por sucursal:
            ['HOGAR-LAMP', 'Lámpara LED de Pie', $catHogar, $hogar, 1200.00, 1850.00, null, 15, null, 4],
            // Sólo en Local B (no existe en A):
            ['HOGAR-SILLA', 'Silla Ergonómica', $catHogar, $hogar, 2400.00, null, 3600.00, null, 9, 4],
        ];
        $productos = [];
        foreach ($defs as [$cod, $nom, $cat, $prov, $pc, $pa, $pb, $ca, $cb, $min]) {
            // $pc se trata como NETO; precio_compra guarda el costo puesto en depósito.
            $costo = \App\Support\Costeo::costo((float) $pc, $prov);
            $p = Producto::create(['codigo' => $cod, 'nombre' => $nom, 'categoria_id' => $cat->id, 'proveedor_id' => $prov->id, 'precio_neto' => $pc, 'precio_compra' => $costo]);
            if ($pa !== null) {
                StockLocal::create(['producto_id' => $p->id, 'local_id' => $localA->id, 'cantidad' => $ca, 'stock_minimo' => $min, 'precio_venta' => $pa]);
            }
            if ($pb !== null) {
                StockLocal::create(['producto_id' => $p->id, 'local_id' => $localB->id, 'cantidad' => $cb, 'stock_minimo' => $min, 'precio_venta' => $pb]);
            }
            $productos[$cod] = $p;
        }

        // ===== Clientes =====
        $roble = Cliente::create(['nombre' => 'Ferretería El Roble', 'tipo_doc' => 'CUIT', 'documento' => '30-44444444-4', 'telefono' => '380-4111-222', 'email' => 'roble@mail.com', 'direccion' => 'Av. San Nicolás 450, La Rioja', 'zona_id' => $zRiojaEste->id, 'limite_credito' => 50000, 'riesgo' => 'bajo', 'aprobado' => true]);
        $obras = Cliente::create(['nombre' => 'Obras del Norte', 'tipo_doc' => 'CUIT', 'documento' => '30-55555555-5', 'telefono' => '380-4222-333', 'limite_credito' => 100000, 'riesgo' => 'medio', 'aprobado' => true]);
        $andina = Cliente::create(['nombre' => 'Constructora Andina', 'tipo_doc' => 'CUIT', 'documento' => '30-66666666-6', 'telefono' => '380-4333-444', 'limite_credito' => 80000, 'riesgo' => 'alto', 'aprobado' => true]);
        $aika = Cliente::create(['nombre' => 'Distribuidora Aika', 'tipo_doc' => 'CUIL', 'documento' => '20-77777777-7', 'limite_credito' => 30000, 'riesgo' => 'bajo', 'aprobado' => true]);

        // ===== Domicilios múltiples (casa · negocio · familiar) =====
        DomicilioCliente::create(['cliente_id' => $roble->id, 'etiqueta' => 'Negocio', 'direccion' => 'Av. San Nicolás 450', 'localidad' => 'La Rioja', 'provincia' => 'La Rioja', 'referencia' => 'Frente a la plaza, cortina verde', 'contacto' => 'Marta (encargada)', 'telefono' => '380-4111-222', 'zona_id' => $zRiojaEste->id, 'latitud' => -29.4131, 'longitud' => -66.8558, 'uso' => 'ambos', 'es_principal' => true]);
        DomicilioCliente::create(['cliente_id' => $roble->id, 'etiqueta' => 'Depósito', 'direccion' => 'Ruta 5 Km 3, Parque Industrial', 'localidad' => 'La Rioja', 'provincia' => 'La Rioja', 'referencia' => 'Portón azul, entregar de 8 a 12', 'uso' => 'entrega', 'zona_id' => $zRiojaEste->id]);
        DomicilioCliente::create(['cliente_id' => $andina->id, 'etiqueta' => 'Casa', 'direccion' => 'Belgrano 1240', 'localidad' => 'Chilecito', 'provincia' => 'La Rioja', 'referencia' => 'Casa de rejas negras, entre Sarmiento y Mitre', 'contacto' => 'Sra. Pérez', 'uso' => 'ambos', 'es_principal' => true]);
        DomicilioCliente::create(['cliente_id' => $andina->id, 'etiqueta' => 'Casa de la hija', 'direccion' => 'Los Álamos 87', 'localidad' => 'Chilecito', 'provincia' => 'La Rioja', 'referencia' => 'Cobrar acá los sábados', 'contacto' => 'Julieta', 'uso' => 'cobro']);
        DomicilioCliente::create(['cliente_id' => $obras->id, 'etiqueta' => 'Obra en curso', 'direccion' => 'Rivadavia 2100', 'localidad' => 'La Rioja', 'provincia' => 'La Rioja', 'referencia' => 'Preguntar por el capataz', 'uso' => 'entrega', 'es_principal' => true]);

        // Cuenta corriente de clientes (debe/haber)
        MovimientoCliente::create(['cliente_id' => $roble->id, 'tipo' => 'debe', 'concepto' => 'Venta FAC-1041 (Cuenta corriente)', 'monto' => 2350, 'fecha' => $hoy->copy()->subDays(8), 'referencia' => 'FAC-1041']);
        MovimientoCliente::create(['cliente_id' => $roble->id, 'tipo' => 'haber', 'concepto' => 'Pago en efectivo', 'monto' => 1000, 'fecha' => $hoy->copy()->subDays(3)]);
        MovimientoCliente::create(['cliente_id' => $obras->id, 'tipo' => 'debe', 'concepto' => 'Venta FAC-1038 (Cuenta corriente)', 'monto' => 12400, 'fecha' => $hoy->copy()->subDays(12), 'referencia' => 'FAC-1038']);
        MovimientoCliente::create(['cliente_id' => $andina->id, 'tipo' => 'debe', 'concepto' => 'Venta FAC-1039 (Pago semanal)', 'monto' => 64000, 'fecha' => $hoy->copy()->subDays(20), 'referencia' => 'FAC-1039']);
        MovimientoCliente::create(['cliente_id' => $andina->id, 'tipo' => 'haber', 'concepto' => 'Pago semanal (3 cuotas)', 'monto' => 24000, 'fecha' => $hoy->copy()->subDays(2)]);

        // Cheques de clientes (a depositar)
        ChequeCliente::create(['cliente_id' => $roble->id, 'numero' => 'C-77231', 'banco' => 'Banco Galicia', 'monto' => 4820, 'fecha_vencimiento' => $hoy->copy()->subWeekday(), 'fecha_deposito' => $hoy->copy(), 'estado' => 'pendiente']);
        ChequeCliente::create(['cliente_id' => $obras->id, 'numero' => 'C-88200', 'banco' => 'Banco Macro', 'monto' => 58500, 'fecha_vencimiento' => $hoy->copy()->addDays(14), 'fecha_deposito' => $hoy->copy()->addDays(15), 'estado' => 'pendiente']);
        ChequeCliente::create(['cliente_id' => $andina->id, 'numero' => 'C-90050', 'banco' => 'Banco Nación', 'monto' => 45000, 'fecha_vencimiento' => $hoy->copy()->addDays(2), 'fecha_deposito' => $hoy->copy()->addDays(3), 'estado' => 'pendiente']);
        ChequeCliente::create(['cliente_id' => $andina->id, 'numero' => 'C-90011', 'banco' => 'Banco Nación', 'monto' => 45000, 'fecha_vencimiento' => $hoy->copy()->subDays(6), 'fecha_deposito' => $hoy->copy()->subDays(5), 'estado' => 'rechazado', 'motivo_rechazo' => 'Sin fondos']);

        // ===== Ventas =====
        // Pendiente de aprobación (aparece en dashboard / notificaciones / aprobaciones)
        $ventaPend = Venta::create(['numero' => 'FAC-1043', 'local_id' => $localA->id, 'vendedor_id' => $ricardo->id, 'cliente_id' => $roble->id, 'cliente_nombre' => $roble->nombre, 'medio_pago' => 'Contado', 'fecha' => now(), 'total' => 1240.00, 'estado' => 'pendiente']);
        VentaItem::create(['venta_id' => $ventaPend->id, 'producto_id' => $productos['DRILL-XL200']->id, 'cantidad' => 1, 'precio_unitario' => 1240.00]);

        // Venta a crédito pendiente (cliente riesgo alto)
        $ventaCred = Venta::create(['numero' => 'FAC-1044', 'local_id' => $localB->id, 'vendedor_id' => $carlos->id, 'cliente_id' => $andina->id, 'cliente_nombre' => $andina->nombre, 'medio_pago' => 'Cuenta corriente', 'credito' => true, 'fecha' => now(), 'total' => 3600.00, 'estado' => 'pendiente']);
        VentaItem::create(['venta_id' => $ventaCred->id, 'producto_id' => $productos['HOGAR-SILLA']->id, 'cantidad' => 1, 'precio_unitario' => 3600.00]);

        // Aprobadas: distribuidas por mes (tendencia) y vendedor (ranking) con items (top productos)
        $vendedores = [$marcos, $ana, $carlos, $ricardo];
        $prodList = ['DRILL-XL200', 'HIERRO-T', 'STORAGE-HC', 'MIXER-500', 'MECHA-HSS'];
        $num = 1000;
        for ($m = 5; $m >= 0; $m--) {                       // últimos 6 meses
            $fecha = $hoy->copy()->subMonthsNoOverflow($m)->day(min(15, $hoy->day));
            $ventasMes = 3 + $m % 2;                        // 3-4 ventas por mes
            for ($k = 0; $k < $ventasMes; $k++) {
                $vend = $vendedores[($m + $k) % count($vendedores)];
                $local = $vend->local_id ?? $localA->id;
                $venta = Venta::create([
                    'numero' => 'FAC-' . (++$num),
                    'local_id' => $local,
                    'vendedor_id' => $vend->id,
                    'aprobada_por' => $dueno->id,
                    'medio_pago' => 'Contado',
                    'fecha' => $fecha->copy()->addDays($k),
                    'total' => 0,
                    'estado' => 'aprobada',
                ]);
                $total = 0;
                $items = 1 + ($k % 2);                      // 1-2 ítems
                for ($it = 0; $it < $items; $it++) {
                    $cod = $prodList[($m + $k + $it) % count($prodList)];
                    $prod = $productos[$cod];
                    $precio = (float) StockLocal::where('producto_id', $prod->id)->where('local_id', $local)->value('precio_venta');
                    if ($precio <= 0) {
                        $precio = (float) StockLocal::where('producto_id', $prod->id)->value('precio_venta');
                    }
                    $cant = 1 + (($m + $it) % 4);
                    VentaItem::create(['venta_id' => $venta->id, 'producto_id' => $prod->id, 'cantidad' => $cant, 'precio_unitario' => $precio]);
                    $total += $precio * $cant;
                }
                $venta->update(['total' => $total]);
            }
        }

        // ===== Venta a crédito DEMO (cronograma de cuotas; algunas vencidas → mora) =====
        $ventaPlan = Venta::create([
            'numero' => 'FAC-1050', 'local_id' => $localA->id, 'vendedor_id' => $ricardo->id, 'aprobada_por' => $dueno->id,
            'cliente_id' => $roble->id, 'cliente_nombre' => $roble->nombre, 'medio_pago' => 'Pago diario', 'credito' => true,
            'fecha' => $hoy->copy()->subDays(6), 'total' => 120000, 'estado' => 'aprobada',
            'plan_codigo' => 'd30_020', 'plan_nombre' => '30% anticipo + 0,20 diario', 'modalidad' => 'diario',
            'anticipo' => 36000, 'saldo_financiado' => 84000, 'plazo' => 20, 'cuota' => 0,
            'fecha_primera_cuota' => $hoy->copy()->subDays(6),
            'zona_cobranza' => 'Rioja Este', 'zona_id' => $zRiojaEste->id, 'cobrador' => 'Marcos Lima',
        ]);
        VentaItem::create(['venta_id' => $ventaPlan->id, 'producto_id' => $productos['STORAGE-HC']->id, 'cantidad' => 1, 'precio_unitario' => 120000]);

        $calcPlan = PlanesCredito::calcular('d30_020', 120000, 20);
        $cronograma = PlanesCredito::cronograma($calcPlan, $hoy->copy()->subDays(6)); // 1ª cuota venció hace 6 días
        $tasaMora = PlanesCredito::tasaMoraDiaria('d30_020');
        MovimientoCliente::create(['cliente_id' => $roble->id, 'tipo' => 'debe', 'concepto' => "Venta {$ventaPlan->numero} — saldo financiado (20 cuotas, diario)", 'monto' => $calcPlan['total_financiado'], 'fecha' => $hoy->copy()->subDays(6), 'referencia' => $ventaPlan->numero]);
        foreach ($cronograma as $c) {
            Cuota::create([
                'venta_id' => $ventaPlan->id, 'cliente_id' => $roble->id, 'numero' => $c['numero'],
                'fecha_vencimiento' => $c['fecha_vencimiento'], 'monto' => $c['monto'], 'capital' => $c['capital'],
                'interes' => $c['interes'], 'tasa_mora' => $tasaMora, 'estado' => 'pendiente',
                'cobrador' => 'Marcos Lima', 'zona' => 'Rioja Este', 'zona_id' => $zRiojaEste->id,
            ]);
        }

        // ===== Compras / pedidos a proveedores =====
        // Pendiente
        $compraPend = Compra::create(['numero' => 'OC-318', 'proveedor_id' => $aceroSur->id, 'local_id' => $localA->id, 'usuario_id' => $ana->id, 'fecha' => now(), 'fecha_estimada' => $hoy->copy()->addDays(7), 'total' => 4820.00, 'estado' => 'pendiente']);
        CompraItem::create(['compra_id' => $compraPend->id, 'producto_id' => $productos['HIERRO-T']->id, 'cantidad' => 40, 'costo_unitario' => 89.00]);
        CompraItem::create(['compra_id' => $compraPend->id, 'producto_id' => $productos['MECHA-HSS']->id, 'cantidad' => 16, 'costo_unitario' => 55.00]);

        // Aprobada (en camino)
        $compraAprob = Compra::create(['numero' => 'OC-317', 'proveedor_id' => $herramientas->id, 'local_id' => $localB->id, 'usuario_id' => $carlos->id, 'factura_numero' => 'FC-B-1203', 'fecha' => $hoy->copy()->subDays(2), 'fecha_estimada' => $hoy->copy()->addDay(), 'total' => 15200.00, 'estado' => 'aprobada']);
        CompraItem::create(['compra_id' => $compraAprob->id, 'producto_id' => $productos['DRILL-XL200']->id, 'cantidad' => 6, 'costo_unitario' => 880.00]);
        CompraItem::create(['compra_id' => $compraAprob->id, 'producto_id' => $productos['MIXER-500']->id, 'cantidad' => 3, 'costo_unitario' => 1500.00]);

        // Recibida
        $compraRec = Compra::create(['numero' => 'OC-315', 'proveedor_id' => $aceroSur->id, 'local_id' => $localA->id, 'usuario_id' => $ana->id, 'factura_numero' => 'FC-A-0099', 'fecha' => $hoy->copy()->subDays(6), 'fecha_estimada' => $hoy->copy()->subDays(4), 'fecha_llegada' => $hoy->copy()->subDays(4), 'total' => 64000.00, 'estado' => 'recibida']);
        CompraItem::create(['compra_id' => $compraRec->id, 'producto_id' => $productos['STORAGE-HC']->id, 'cantidad' => 12, 'costo_unitario' => 3100.00]);
        CompraItem::create(['compra_id' => $compraRec->id, 'producto_id' => $productos['HIERRO-T']->id, 'cantidad' => 160, 'costo_unitario' => 89.00]);

        // ===== Solicitud de compra pendiente =====
        SolicitudCompra::create(['numero' => 'SOL-77', 'producto_id' => $productos['MECHA-HSS']->id, 'local_id' => $localB->id, 'solicitante_id' => $carlos->id, 'cantidad' => 100, 'estado' => 'pendiente', 'nota' => 'Reposición urgente']);

        // ===== Cuentas por pagar / pagos a proveedores =====
        PagoProveedor::create(['proveedor_id' => $aceroSur->id, 'compra_id' => $compraRec->id, 'monto' => 64000, 'monto_pagado' => 51700, 'fecha_vencimiento' => $hoy->copy()->addDays(5), 'estado' => 'parcial']);
        PagoProveedor::create(['proveedor_id' => $aceroSur->id, 'monto' => 3500, 'monto_pagado' => 0, 'fecha_vencimiento' => $hoy->copy()->subDays(2), 'estado' => 'pendiente']);
        PagoProveedor::create(['proveedor_id' => $herramientas->id, 'compra_id' => $compraAprob->id, 'monto' => 15200, 'monto_pagado' => 10380, 'fecha_vencimiento' => $hoy->copy()->addDays(3), 'estado' => 'parcial']);
        PagoProveedor::create(['proveedor_id' => $hogar->id, 'monto' => 8600, 'monto_pagado' => 8600, 'fecha_pago' => $hoy->copy()->subDays(4), 'estado' => 'pagado']);

        // ===== Cheques emitidos a proveedores (a debitar) =====
        Cheque::create(['numero' => 'CH-5001', 'banco' => 'Banco Nación', 'proveedor_id' => $aceroSur->id, 'monto' => 12300, 'fecha_emision' => $hoy->copy()->subDays(5), 'fecha_vencimiento' => $hoy->copy(), 'estado' => 'pendiente']);
        Cheque::create(['numero' => 'CH-5002', 'banco' => 'Banco Galicia', 'proveedor_id' => $herramientas->id, 'monto' => 4820, 'fecha_emision' => $hoy->copy()->subDays(3), 'fecha_vencimiento' => $hoy->copy()->addDay(), 'estado' => 'pendiente']);
        Cheque::create(['numero' => 'CH-5003', 'banco' => 'Banco Nación', 'proveedor_id' => $aceroSur->id, 'monto' => 8800, 'fecha_emision' => $hoy->copy()->subDays(1), 'fecha_vencimiento' => $hoy->copy()->addDays(6), 'estado' => 'pendiente']);

        // ===== Devoluciones =====
        Devolucion::create(['cliente_id' => $roble->id, 'venta_id' => $ventaPend->id, 'producto_id' => $productos['MIXER-500']->id, 'producto' => 'Hormigonera 500L', 'cantidad' => 1, 'monto' => 2350, 'motivo' => 'Producto fallado', 'medio_pago' => 'cheque', 'condicion' => 'en_condiciones', 'fecha' => $hoy->copy(), 'estado' => 'pendiente']);
        Devolucion::create(['cliente_id' => $obras->id, 'producto_id' => $productos['DRILL-XL200']->id, 'producto' => 'Taladro Industrial XL-200', 'cantidad' => 1, 'monto' => 1240, 'motivo' => 'Falla de motor', 'medio_pago' => 'cuenta_corriente', 'condicion' => 'a_fabrica', 'fecha' => $hoy->copy(), 'estado' => 'pendiente']);
        Devolucion::create(['cliente_id' => $andina->id, 'producto_id' => $productos['MECHA-HSS']->id, 'producto' => 'Mechas HSS x100', 'cantidad' => 2, 'monto' => 162, 'motivo' => 'Rotas', 'medio_pago' => 'semanal', 'condicion' => 'defectuoso', 'fecha' => $hoy->copy()->subDay(), 'estado' => 'pendiente']);
        Devolucion::create(['cliente_id' => $roble->id, 'producto_id' => $productos['STORAGE-HC']->id, 'producto' => 'Estantería de Alta Capacidad', 'cantidad' => 1, 'monto' => 4100, 'motivo' => 'Equivocación de pedido', 'medio_pago' => 'cheque', 'condicion' => 'en_condiciones', 'estado_producto' => 'reingresado', 'fecha' => $hoy->copy()->subDays(5), 'estado' => 'aprobada']);

        // ===== Movimientos de caja (Tesorería) =====
        MovimientoCaja::create(['tipo' => 'ingreso', 'concepto' => 'Cobro diario', 'medio' => 'Efectivo', 'monto' => 15000, 'fecha' => $hoy->copy()->subDays(2), 'local_id' => $localA->id]);
        MovimientoCaja::create(['tipo' => 'egreso', 'concepto' => 'Pago proveedor Acero Sur', 'medio' => 'Transferencia', 'monto' => 22000, 'fecha' => $hoy->copy()->subDays(2), 'referencia' => 'OC-310', 'local_id' => $localA->id]);
        MovimientoCaja::create(['tipo' => 'ingreso', 'concepto' => 'Cheque acreditado C-90011', 'medio' => 'Cheque', 'monto' => 45000, 'fecha' => $hoy->copy()->subDay(), 'local_id' => $localB->id]);
        MovimientoCaja::create(['tipo' => 'ingreso', 'concepto' => 'Cobro diario', 'medio' => 'Efectivo', 'monto' => 15000, 'fecha' => $hoy->copy()->subDay(), 'local_id' => $localA->id]);
        MovimientoCaja::create(['tipo' => 'ingreso', 'concepto' => 'Cobro diario', 'medio' => 'Efectivo', 'monto' => 15000, 'fecha' => $hoy->copy(), 'local_id' => $localA->id]);
        MovimientoCaja::create(['tipo' => 'ingreso', 'concepto' => 'Saldo inicial de caja', 'medio' => 'Apertura', 'monto' => 120000, 'fecha' => $hoy->copy()->subDays(7)]);

        // ===== Actividad reciente =====
        ActivityLog::create(['tipo' => 'venta', 'titulo' => 'Venta Aprobada', 'detalle' => 'FAC-1001 por Marcos Lima', 'usuario_id' => $marcos->id]);
        ActivityLog::create(['tipo' => 'stock', 'titulo' => 'Stock Actualizado', 'detalle' => 'Local A · Hierro +50', 'local_id' => $localA->id]);
        ActivityLog::create(['tipo' => 'alerta', 'titulo' => 'Alerta de Stock Bajo', 'detalle' => 'Mechas (Local B)', 'local_id' => $localB->id]);
        ActivityLog::create(['tipo' => 'login', 'titulo' => 'Inicio de Sesión', 'detalle' => 'El administrador inició sesión', 'usuario_id' => $dueno->id]);
    }
}
