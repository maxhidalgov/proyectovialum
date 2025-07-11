<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('producto_color_proveedor', function (Blueprint $table) {
        $table->string('codigo_proveedor')->nullable()->after('color_id');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('producto_color_proveedor', function (Blueprint $table) {
            //
        });
    }
};
