<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Motivo obligatorio al rechazar una venta. */
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('motivo_rechazo')->nullable()->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn('motivo_rechazo');
        });
    }
};
