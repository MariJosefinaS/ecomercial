<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Imagen principal + mini-ficha de detalles (clave/valor) del producto. */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('imagen')->nullable()->after('nombre');   // ruta en disco public
            $table->json('detalles')->nullable()->after('descripcion'); // [['clave','valor'], ...]
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['imagen', 'detalles']);
        });
    }
};
