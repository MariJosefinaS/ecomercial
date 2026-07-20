<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo EDITABLE de planes de crédito (decisión del usuario 2026-06-19).
 * Reemplaza el array hardcodeado de App\Support\PlanesCredito; se administra en
 * Configuración → "Productos de crédito".
 *
 * ⚠️ tasa_periodo PROVISIONAL (interés simple % por día/semana) — falta confirmar
 * las tasas exactas con el cliente; se editan acá sin tocar código.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planes_credito', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nombre');
            $table->string('modalidad', 20)->default('diario');   // contado | diario | semanal
            $table->decimal('anticipo_pct', 6, 2)->default(0);     // % de anticipo mínimo
            $table->decimal('tasa_periodo', 8, 4)->default(0);     // % de interés por período (día/semana)
            $table->unsignedInteger('plazo_default')->default(0);  // cantidad de días/semanas
            $table->string('unidad', 12)->default('días');         // días | semanas | ''
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('planes_credito')->insert([
            ['codigo' => 'contado', 'nombre' => 'Contado', 'modalidad' => 'contado', 'anticipo_pct' => 100, 'tasa_periodo' => 0, 'plazo_default' => 0, 'unidad' => '', 'orden' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'd30_020', 'nombre' => '30% anticipo + 0,20 diario', 'modalidad' => 'diario', 'anticipo_pct' => 30, 'tasa_periodo' => 0.20, 'plazo_default' => 100, 'unidad' => 'días', 'orden' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 's50_si0', 'nombre' => '50% anticipo, saldo sin interés', 'modalidad' => 'diario', 'anticipo_pct' => 50, 'tasa_periodo' => 0, 'plazo_default' => 100, 'unidad' => 'días', 'orden' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'd0_045', 'nombre' => '0% anticipo + 0,45 diario', 'modalidad' => 'diario', 'anticipo_pct' => 0, 'tasa_periodo' => 0.45, 'plazo_default' => 100, 'unidad' => 'días', 'orden' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'm5_13s', 'nombre' => '30% anticipo en 13 semanas (M5)', 'modalidad' => 'semanal', 'anticipo_pct' => 30, 'tasa_periodo' => 0.20, 'plazo_default' => 13, 'unidad' => 'semanas', 'orden' => 5, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('planes_credito');
    }
};
