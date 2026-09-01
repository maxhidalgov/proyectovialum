<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('cotizaciones', 'oculto_operaciones')) {
            Schema::table('cotizaciones', function (Blueprint $table) {
                $table->boolean('oculto_operaciones')->default(false)->after('estado_obra');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cotizaciones', 'oculto_operaciones')) {
            Schema::table('cotizaciones', function (Blueprint $table) {
                $table->dropColumn('oculto_operaciones');
            });
        }
    }
};
