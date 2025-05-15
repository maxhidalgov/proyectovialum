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
    Schema::table('proveedors', function (Blueprint $table) {
        if (!Schema::hasColumn('proveedors', 'nombre')) {
            $table->string('nombre');
        }
        if (!Schema::hasColumn('proveedors', 'contacto')) {
            $table->string('contacto')->nullable();
        }
    });
}

public function down()
{
    Schema::table('proveedors', function (Blueprint $table) {
        $table->dropColumn(['nombre', 'contacto']);
    });
}
};
