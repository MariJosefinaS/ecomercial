<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recepción granular: por ítem se registra cuántos llegaron defectuosos y
 * cuántos no llegaron (faltantes). El stock suma sólo los OK
 * (= facturada - defectuosos - faltantes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compra_items', function (Blueprint $table) {
            $table->integer('cantidad_defectuosa')->default(0)->after('cantidad_recibida');
            $table->integer('cantidad_faltante')->default(0)->after('cantidad_defectuosa');
        });
    }

    public function down(): void
    {
        Schema::table('compra_items', function (Blueprint $table) {
            $table->dropColumn(['cantidad_defectuosa', 'cantidad_faltante']);
        });
    }
};
