<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Cuenta corriente del cliente (debe/haber) para consulta y análisis de riesgo. */
    public function up(): void
    {
        Schema::create('movimientos_cliente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->enum('tipo', ['debe', 'haber']); // debe = deuda del cliente; haber = pago/crédito
            $table->string('concepto');
            $table->decimal('monto', 14, 2);
            $table->date('fecha');
            $table->string('referencia')->nullable(); // FAC-xxx, cheque, devolución, etc.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_cliente');
    }
};
