<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Marca de la última vez que el usuario vio sus notificaciones (campanita) → para "no leídas". */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('notificaciones_vistas_at')->nullable()->after('ultimo_acceso');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notificaciones_vistas_at');
        });
    }
};
