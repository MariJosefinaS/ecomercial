<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columnas que el sistema necesita para IMPORTAR (y reimportar) el catálogo
 * histórico del cliente (CSV PROVEEDORES + STOCK) de forma idempotente.
 *
 *  - codigo_externo: id estable del sistema viejo (PROV-001 / ProductoID) → clave
 *    de upsert; permite volver a correr el import sin duplicar.
 *  - marca: los CSV traen marca por producto y hoy no hay dónde guardarla.
 *  - tags: keywords de búsqueda (Tags_Busqueda) para el buscador de stock.
 *
 * NOTA: no hay costo de compra en el CSV; precio_compra/precio_neto quedan como
 * están (0/null). El precio de venta va a stock_locales (por local).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->string('codigo_externo', 40)->nullable()->unique()->after('id');
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->string('codigo_externo', 40)->nullable()->unique()->after('sku');
            $table->string('marca', 120)->nullable()->after('nombre')->index();
            $table->text('tags')->nullable()->after('detalles');
        });
    }

    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropColumn('codigo_externo');
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['codigo_externo', 'marca', 'tags']);
        });
    }
};
