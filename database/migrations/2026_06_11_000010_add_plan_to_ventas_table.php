<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nota de Pedido (carga del vendedor) — plan comercial y financiación.
 * Fuente: videollamada con el cliente 2026-06-10 (planes de crédito).
 * El estado 'pendiente' se muestra como "Solicitado" al vendedor; el flujo
 * de aprobación existente (pendiente→aprobada/rechazada) no se toca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('plan_codigo', 30)->nullable()->after('medio_pago');
            $table->string('plan_nombre')->nullable()->after('plan_codigo');
            $table->string('modalidad', 20)->nullable()->after('plan_nombre'); // contado/diario/semanal
            $table->decimal('anticipo', 14, 2)->default(0)->after('modalidad');
            $table->decimal('saldo_financiado', 14, 2)->default(0)->after('anticipo');
            $table->unsignedInteger('plazo')->nullable()->after('saldo_financiado'); // días o semanas
            $table->decimal('cuota', 14, 2)->default(0)->after('plazo');
            $table->string('zona_cobranza')->nullable()->after('cuota');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['plan_codigo', 'plan_nombre', 'modalidad', 'anticipo', 'saldo_financiado', 'plazo', 'cuota', 'zona_cobranza']);
        });
    }
};
