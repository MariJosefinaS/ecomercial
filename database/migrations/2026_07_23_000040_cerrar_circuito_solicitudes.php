<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cierra el circuito de reposición: la solicitud nacía y quedaba colgada en 'pendiente'
 * (nadie la aprobaba ni se convertía en orden de compra).
 * Ahora: solicitud → aprobación → ORDEN DE COMPRA → (flujo existente) recepción.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_compra', function (Blueprint $table) {
            $table->foreignId('proveedor_id')->nullable()->after('producto_id')
                ->constrained('proveedores')->nullOnDelete();          // sugerido del producto, editable
            $table->foreignId('compra_id')->nullable()->after('estado')
                ->constrained('compras')->nullOnDelete();               // orden de compra que la satisface
            $table->foreignId('resuelta_por')->nullable()->after('compra_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('resuelta_at')->nullable()->after('resuelta_por');
            $table->string('motivo_rechazo')->nullable()->after('resuelta_at');
        });

        // Se suma el estado 'convertida' (ya tiene su orden de compra).
        DB::statement("ALTER TABLE solicitudes_compra MODIFY COLUMN estado ENUM('pendiente','aprobada','rechazada','convertida') NOT NULL DEFAULT 'pendiente'");

        // Backfill: el proveedor sugerido sale del producto.
        DB::statement('UPDATE solicitudes_compra s JOIN productos p ON p.id = s.producto_id SET s.proveedor_id = p.proveedor_id WHERE s.proveedor_id IS NULL');
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE solicitudes_compra MODIFY COLUMN estado ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente'");
        Schema::table('solicitudes_compra', function (Blueprint $table) {
            $table->dropForeign(['proveedor_id']);
            $table->dropForeign(['compra_id']);
            $table->dropForeign(['resuelta_por']);
            $table->dropColumn(['proveedor_id', 'compra_id', 'resuelta_por', 'resuelta_at', 'motivo_rechazo']);
        });
    }
};
