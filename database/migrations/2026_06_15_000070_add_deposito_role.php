<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Nuevo rol de sistema: Encargado de Depósito (recepción de mercadería). */
    public function up(): void
    {
        if (! DB::table('roles')->where('clave', 'deposito')->exists()) {
            DB::table('roles')->insert([
                'clave' => 'deposito',
                'nombre' => 'Encargado de Depósito',
                'variante' => 'amber',
                'es_sistema' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('roles')->where('clave', 'deposito')->where('es_sistema', true)->delete();
    }
};
