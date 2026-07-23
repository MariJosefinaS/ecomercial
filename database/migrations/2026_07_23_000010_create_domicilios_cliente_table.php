<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Domicilios MÚLTIPLES por cliente (pedido del video: "casa, negocio, familiar, hija").
 * Un cliente tiene N domicilios; uno es el PRINCIPAL (se sincroniza con clientes.direccion,
 * que se conserva como domicilio fiscal / de respaldo para no romper lo ya construido).
 * `uso` distingue a dónde se ENTREGA la mercadería y dónde se COBRA la cuota.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domicilios_cliente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('etiqueta', 60);                 // Casa · Negocio · Casa de la hija…
            $table->string('direccion');
            $table->string('localidad')->nullable();
            $table->string('provincia')->nullable();
            $table->string('referencia')->nullable();       // entre calles / cómo llegar
            $table->string('contacto')->nullable();         // quién recibe
            $table->string('telefono', 40)->nullable();
            $table->foreignId('zona_id')->nullable()->constrained('zonas')->nullOnDelete();
            $table->decimal('latitud', 10, 7)->nullable();  // geo: que el cobrador lo encuentre
            $table->decimal('longitud', 10, 7)->nullable();
            $table->enum('uso', ['ambos', 'entrega', 'cobro'])->default('ambos');
            $table->boolean('es_principal')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['cliente_id', 'es_principal']);
        });

        // Domicilio de ENTREGA elegido en la venta (si no se elige, va el principal del cliente).
        Schema::table('ventas', function (Blueprint $table) {
            $table->foreignId('domicilio_entrega_id')->nullable()->after('cliente_id')
                ->constrained('domicilios_cliente')->nullOnDelete();
        });

        // Backfill: la dirección actual de cada cliente pasa a ser su domicilio principal.
        $ahora = now();
        foreach (DB::table('clientes')->select('id', 'direccion', 'telefono', 'zona_id')->orderBy('id')->get() as $c) {
            if (trim((string) $c->direccion) === '') {
                continue;
            }
            DB::table('domicilios_cliente')->insert([
                'cliente_id' => $c->id,
                'etiqueta' => 'Principal',
                'direccion' => $c->direccion,
                'telefono' => $c->telefono,
                'zona_id' => $c->zona_id,
                'uso' => 'ambos',
                'es_principal' => true,
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropForeign(['domicilio_entrega_id']);
            $table->dropColumn('domicilio_entrega_id');
        });
        Schema::dropIfExists('domicilios_cliente');
    }
};
