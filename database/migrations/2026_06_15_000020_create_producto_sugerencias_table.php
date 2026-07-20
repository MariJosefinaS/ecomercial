<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Venta cruzada: qué productos se sugieren al cargar uno (curado manual). */
    public function up(): void
    {
        Schema::create('producto_sugerencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('sugerido_id')->constrained('productos')->cascadeOnDelete();
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->unique(['producto_id', 'sugerido_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_sugerencias');
    }
};
