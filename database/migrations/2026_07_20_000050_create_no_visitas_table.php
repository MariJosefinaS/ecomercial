<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Novedades de cobranza: días en que el cobrador NO pasó por una zona (enfermedad, robo, ausencia).
 *
 * Audios del cliente (2026-07-20): si el cobrador no va, el cliente NO debe absorber la mora de esos
 * días ("el cliente recupera el día"). Esos días se DESCUENTAN del cómputo de mora de las cuotas de
 * la zona. Lo administra alguien con permiso (`gestionar_novedades_cobranza`) — NUNCA el cobrador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('no_visitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zona_id')->constrained('zonas')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('motivo', 20)->default('ausente'); // enfermedad | robo | ausente | feriado | otro
            $table->string('nota')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['zona_id', 'fecha']); // una novedad por zona y día
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('no_visitas');
    }
};
