<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nota de Pedido: flag "sugerido" por renglón (venta cruzada) y cobrador asignado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta_items', function (Blueprint $table) {
            $table->boolean('sugerido')->default(false)->after('precio_unitario');
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->string('cobrador')->nullable()->after('zona_cobranza');
        });
    }

    public function down(): void
    {
        Schema::table('venta_items', function (Blueprint $table) {
            $table->dropColumn('sugerido');
        });
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn('cobrador');
        });
    }
};
