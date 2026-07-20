<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('tipo_doc', 10)->default('CUIT'); // CUIT | DNI
            $table->string('documento', 20)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('direccion')->nullable();
            $table->decimal('limite_credito', 14, 2)->default(0);
            $table->enum('riesgo', ['bajo', 'medio', 'alto'])->default('bajo'); // análisis crediticio interno
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
