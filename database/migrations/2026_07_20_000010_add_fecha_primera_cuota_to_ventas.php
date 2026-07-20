<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R4 — Fecha de inicio de la 1ª cuota configurable (pedido del cliente 2026-07-14).
 *
 * Antes el cronograma arrancaba SIEMPRE al día siguiente de aprobar la venta
 * (`Carbon::today()`), sin poder ajustarlo; Gustavo nunca lo ajustó = pain point.
 * Ahora el vendedor elige en la Nota de Pedido cuándo vence la 1ª cuota
 * (ej. "empezar la semana que viene"). Null = comportamiento previo (1 período
 * después de aprobar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->date('fecha_primera_cuota')->nullable()->after('cuota');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn('fecha_primera_cuota');
        });
    }
};
