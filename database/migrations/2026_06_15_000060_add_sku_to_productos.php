<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** SKU del producto (código del proveedor/fabricante), cargable en la recepción. */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('sku', 60)->nullable()->after('codigo')->index();
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('sku');
        });
    }
};
