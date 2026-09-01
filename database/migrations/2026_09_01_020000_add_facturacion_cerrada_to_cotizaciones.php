<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('cotizaciones', 'facturacion_cerrada')) {
            Schema::table('cotizaciones', function (Blueprint $table) {
                $table->boolean('facturacion_cerrada')->default(false)->after('estado_facturacion');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cotizaciones', 'facturacion_cerrada')) {
            Schema::table('cotizaciones', function (Blueprint $table) {
                $table->dropColumn('facturacion_cerrada');
            });
        }
    }
};
