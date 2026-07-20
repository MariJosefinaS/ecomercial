<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->string('rubro')->nullable()->after('nombre');
            $table->unsignedSmallInteger('dias_entrega')->nullable()->after('direccion'); // demora aprox. de entrega
        });
    }

    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropColumn(['rubro', 'dias_entrega']);
        });
    }
};
