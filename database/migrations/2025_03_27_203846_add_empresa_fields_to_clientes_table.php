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
    Schema::table('clientes', function (Blueprint $table) {
        $table->string('tipo_cliente')->nullable(); // 'persona' o 'empresa'
        $table->string('razon_social')->nullable(); // company
        $table->string('giro')->nullable();          // activity
        $table->string('ciudad')->nullable();        // city
        $table->string('comuna')->nullable();        // municipality
     //  $table->string('direccion')->nullable();     // address
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            //
        });
    }
};
