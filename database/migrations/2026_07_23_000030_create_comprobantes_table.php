<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo fiscal (como GENESIS): comprobantes formales + datos de IVA del cliente.
 *  - Factura A/B/C según la condición de IVA del cliente (A discrimina IVA, B/C lo llevan incluido).
 *  - Nota de crédito (devoluciones), Recibo (cobro al cliente), Orden de Pago (pago a proveedor).
 * La cuenta corriente pasa a tener COMPROBANTE y FECHA DE VENCIMIENTO por movimiento,
 * para poder mostrar Saldo Vencido / A Vencer.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ===== Datos fiscales del cliente =====
        Schema::table('clientes', function (Blueprint $table) {
            $table->enum('tipo_iva', ['responsable_inscripto', 'monotributo', 'consumidor_final', 'exento'])
                ->default('consumidor_final')->after('documento');
            $table->string('ingresos_brutos', 40)->nullable()->after('tipo_iva');
        });

        // Los clientes con CUIT suelen ser responsables inscriptos; con DNI/CUIL, consumidor final.
        DB::table('clientes')->where('tipo_doc', 'CUIT')->update(['tipo_iva' => 'responsable_inscripto']);

        // ===== Comprobantes =====
        Schema::create('comprobantes', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['factura', 'nota_credito', 'nota_debito', 'recibo', 'orden_pago']);
            $table->char('letra', 1)->nullable();            // A · B · C · X (interno, sin valor fiscal)
            $table->unsignedSmallInteger('punto_venta')->default(1);
            $table->unsignedInteger('numero');                // correlativo por tipo+letra+punto de venta
            $table->string('numero_completo', 20)->index();   // 0001-00000123

            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->foreignId('cobro_id')->nullable()->constrained('cobros')->nullOnDelete();
            $table->foreignId('devolucion_id')->nullable()->constrained('devoluciones')->nullOnDelete();
            $table->foreignId('pedido_pago_id')->nullable()->constrained('pedidos_pago')->nullOnDelete();

            $table->date('fecha');
            $table->date('fecha_vencimiento')->nullable();
            $table->string('concepto');
            $table->decimal('neto', 14, 2)->default(0);
            $table->decimal('iva_pct', 5, 2)->default(0);
            $table->decimal('iva', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->enum('estado', ['emitido', 'anulado'])->default('emitido');
            $table->string('motivo_anulacion')->nullable();
            $table->foreignId('emitido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tipo', 'letra', 'punto_venta', 'numero'], 'comprobantes_correlativo_unico');
            $table->index(['cliente_id', 'fecha']);
        });

        // ===== La cuenta corriente ahora referencia el comprobante y su vencimiento =====
        Schema::table('movimientos_cliente', function (Blueprint $table) {
            $table->foreignId('comprobante_id')->nullable()->after('referencia')
                ->constrained('comprobantes')->nullOnDelete();
            $table->date('fecha_vencimiento')->nullable()->after('fecha');
        });

        // Backfill: los movimientos viejos vencen el día que se cargaron (no había plazo).
        DB::statement('UPDATE movimientos_cliente SET fecha_vencimiento = fecha WHERE fecha_vencimiento IS NULL');
    }

    public function down(): void
    {
        Schema::table('movimientos_cliente', function (Blueprint $table) {
            $table->dropForeign(['comprobante_id']);
            $table->dropColumn(['comprobante_id', 'fecha_vencimiento']);
        });
        Schema::dropIfExists('comprobantes');
        Schema::table('clientes', fn (Blueprint $t) => $t->dropColumn(['tipo_iva', 'ingresos_brutos']));
    }
};
