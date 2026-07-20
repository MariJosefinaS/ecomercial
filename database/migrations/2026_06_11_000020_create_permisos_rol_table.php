<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persistencia de la matriz de permisos por rol (antes solo defaults en memoria).
 * Cada fila = (rol, permiso) → permitido. Lo que no está en la tabla usa el
 * default del helper App\Support\Permisos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permisos_rol', function (Blueprint $table) {
            $table->id();
            $table->string('rol', 50);
            $table->string('permiso', 50);
            $table->boolean('permitido')->default(false);
            $table->timestamps();
            $table->unique(['rol', 'permiso']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permisos_rol');
    }
};
