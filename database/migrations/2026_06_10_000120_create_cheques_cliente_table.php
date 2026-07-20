<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Cheques que el cliente emite para pagar (financiación propia). */
    public function up(): void
    {
        Schema::create('cheques_cliente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->string('numero', 60);
            $table->string('banco')->nullable();
            $table->decimal('monto', 14, 2);
            $table->date('fecha_vencimiento');
            $table->date('fecha_deposito')->nullable(); // = vencimiento + 1 día hábil
            $table->enum('estado', ['pendiente', 'depositado', 'acreditado', 'rechazado'])->default('pendiente');
            $table->string('motivo_rechazo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cheques_cliente');
    }
};
