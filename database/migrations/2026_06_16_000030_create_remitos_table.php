<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remitos: una factura/compra puede entregarse en VARIOS remitos (entregas
 * parciales y/o a distintas sucursales). El remito es lo que físicamente llega
 * al depósito y se controla contra la factura. El saldo no entregado queda
 * pendiente. Cada remito impacta el stock de SU sucursal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remitos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->foreignId('local_id')->constrained('locales');   // sucursal destino
            $table->string('numero')->nullable();                    // nº de remito
            $table->string('estado')->default('recibido');           // recibido
            $table->json('desglose')->nullable();
            $table->foreignId('factura_escaneo_id')->nullable()->constrained('factura_escaneos')->nullOnDelete();
            $table->foreignId('recibido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recibido_at')->nullable();
            $table->text('nota')->nullable();
            $table->timestamps();
        });

        Schema::create('remito_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('remito_id')->constrained('remitos')->cascadeOnDelete();
            $table->foreignId('compra_item_id')->nullable()->constrained('compra_items')->nullOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->integer('cantidad_recibida')->default(0);    // lo que trajo ESTE remito
            $table->integer('cantidad_defectuosa')->default(0);  // de los recibidos, cuántos defectuosos
            $table->string('estado_item')->default('ok');        // ok | parcial | defectuoso
            $table->decimal('costo_unitario', 12, 2)->default(0);
            $table->text('nota')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remito_items');
        Schema::dropIfExists('remitos');
    }
};
