<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Galería de imágenes por producto (varias fotos). `productos.imagen` se conserva como
 * PORTADA (compatibilidad con todo lo que ya la usa); `imagenes` guarda la lista completa
 * en orden (la portada es la primera).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->json('imagenes')->nullable()->after('imagen');
        });

        // Backfill: los productos que ya tenían una imagen arrancan con ella en la galería.
        foreach (DB::table('productos')->whereNotNull('imagen')->select('id', 'imagen')->get() as $p) {
            DB::table('productos')->where('id', $p->id)->update(['imagenes' => json_encode([$p->imagen])]);
        }
    }

    public function down(): void
    {
        Schema::table('productos', fn (Blueprint $t) => $t->dropColumn('imagenes'));
    }
};
