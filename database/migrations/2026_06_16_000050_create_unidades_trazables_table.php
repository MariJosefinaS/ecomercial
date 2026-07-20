<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger de trazabilidad: una fila por UNIDAD recibida en buen estado, con un
 * código único que se imprime en la etiqueta de la caja. Sigue a la unidad por
 * su vida: stock → entrega (venta) → devolución → traspaso entre sucursales.
 * Corre en paralelo a stock_locales (que sigue siendo la cantidad operativa).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades_trazables', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->foreignId('remito_item_id')->nullable()->constrained('remito_items')->nullOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->foreignId('local_id')->constrained('locales');     // ubicación actual
            $table->decimal('costo', 12, 2)->default(0);
            $table->string('estado', 20)->default('en_stock');         // en_stock|entregado|devuelto|en_reparacion|baja
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->timestamp('entregado_at')->nullable();
            $table->timestamp('devuelto_at')->nullable();
            $table->timestamps();
            $table->index(['producto_id', 'local_id', 'estado']);
        });

        Schema::create('unidad_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_id')->constrained('unidades_trazables')->cascadeOnDelete();
            $table->string('tipo', 20);                                // recepcion|traspaso|entrega|devolucion|reparacion|baja
            $table->foreignId('local_id')->nullable()->constrained('locales');
            $table->string('referencia')->nullable();                  // nº remito/venta/traspaso/devolución
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('nota')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidad_eventos');
        Schema::dropIfExists('unidades_trazables');
    }
};
