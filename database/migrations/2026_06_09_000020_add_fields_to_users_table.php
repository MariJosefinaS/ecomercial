<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('rol', ['super_admin', 'admin_local', 'vendedor', 'empleado'])
                  ->default('vendedor')->after('email');
            $table->foreignId('local_id')->nullable()->after('rol')
                  ->constrained('locales')->nullOnDelete();
            $table->boolean('activo')->default(true)->after('local_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('local_id');
            $table->dropColumn(['rol', 'activo']);
        });
    }
};
