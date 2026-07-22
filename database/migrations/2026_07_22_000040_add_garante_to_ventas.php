<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Garante del crédito (como CRED): datos de la persona que garantiza el crédito.
 * Se captura en la nota de pedido a crédito y se ve en el estado de cuenta del crédito.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('garante_nombre')->nullable()->after('credito_barra');
            $table->string('garante_documento')->nullable()->after('garante_nombre');
            $table->string('garante_telefono')->nullable()->after('garante_documento');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['garante_nombre', 'garante_documento', 'garante_telefono']);
        });
    }
};
