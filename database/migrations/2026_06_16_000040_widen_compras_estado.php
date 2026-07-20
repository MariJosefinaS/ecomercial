<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * compras.estado era ENUM y no admitía 'parcial' (recepción por remitos, donde
 * una factura puede quedar parcialmente recibida). Se pasa a VARCHAR.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE compras MODIFY estado VARCHAR(20) NOT NULL DEFAULT 'pendiente'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE compras MODIFY estado ENUM('pendiente','aprobada','recibida','rechazada') NOT NULL DEFAULT 'pendiente'");
    }
};
