<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acceso ANTERIOR del usuario: al loguearse, el ultimo_acceso vigente se guarda acá antes de
 * pisarlo con "ahora". Así, en el perfil, "Último acceso" muestra la sesión ANTERIOR (útil),
 * no el momento actual del login.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('acceso_previo')->nullable()->after('ultimo_acceso');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('acceso_previo');
        });
    }
};
