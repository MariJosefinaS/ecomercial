<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rendición de efectivo del cobrador: el admin (Tesorería) registra cuánto EFECTIVO recibió
 * frente a lo esperado (Σ de las partes efectivo de los cobros del día). La diferencia
 * (faltante/sobrante) se ajusta en caja. Transferencias/cheques se concilian en cobro_medios.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rendiciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cobrador_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha');
            $table->decimal('total_esperado', 14, 2)->default(0);
            $table->decimal('total_recibido', 14, 2)->default(0);
            $table->decimal('diferencia', 14, 2)->default(0); // recibido - esperado (negativo = faltante)
            $table->unsignedInteger('cantidad_cobros')->default(0);
            $table->string('nota')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['cobrador_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rendiciones');
    }
};
