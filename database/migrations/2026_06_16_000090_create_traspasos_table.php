<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traspasos de mercadería entre sucursales. Se mueven UNIDADES por su código de
 * trazabilidad (el código se reutiliza; la unidad cambia de local y queda el
 * evento de traspaso en su historia). Requiere aprobación del administrador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traspasos', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 40)->unique();
            $table->foreignId('local_origen_id')->constrained('locales');
            $table->foreignId('local_destino_id')->constrained('locales');
            $table->foreignId('usuario_id')->constrained('users');
            $table->foreignId('aprobada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha');
            $table->text('motivo')->nullable();
            $table->string('estado', 20)->default('pendiente');   // pendiente|aprobada|rechazada
            $table->text('motivo_rechazo')->nullable();
            $table->timestamps();
        });

        Schema::create('traspaso_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('traspaso_id')->constrained('traspasos')->cascadeOnDelete();
            $table->foreignId('unidad_id')->constrained('unidades_trazables');
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traspaso_items');
        Schema::dropIfExists('traspasos');
    }
};
