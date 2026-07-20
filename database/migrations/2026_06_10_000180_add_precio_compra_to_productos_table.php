<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * precio_compra: costo base con el que se calcula el precio de venta (vía conceptos).
     * conceptos: snapshot JSON de los conceptos aplicados a ESTE producto [{id,nombre,aplica,porcentaje}]
     *            para poder reabrir la edición con el mismo desglose (override por producto).
     */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('precio_compra', 12, 2)->default(0)->after('proveedor_id');
            $table->json('conceptos')->nullable()->after('precio_compra');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['precio_compra', 'conceptos']);
        });
    }
};
