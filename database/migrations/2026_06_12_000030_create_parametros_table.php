<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parámetros globales del sistema (key-value). Hoy lo usa el cálculo de EOQ
 * (lote óptimo de reposición), que necesita valores que no viven en otra tabla:
 * costo de emitir un pedido, tasa anual de mantenimiento de inventario y nivel
 * de servicio para el stock de seguridad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametros', function (Blueprint $table) {
            $table->string('clave', 60)->primary();
            $table->string('valor', 255)->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('parametros')->insert([
            ['clave' => 'eoq_costo_pedido', 'valor' => '5000', 'created_at' => $now, 'updated_at' => $now],     // $ por orden de compra
            ['clave' => 'eoq_tasa_mantenimiento', 'valor' => '25', 'created_at' => $now, 'updated_at' => $now], // % anual del valor inmovilizado
            ['clave' => 'eoq_nivel_servicio', 'valor' => '95', 'created_at' => $now, 'updated_at' => $now],     // % (define el z del stock de seguridad)
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('parametros');
    }
};
