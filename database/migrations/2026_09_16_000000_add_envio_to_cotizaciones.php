<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('cotizaciones', 'enviado_at')) {
                $table->timestamp('enviado_at')->nullable()->after('estado_facturacion');
            }
            if (!Schema::hasColumn('cotizaciones', 'enviado_via')) {
                $table->string('enviado_via', 20)->nullable()->after('enviado_at');
            }
            if (!Schema::hasColumn('cotizaciones', 'enviado_a')) {
                $table->string('enviado_a', 120)->nullable()->after('enviado_via');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropColumn(['enviado_at', 'enviado_via', 'enviado_a']);
        });
    }
};
