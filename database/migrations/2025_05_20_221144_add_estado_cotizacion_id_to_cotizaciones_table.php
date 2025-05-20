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
    Schema::table('cotizaciones', function (Blueprint $table) {
        $table->unsignedBigInteger('estado_cotizacion_id')->nullable()->after('fecha');
        $table->foreign('estado_cotizacion_id')->references('id')->on('estados_cotizacion');
        $table->dropColumn('estado'); // si quieres eliminar el campo anterior
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            //
        });
    }
};
