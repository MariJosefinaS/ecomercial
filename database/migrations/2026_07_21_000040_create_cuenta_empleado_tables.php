<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuenta corriente del empleado (cobrador):
 *  - `movimientos_empleado`: ledger. 'haber' = comisión devengada (a favor del empleado, se acredita
 *    cuando Tesorería CONFIRMA la cobranza); 'debe' = pago al empleado. Saldo a favor = Σhaber − Σdebe.
 *  - `pagos_empleado`: cada pago que el tesorero le hace al empleado (para el recibo firmable + caja).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_empleado', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('users')->cascadeOnDelete();
            $table->string('tipo', 10);                 // haber (comisión) | debe (pago) | ajuste
            $table->string('concepto');
            $table->decimal('monto', 14, 2);
            $table->string('referencia')->nullable();   // idempotencia (ej. "medio:123") / traza
            $table->dateTime('fecha');
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['empleado_id', 'fecha']);
            $table->index('referencia');
        });

        Schema::create('pagos_empleado', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('monto', 14, 2);
            $table->string('medio', 14);                // efectivo | transferencia
            $table->string('comprobante')->nullable();
            $table->string('banco')->nullable();
            $table->string('nota')->nullable();
            $table->decimal('saldo_antes', 14, 2)->default(0);
            $table->decimal('saldo_despues', 14, 2)->default(0);
            $table->dateTime('fecha');
            $table->boolean('firmado')->default(false); // el empleado firmó el recibo
            $table->dateTime('firmado_at')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete(); // tesorero
            $table->timestamps();
            $table->index(['empleado_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_empleado');
        Schema::dropIfExists('movimientos_empleado');
    }
};
