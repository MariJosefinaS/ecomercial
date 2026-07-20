<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zonas de cobranza configurables + cobrador por zona (pedido del cliente 2026-07-20).
 *
 * Antes `zona_cobranza`/`cobrador` eran TEXTO LIBRE en ventas y cuotas. Ahora la zona es una
 * entidad con un cobrador (User) asignado; elegir la zona en la Nota de Pedido auto-completa el
 * cobrador (como en el AppSheet del cliente, frame f_050).
 *
 * REUSO consciente: el cobrador es un `User` existente (no se crea rol nuevo; `vendedor` ya cobra).
 * `nullOnDelete` en todas las FK → borrar/dar de baja un usuario o una zona NO rompe ventas/cuotas
 * (requisito: poder eliminar usuarios). Los strings viejos quedan como snapshot de display.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zonas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->foreignId('local_id')->nullable()->constrained('locales')->nullOnDelete(); // sucursal
            $table->foreignId('cobrador_id')->nullable()->constrained('users')->nullOnDelete();  // cobrador ACTUAL
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->foreignId('zona_id')->nullable()->after('direccion')->constrained('zonas')->nullOnDelete();
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->foreignId('zona_id')->nullable()->after('zona_cobranza')->constrained('zonas')->nullOnDelete();
        });

        Schema::table('cuotas', function (Blueprint $table) {
            // La cuota pertenece a una zona → el cobrador se resuelve por zona.cobrador_id (ACTUAL).
            // Reasignar el cobrador de la zona mueve todas sus cuotas abiertas al nuevo cobrador.
            $table->foreignId('zona_id')->nullable()->after('zona')->constrained('zonas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cuotas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('zona_id');
        });
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('zona_id');
        });
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('zona_id');
        });
        Schema::dropIfExists('zonas');
    }
};
