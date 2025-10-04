<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (!Schema::hasColumn('clientes', 'bsale_id')) {
                $table->integer('bsale_id')->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('clientes', 'tipo_cliente')) {
                $table->string('tipo_cliente')->nullable()->after('bsale_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (Schema::hasColumn('clientes', 'bsale_id')) {
                $table->dropColumn('bsale_id');
            }
            if (Schema::hasColumn('clientes', 'tipo_cliente')) {
                $table->dropColumn('tipo_cliente');
            }
        });
    }
};
