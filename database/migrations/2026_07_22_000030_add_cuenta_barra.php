<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modelo de cuenta del cliente (como GENESIS/CRED):
 *  - clientes.numero_cuenta = número de cuenta ÚNICO y fijo del cliente (ej. 38301).
 *  - ventas.credito_barra   = correlativo del crédito DENTRO de esa cuenta (ej. /15).
 * Display del crédito = "{numero_cuenta}/{credito_barra}" (ej. 38301/15).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->unsignedInteger('numero_cuenta')->nullable()->unique()->after('id');
        });
        Schema::table('ventas', function (Blueprint $table) {
            $table->unsignedInteger('credito_barra')->nullable()->after('numero');
        });

        // Backfill numero_cuenta (base 38000 + correlativo por orden de alta).
        $i = 0;
        foreach (DB::table('clientes')->orderBy('id')->pluck('id') as $id) {
            DB::table('clientes')->where('id', $id)->update(['numero_cuenta' => 38000 + (++$i)]);
        }

        // Backfill credito_barra: correlativo por cliente de sus ventas a crédito (por orden de id).
        $porCliente = [];
        foreach (DB::table('ventas')->where('credito', true)->whereNotNull('cliente_id')->orderBy('id')->get(['id', 'cliente_id']) as $v) {
            $porCliente[$v->cliente_id] = ($porCliente[$v->cliente_id] ?? 0) + 1;
            DB::table('ventas')->where('id', $v->id)->update(['credito_barra' => $porCliente[$v->cliente_id]]);
        }
    }

    public function down(): void
    {
        Schema::table('clientes', fn (Blueprint $t) => $t->dropColumn('numero_cuenta'));
        Schema::table('ventas', fn (Blueprint $t) => $t->dropColumn('credito_barra'));
    }
};
