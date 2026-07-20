<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planilla de cobranza del cobrador (Bloque 2). Una por COBRADOR × FECHA × MODALIDAD
 * (diaria/semanal/mensual) → el moroso reaparece en la planilla de SU plan hasta pagar.
 *
 * Lleva la APERTURA/CIERRE con HORA (f_055, cierra ~2:30 AM en su AppSheet) y el estado del
 * flujo del cliente: En confección → Cargada pend. auditoría → Cerrada (auditada por admin).
 * Las líneas (cuotas a cobrar) son DERIVADAS del cronograma (no se duplican): la planilla es el
 * encabezado + totales del día. `total_*` se snapshotean al cerrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planillas_cobranza', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cobrador_id')->constrained('users')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('modalidad', 12);                         // diario | semanal | mensual
            $table->string('estado', 20)->default('en_confeccion');  // en_confeccion | pend_auditoria | cerrada
            $table->dateTime('hora_apertura')->nullable();
            $table->dateTime('hora_cierre')->nullable();
            $table->decimal('total_esperado', 14, 2)->default(0);
            $table->decimal('total_cobrado', 14, 2)->default(0);
            $table->foreignId('auditada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('auditada_at')->nullable();
            $table->timestamps();

            $table->unique(['cobrador_id', 'fecha', 'modalidad']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planillas_cobranza');
    }
};
