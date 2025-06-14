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
    Schema::table('ventanas', function (Blueprint $table) {
        $table->integer('hojas_totales')->nullable()->after('precio');
        $table->integer('hojas_moviles')->nullable()->after('hojas_totales');
    });
}

    /**
     * Reverse the migrations.
     */
public function down()
{
    Schema::table('ventanas', function (Blueprint $table) {
        $table->dropColumn(['hojas_totales', 'hojas_moviles']);
    });
}
};
