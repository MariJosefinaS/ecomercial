<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adelanto de sueldo: el empleado lo SOLICITA (estado pendiente), el SUPER ADMIN lo aprueba/rechaza,
 * y recién aprobado el tesorero puede pagarlo (genera el pago + egreso en caja). Nunca antes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adelantos_sueldo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('monto', 14, 2);
            $table->string('motivo')->nullable();
            $table->string('estado', 12)->default('pendiente'); // pendiente | aprobado | rechazado | pagado
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('aprobado_at')->nullable();
            $table->string('motivo_rechazo')->nullable();
            $table->foreignId('pago_empleado_id')->nullable()->constrained('pagos_empleado')->nullOnDelete();
            $table->timestamps();
            $table->index(['empleado_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adelantos_sueldo');
    }
};
