<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Conceptos configurables que se aplican (en %) sobre el precio de compra para calcular el de venta. */
    public function up(): void
    {
        Schema::create('conceptos_precio', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->decimal('porcentaje', 6, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        DB::table('conceptos_precio')->insert([
            ['nombre' => 'Flete', 'porcentaje' => 5, 'activo' => true, 'orden' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Remarcar', 'porcentaje' => 40, 'activo' => true, 'orden' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('conceptos_precio');
    }
};
