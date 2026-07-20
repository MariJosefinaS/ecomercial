<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->foreignId('cliente_id')->nullable()->after('vendedor_id')->constrained('clientes')->nullOnDelete();
            $table->string('medio_pago', 40)->default('Contado')->after('cliente_nombre');
            $table->boolean('credito')->default(false)->after('medio_pago'); // cuenta corriente / pago diario / semanal
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cliente_id');
            $table->dropColumn(['medio_pago', 'credito']);
        });
    }
};
