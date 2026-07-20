<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un cliente dado de alta por un vendedor durante una venta nace SIN aprobar (aprobado=false);
     * un administrador debe aprobarlo para que la venta pueda concretarse.
     * Los clientes existentes/creados por admin quedan aprobados por defecto.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->boolean('aprobado')->default(true)->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('aprobado');
        });
    }
};
