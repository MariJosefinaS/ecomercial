<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nuevo modelo de costeo (decisión del usuario 2026-06-18):
 *  - El COSTO puesto en depósito sale de la factura: neto de la línea
 *    × (1 + Σ conceptos de COSTO del proveedor) × (1 + IVA% si el proveedor "costea con IVA").
 *  - TODOS los conceptos (Flete, Gestión, …) pasan a ser conceptos de COSTO.
 *  - La GANANCIA ("Remarcar") deja de ser un concepto y se vuelve un % aparte
 *    por proveedor (`remarque_pct`): precio_venta = costo × (1 + remarque%).
 *  - El IVA es configurable por proveedor (RI con crédito fiscal = sin IVA en el costo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            // ¿El costo del producto incluye el IVA? (RI: normalmente NO, es crédito fiscal).
            $table->boolean('costea_con_iva')->default(false)->after('dias_entrega');
            // Alícuota de IVA que aplica este proveedor cuando costea con IVA.
            $table->decimal('iva_pct', 5, 2)->default(21)->after('costea_con_iva');
            // Ganancia/remarque sobre el costo para fijar el precio de venta.
            $table->decimal('remarque_pct', 6, 2)->default(40)->after('iva_pct');
        });

        Schema::table('productos', function (Blueprint $table) {
            // Neto de la factura (base del costeo; precio_compra pasa a ser el COSTO puesto en depósito).
            $table->decimal('precio_neto', 12, 2)->nullable()->after('precio_compra');
            // Remarque por producto (override del default del proveedor; null = usa el del proveedor).
            $table->decimal('remarque_pct', 6, 2)->nullable()->after('conceptos');
        });

        // ── Migración de datos: "Remarcar" deja de ser concepto y pasa a remarque_pct ──
        $remarcar = DB::table('conceptos_precio')->where('nombre', 'Remarcar')->first();
        if ($remarcar) {
            // 1) Cada proveedor hereda su % de Remarcar del pivot (si lo tenía).
            $pivots = DB::table('concepto_proveedor')->where('concepto_precio_id', $remarcar->id)->get();
            foreach ($pivots as $piv) {
                DB::table('proveedores')->where('id', $piv->proveedor_id)
                    ->update(['remarque_pct' => $piv->porcentaje]);
            }
            // 2) Quitar Remarcar de los pivots (ya no es concepto de costo) y desactivarlo.
            DB::table('concepto_proveedor')->where('concepto_precio_id', $remarcar->id)->delete();
            DB::table('conceptos_precio')->where('id', $remarcar->id)->update(['activo' => false, 'updated_at' => now()]);

            // 3) Productos con snapshot de conceptos: extraer Remarcar → remarque_pct y sacarlo del snapshot.
            foreach (DB::table('productos')->whereNotNull('conceptos')->get(['id', 'conceptos']) as $p) {
                $conceptos = json_decode($p->conceptos, true);
                if (! is_array($conceptos)) {
                    continue;
                }
                $rem = null;
                $resto = [];
                foreach ($conceptos as $c) {
                    if (($c['nombre'] ?? '') === 'Remarcar' || (int) ($c['id'] ?? 0) === (int) $remarcar->id) {
                        $rem = (float) ($c['porcentaje'] ?? 0);
                    } else {
                        $resto[] = $c;
                    }
                }
                $upd = ['conceptos' => json_encode(array_values($resto))];
                if ($rem !== null) {
                    $upd['remarque_pct'] = $rem;
                }
                DB::table('productos')->where('id', $p->id)->update($upd);
            }
        }
    }

    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropColumn(['costea_con_iva', 'iva_pct', 'remarque_pct']);
        });
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['precio_neto', 'remarque_pct']);
        });
        // Reactivar "Remarcar" como concepto (reversa best-effort).
        DB::table('conceptos_precio')->where('nombre', 'Remarcar')->update(['activo' => true, 'updated_at' => now()]);
    }
};
