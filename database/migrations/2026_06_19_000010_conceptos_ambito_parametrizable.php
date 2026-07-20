<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conceptos 100% parametrizables (decisión del usuario 2026-06-19):
 *  - Cada concepto declara su ÁMBITO: 'costo' (pega sobre el costo) o 'venta' (sobre el precio).
 *  - "Remarcar" vuelve a ser un CONCEPTO (ámbito = venta) — se elimina el campo especial
 *    proveedores.remarque_pct y su valor por proveedor migra al pivot concepto_proveedor.
 *  - Los conceptos del mismo ámbito se aplican EN CASCADA (compuesto), según `orden`.
 *  - El % por producto sigue siendo override (snapshot en productos.conceptos); se puede
 *    agregar/quitar cualquier concepto por producto.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Ámbito en el catálogo de conceptos (por defecto 'costo').
        Schema::table('conceptos_precio', function (Blueprint $table) {
            $table->string('ambito', 10)->default('costo')->after('nombre');
        });

        // 2) Reactivar "Remarcar" como concepto de VENTA; el resto queda como 'costo'.
        DB::table('conceptos_precio')->update(['ambito' => 'costo']);
        DB::table('conceptos_precio')->where('nombre', 'Remarcar')
            ->update(['ambito' => 'venta', 'activo' => true, 'updated_at' => now()]);

        // 3) Migrar proveedores.remarque_pct → pivot del concepto "Remarcar".
        $remarcar = DB::table('conceptos_precio')->where('nombre', 'Remarcar')->first();
        if ($remarcar) {
            foreach (DB::table('proveedores')->get(['id', 'remarque_pct']) as $prov) {
                DB::table('concepto_proveedor')->updateOrInsert(
                    ['proveedor_id' => $prov->id, 'concepto_precio_id' => $remarcar->id],
                    ['porcentaje' => $prov->remarque_pct ?? $remarcar->porcentaje, 'updated_at' => now(), 'created_at' => now()],
                );
            }

            // 4) Productos con remarque por producto → volver a meterlo en el snapshot como concepto de venta.
            foreach (DB::table('productos')->whereNotNull('remarque_pct')->get(['id', 'conceptos', 'remarque_pct']) as $p) {
                $conceptos = json_decode($p->conceptos ?? '[]', true);
                $conceptos = is_array($conceptos) ? $conceptos : [];
                $conceptos[] = [
                    'id' => $remarcar->id,
                    'nombre' => 'Remarcar',
                    'ambito' => 'venta',
                    'aplica' => true,
                    'porcentaje' => (float) $p->remarque_pct,
                    'orden' => $remarcar->orden,
                ];
                DB::table('productos')->where('id', $p->id)->update(['conceptos' => json_encode($conceptos)]);
            }
        }

        // 5) Eliminar los campos especiales que ahora son conceptos.
        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropColumn('remarque_pct');
        });
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('remarque_pct');
        });
    }

    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->decimal('remarque_pct', 6, 2)->default(40)->after('iva_pct');
        });
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('remarque_pct', 6, 2)->nullable()->after('conceptos');
        });

        // Devolver el % de "Remarcar" del pivot al campo del proveedor y desactivar el concepto.
        $remarcar = DB::table('conceptos_precio')->where('nombre', 'Remarcar')->first();
        if ($remarcar) {
            foreach (DB::table('concepto_proveedor')->where('concepto_precio_id', $remarcar->id)->get() as $piv) {
                DB::table('proveedores')->where('id', $piv->proveedor_id)->update(['remarque_pct' => $piv->porcentaje]);
            }
            DB::table('concepto_proveedor')->where('concepto_precio_id', $remarcar->id)->delete();
            DB::table('conceptos_precio')->where('id', $remarcar->id)->update(['activo' => false]);
        }

        Schema::table('conceptos_precio', function (Blueprint $table) {
            $table->dropColumn('ambito');
        });
    }
};
