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
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('unidad_medida');
        });
    }
    
    public function down()
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('unidad_medida')->nullable();
        });
    }
};
