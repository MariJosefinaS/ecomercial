<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cronograma de cobros programados de una venta a crédito (decisión del usuario
 * 2026-06-19, modelo "cuota fija con interés incluido").
 *
 * Se genera al APROBAR la venta. Cada fila es una cuota con su vencimiento; el
 * interés ya viene embebido (capital + interes son para reporte). Alimenta la
 * cuenta corriente (vencido / a vencer) y la proyección de Tesorería.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->unsignedInteger('numero');                 // 1..N
            $table->date('fecha_vencimiento');
            $table->decimal('monto', 14, 2);                   // lo que se cobra esta cuota
            $table->decimal('capital', 14, 2)->default(0);     // desglose (reporte)
            $table->decimal('interes', 14, 2)->default(0);     // desglose (reporte)
            // Mora: tasa diaria (snapshot del plan) que devenga el saldo impago por día de atraso.
            $table->decimal('tasa_mora', 8, 4)->default(0);
            // estado guardado: pendiente | cobrada | anulada. "vencida" es DERIVADO
            // (pendiente && vencimiento <= hoy) para que reaparezca en la planilla cada día.
            $table->string('estado', 12)->default('pendiente');
            $table->decimal('pagado_monto', 14, 2)->default(0);
            $table->string('cobrador')->nullable();
            $table->string('zona')->nullable();
            $table->timestamp('cobrada_at')->nullable();
            $table->timestamps();

            $table->index(['estado', 'fecha_vencimiento']);
            $table->index(['cliente_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuotas');
    }
};
