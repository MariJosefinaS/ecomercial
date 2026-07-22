<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Umbral de incobrable POR PLAN: cantidad de cuotas vencidas a partir de la cual un crédito de
 * ese plan se considera INCOBRABLE y deja de aparecer en la planilla del cobrador.
 * Como cada plan tiene su modalidad (diaria/semanal/mensual), el umbral queda "por tipo de plan".
 * 0 = nunca marca incobrable (comportamiento previo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planes_credito', function (Blueprint $table) {
            $table->unsignedInteger('cuotas_incobrable')->default(0)->after('plazo_default');
        });
    }

    public function down(): void
    {
        Schema::table('planes_credito', function (Blueprint $table) {
            $table->dropColumn('cuotas_incobrable');
        });
    }
};
