<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Circuito de aprobación de las no-visitas (pedido del cliente 2026-07-20):
 * el COBRADOR puede REPORTAR que no cobró (estado 'pendiente'), pero recién suspende la mora
 * cuando un SUPERVISOR lo APRUEBA ('aprobada'). El supervisor puede crearlas directamente aprobadas.
 * Solo las 'aprobada' descuentan mora (ver App\Models\NoVisita::fechasDeZona()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('no_visitas', function (Blueprint $table) {
            $table->string('estado', 12)->default('aprobada')->after('motivo'); // pendiente | aprobada | rechazada
            $table->foreignId('solicitado_por')->nullable()->after('registrado_por')->constrained('users')->nullOnDelete();
            $table->foreignId('aprobado_por')->nullable()->after('solicitado_por')->constrained('users')->nullOnDelete();
            $table->dateTime('aprobado_at')->nullable()->after('aprobado_por');
        });
    }

    public function down(): void
    {
        Schema::table('no_visitas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('solicitado_por');
            $table->dropConstrainedForeignId('aprobado_por');
            $table->dropColumn(['estado', 'aprobado_at']);
        });
    }
};
