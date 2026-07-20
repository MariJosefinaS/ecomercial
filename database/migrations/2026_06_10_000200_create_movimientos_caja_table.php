<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Flujo de fondos de Tesorería: ingresos y egresos de caja. */
    public function up(): void
    {
        Schema::create('movimientos_caja', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['ingreso', 'egreso'])->index();
            $table->string('concepto');
            $table->string('medio')->nullable();        // Efectivo | Transferencia | Cheque | ...
            $table->decimal('monto', 14, 2)->default(0);
            $table->date('fecha')->index();
            $table->string('referencia')->nullable();   // FAC-xxx / OC-xxx / C-xxx
            $table->foreignId('local_id')->nullable()->constrained('locales')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_caja');
    }
};
