<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cobro NO RENDIDO / robado: una parte de cobro cuyo efectivo el cobrador no entregó.
 * estado_conciliacion pasa a 'no_rendido'. El cliente NO se afecta (pagó, tiene recibo);
 * se revierte el ingreso en caja y se le CARGA el importe al cobrador (cuenta del empleado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cobro_medios', function (Blueprint $table) {
            $table->string('no_rendido_motivo')->nullable()->after('estado_conciliacion');
        });
    }

    public function down(): void
    {
        Schema::table('cobro_medios', function (Blueprint $table) {
            $table->dropColumn('no_rendido_motivo');
        });
    }
};
