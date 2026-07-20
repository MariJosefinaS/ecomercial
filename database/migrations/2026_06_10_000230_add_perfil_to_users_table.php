<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Datos de perfil del empleado (gestión de usuarios). */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('telefono')->nullable()->after('email');
            $table->string('avatar')->nullable()->after('telefono'); // ruta del avatar subido
            $table->timestamp('ultimo_acceso')->nullable()->after('avatar');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telefono', 'avatar', 'ultimo_acceso']);
        });
    }
};
