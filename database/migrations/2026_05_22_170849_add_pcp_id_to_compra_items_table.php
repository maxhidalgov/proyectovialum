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
        Schema::table('compra_items', function (Blueprint $table) {
            $table->unsignedBigInteger('pcp_id')->nullable()->after('compra_id');
            $table->foreign('pcp_id')->references('id')->on('producto_color_proveedor')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('compra_items', function (Blueprint $table) {
            $table->dropForeign(['pcp_id']);
            $table->dropColumn('pcp_id');
        });
    }
};
