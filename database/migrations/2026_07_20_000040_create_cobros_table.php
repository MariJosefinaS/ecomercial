<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cobros (Bloque 3) — registro OPERATIVO de cada pago que recibe el cobrador.
 *
 * Un cobro puede pagar una cuota, parte de ella, o de más (el excedente adelanta la/s siguiente/s).
 * Guarda el MEDIO (efectivo/transferencia/cheque) y, si es transferencia, el COMPROBANTE. Es la
 * fuente del historial de pagos del cliente y de la rendición/conciliación con administración.
 * Los movimientos contables (movimientos_cliente/movimientos_caja) se PROYECTAN desde acá (no se
 * duplican). `uuid` idempotente para soportar el alta offline sin duplicar al sincronizar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobros', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();                                  // idempotencia (offline-first)
            $table->foreignId('cuota_id')->nullable()->constrained('cuotas')->nullOnDelete(); // cuota principal pagada
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete(); // el crédito
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('cobrador_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('zona_id')->nullable()->constrained('zonas')->nullOnDelete();
            $table->decimal('monto', 14, 2);                                 // lo que entregó el cliente
            $table->string('medio', 14)->default('efectivo');                // efectivo | transferencia | cheque
            $table->string('comprobante')->nullable();                       // archivo (transferencia)
            $table->string('banco')->nullable();                             // cheque/transferencia
            $table->string('cheque_numero')->nullable();
            $table->decimal('excedente', 14, 2)->default(0);                 // parte imputada a cuotas siguientes / saldo a favor
            $table->string('estado_conciliacion', 14)->default('registrado'); // registrado | conciliado
            $table->foreignId('conciliado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('conciliado_at')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('fecha');
            $table->timestamps();

            $table->index(['cobrador_id', 'fecha']);
            $table->index(['medio', 'estado_conciliacion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobros');
    }
};
