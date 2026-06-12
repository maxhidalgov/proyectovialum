<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boleta_resumenes', function (Blueprint $table) {
            $table->boolean('conciliado_transbank')->default(false)->after('conciliado');
        });
    }

    public function down(): void
    {
        Schema::table('boleta_resumenes', function (Blueprint $table) {
            $table->dropColumn('conciliado_transbank');
        });
    }
};
