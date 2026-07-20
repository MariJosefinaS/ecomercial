<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Recepción de mercadería: quién/cuándo recibió + desglose de factura + estado por ítem. */
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->foreignId('recibido_por')->nullable()->after('usuario_id')->constrained('users')->nullOnDelete();
            $table->timestamp('recibido_at')->nullable()->after('fecha_llegada');
            $table->json('desglose')->nullable()->after('total'); // {subtotal, iva, otros, total}
        });

        Schema::table('compra_items', function (Blueprint $table) {
            $table->integer('cantidad_recibida')->nullable()->after('cantidad');
            $table->string('estado_item', 20)->default('pendiente')->after('cantidad_recibida'); // pendiente|ok|faltante|defectuoso
            $table->string('nota_recepcion')->nullable()->after('estado_item');
        });
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recibido_por');
            $table->dropColumn(['recibido_at', 'desglose']);
        });
        Schema::table('compra_items', function (Blueprint $table) {
            $table->dropColumn(['cantidad_recibida', 'estado_item', 'nota_recepcion']);
        });
    }
};
