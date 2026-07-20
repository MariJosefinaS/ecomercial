<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stock y PRECIO por producto en cada local.
     * El precio vive acá (no en productos) → de comparar el mismo producto
     * entre locales salen las "alertas de diferencia de precio".
     */
    public function up(): void
    {
        Schema::create('stock_locales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('local_id')->constrained('locales')->cascadeOnDelete();
            $table->integer('cantidad')->default(0);
            $table->integer('stock_minimo')->default(0);
            $table->decimal('precio_venta', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['producto_id', 'local_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_locales');
    }
};
