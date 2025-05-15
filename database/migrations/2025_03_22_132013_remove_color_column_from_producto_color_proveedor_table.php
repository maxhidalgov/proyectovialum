<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveColorColumnFromProductoColorProveedorTable extends Migration
{
    public function up()
    {
        Schema::table('producto_color_proveedor', function (Blueprint $table) {
            $table->dropColumn('color'); // Eliminar la columna obsoleta
        });
    }

    public function down()
    {
        Schema::table('producto_color_proveedor', function (Blueprint $table) {
            $table->string('color'); // Solo si deseas revertir
        });
    }

};
