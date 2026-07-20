<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `users.rol` era un ENUM con los 4 roles fijos del sistema, lo que impedía
 * asignar a un usuario un rol custom creado en Configuración. Se pasa a VARCHAR
 * (la integridad la dan ahora la tabla `roles` + la validación del componente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('rol', 50)->default('vendedor')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('rol', ['super_admin', 'admin_local', 'vendedor', 'empleado'])
                  ->default('vendedor')->change();
        });
    }
};
