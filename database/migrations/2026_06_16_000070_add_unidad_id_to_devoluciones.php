<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vincula la devolución a la unidad trazable (la caja por su código), para
 * identificar al instante proveedor de origen y la venta/fecha en que salió.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devoluciones', function (Blueprint $table) {
            $table->foreignId('unidad_id')->nullable()->after('venta_id')->constrained('unidades_trazables')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('devoluciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unidad_id');
        });
    }
};
