<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pedido de pago (Tablero de Autorización, como GENESIS Finanzas):
 * alguien SOLICITA un pago → el jefe lo AUTORIZA (o rechaza/anula) → el tesorero lo PROCESA
 * (recién ahí se hace el egreso en caja y se imputa la obligación). Julio autoriza, Andrea procesa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos_pago', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 12);                 // proveedor | empleado | gasto
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->foreignId('obligacion_id')->nullable()->constrained('pagos_proveedor')->nullOnDelete();
            $table->foreignId('empleado_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('adelanto_id')->nullable()->constrained('adelantos_sueldo')->nullOnDelete();
            $table->string('beneficiario');             // nombre para mostrar
            $table->string('concepto');
            $table->decimal('importe', 14, 2);
            $table->string('medio', 14);                // efectivo | transferencia | cheque
            $table->string('comprobante')->nullable();
            $table->string('banco')->nullable();
            $table->string('cheque_numero')->nullable();
            $table->string('comentario')->nullable();
            $table->string('estado', 12)->default('pendiente'); // pendiente | autorizado | rechazado | pagado | anulado
            $table->foreignId('solicitado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('autorizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('autorizado_at')->nullable();
            $table->string('motivo_rechazo')->nullable();
            $table->foreignId('procesado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('procesado_at')->nullable();
            $table->string('resultado_ref')->nullable(); // ej. "PagoEmpleado:12"
            $table->timestamps();
            $table->index(['estado', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos_pago');
    }
};
