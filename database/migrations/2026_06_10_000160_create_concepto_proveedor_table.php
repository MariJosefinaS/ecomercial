<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Conceptos de precio que cobra CADA proveedor, con su % por defecto.
     * Es el default que se carga al elegir el proveedor en el alta de un producto;
     * después se puede ajustar por producto (override) sin tocar este default.
     */
    public function up(): void
    {
        Schema::create('concepto_proveedor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('proveedores')->cascadeOnDelete();
            $table->foreignId('concepto_precio_id')->constrained('conceptos_precio')->cascadeOnDelete();
            $table->decimal('porcentaje', 6, 2)->default(0);
            $table->timestamps();
            $table->unique(['proveedor_id', 'concepto_precio_id']);
        });

        // Default: cada proveedor existente arranca con los conceptos activos al % global.
        $conceptos = DB::table('conceptos_precio')->where('activo', true)->get();
        $rows = [];
        foreach (DB::table('proveedores')->pluck('id') as $provId) {
            foreach ($conceptos as $c) {
                $rows[] = [
                    'proveedor_id' => $provId,
                    'concepto_precio_id' => $c->id,
                    'porcentaje' => $c->porcentaje,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        if ($rows) {
            DB::table('concepto_proveedor')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('concepto_proveedor');
    }
};
