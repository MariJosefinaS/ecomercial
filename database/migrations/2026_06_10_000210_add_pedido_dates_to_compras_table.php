<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Fechas de seguimiento del pedido/OC para la ficha del proveedor. */
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->date('fecha_estimada')->nullable()->after('fecha'); // llegada estimada
            $table->date('fecha_llegada')->nullable()->after('fecha_estimada'); // llegada real
        });
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->dropColumn(['fecha_estimada', 'fecha_llegada']);
        });
    }
};
