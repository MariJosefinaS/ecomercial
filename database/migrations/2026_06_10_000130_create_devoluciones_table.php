<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devoluciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('producto')->nullable();              // nombre denormalizado
            $table->unsignedInteger('cantidad')->default(1);
            $table->decimal('monto', 14, 2)->default(0);
            $table->string('motivo');
            $table->string('medio_pago')->nullable();            // cheque | cuenta_corriente | diario | semanal | contado | ...
            $table->enum('condicion', ['en_condiciones', 'a_fabrica', 'defectuoso'])->default('en_condiciones');
            // seguimiento del producto devuelto (null hasta aprobar)
            $table->enum('estado_producto', ['reingresado', 'enviado_a_fabrica', 'en_reparacion', 'defectuoso'])->nullable();
            $table->date('fecha');
            $table->enum('estado', ['pendiente', 'aprobada', 'rechazada'])->default('pendiente');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devoluciones');
    }
};
