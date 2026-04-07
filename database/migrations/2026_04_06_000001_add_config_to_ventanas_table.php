<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventanas', function (Blueprint $table) {
            // Almacena parámetros extra que no tienen columna propia:
            // tipo_vidrio, manillon, proveedor_vidrio, etc.
            $table->json('config')->nullable()->after('hojas_moviles');
        });
    }

    public function down(): void
    {
        Schema::table('ventanas', function (Blueprint $table) {
            $table->dropColumn('config');
        });
    }
};
