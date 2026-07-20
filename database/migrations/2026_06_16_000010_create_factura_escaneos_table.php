<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Borrador del escaneo de una factura de compra (Fase B — Recepción).
 * Guarda lo que el modelo de visión extrajo + el resultado del matching
 * interno contra el catálogo, para pre-rellenar la pantalla de Recepción.
 * 1:N con compras (se puede re-escanear; queda historial/auditoría).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('factura_escaneos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->string('archivo')->nullable();            // path en storage/public
            $table->string('modelo')->nullable();             // modelo de visión usado
            $table->string('estado')->default('procesando');  // procesando|listo|error|aplicado
            $table->json('cabecera')->nullable();             // comprobante, fecha, remito, cae, totales
            $table->json('lineas')->nullable();               // líneas extraídas + match (producto_id, confianza, candidatos)
            $table->text('error')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factura_escaneos');
    }
};
