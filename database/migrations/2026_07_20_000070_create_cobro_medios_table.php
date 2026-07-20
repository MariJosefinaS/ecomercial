<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partes de un cobro por MEDIO (pago dividido): un cobro puede pagarse en parte efectivo,
 * parte transferencia y parte cheque (pedido del cliente 2026-07-20). Cada parte impacta su
 * bucket en Tesorería (efectivo en caja / transferencia a conciliar / cheque en cartera) y el
 * detalle va en el comprobante de pago. La suma de las partes = `cobros.monto`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobro_medios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cobro_id')->constrained('cobros')->cascadeOnDelete();
            $table->string('medio', 14);              // efectivo | transferencia | cheque
            $table->decimal('monto', 14, 2);
            $table->string('comprobante')->nullable(); // transferencia
            $table->string('banco')->nullable();
            $table->string('cheque_numero')->nullable();
            $table->string('estado_conciliacion', 14)->default('registrado'); // registrado | conciliado
            $table->foreignId('conciliado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('conciliado_at')->nullable();
            $table->timestamps();

            $table->index(['medio', 'estado_conciliacion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobro_medios');
    }
};
