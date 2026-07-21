<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cobros', function (Blueprint $table) {
            $table->timestamp('recibo_enviado_at')->nullable()->after('fecha');
            $table->string('recibo_email')->nullable()->after('recibo_enviado_at');
        });
    }

    public function down(): void
    {
        Schema::table('cobros', function (Blueprint $table) {
            $table->dropColumn(['recibo_enviado_at', 'recibo_email']);
        });
    }
};
