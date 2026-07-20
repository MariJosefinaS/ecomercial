<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de roles persistido. Antes los 4 roles del sistema estaban
 * hardcodeados en Configuracion\Index::mount() y los roles "custom" creados
 * por el super admin se perdían al recargar (solo se guardaban sus permisos
 * en `permisos_rol`, no el rol en sí). Ahora el rol vive acá.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 50)->unique();
            $table->string('nombre');
            $table->string('variante', 20)->default('gray'); // color del badge
            $table->boolean('es_sistema')->default(false);
            $table->timestamps();
        });

        // Roles base del sistema (no se pueden borrar).
        $now = now();
        DB::table('roles')->insert([
            ['clave' => 'super_admin', 'nombre' => 'Super Admin', 'variante' => 'brand', 'es_sistema' => true, 'created_at' => $now, 'updated_at' => $now],
            ['clave' => 'admin_local', 'nombre' => 'Admin de Local', 'variante' => 'blue', 'es_sistema' => true, 'created_at' => $now, 'updated_at' => $now],
            ['clave' => 'vendedor', 'nombre' => 'Vendedor', 'variante' => 'green', 'es_sistema' => true, 'created_at' => $now, 'updated_at' => $now],
            ['clave' => 'empleado', 'nombre' => 'Empleado', 'variante' => 'gray', 'es_sistema' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
