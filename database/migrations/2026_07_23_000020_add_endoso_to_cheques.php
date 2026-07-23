<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cartera de cheques: un cheque de TERCEROS (recibido de un cliente) se puede ENDOSAR
 * para pagarle a un proveedor. El endoso NO mueve la caja (ese cheque nunca ingresó a
 * caja: solo lo hace al depositarse), pero sí cancela la obligación con el proveedor.
 * Pasa por el tablero de autorización de pagos (jefe autoriza → tesorero procesa).
 */
return new class extends Migration
{
    public function up(): void
    {
        // El enum de cheques_cliente.estado suma 'endosado'.
        DB::statement("ALTER TABLE cheques_cliente MODIFY COLUMN estado ENUM('pendiente','depositado','acreditado','rechazado','endosado') NOT NULL DEFAULT 'pendiente'");

        Schema::table('cheques_cliente', function (Blueprint $table) {
            $table->foreignId('endosado_a_proveedor_id')->nullable()->after('estado')
                ->constrained('proveedores')->nullOnDelete();
            $table->timestamp('endosado_at')->nullable()->after('endosado_a_proveedor_id');
        });

        // El pedido de pago puede saldarse endosando un cheque de la cartera.
        Schema::table('pedidos_pago', function (Blueprint $table) {
            $table->foreignId('cheque_cliente_id')->nullable()->after('cheque_numero')
                ->constrained('cheques_cliente')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_pago', function (Blueprint $table) {
            $table->dropForeign(['cheque_cliente_id']);
            $table->dropColumn('cheque_cliente_id');
        });
        Schema::table('cheques_cliente', function (Blueprint $table) {
            $table->dropForeign(['endosado_a_proveedor_id']);
            $table->dropColumn(['endosado_a_proveedor_id', 'endosado_at']);
        });
        DB::statement("ALTER TABLE cheques_cliente MODIFY COLUMN estado ENUM('pendiente','depositado','acreditado','rechazado') NOT NULL DEFAULT 'pendiente'");
    }
};
