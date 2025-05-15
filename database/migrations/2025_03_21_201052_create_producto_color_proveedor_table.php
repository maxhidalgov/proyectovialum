<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductoColorProveedorTable extends Migration
{
    public function up()
    {
        Schema::create('producto_color_proveedor', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('producto_id');
            $table->unsignedBigInteger('proveedor_id');
            $table->string('color');
            $table->decimal('costo', 10, 2);
            $table->integer('stock')->default(0)->nullable();
            $table->timestamps();

            $table->foreign('producto_id')->references('id')->on('productos')->onDelete('cascade');
            $table->foreign('proveedor_id')->references('id')->on('proveedors')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('producto_color_proveedor');
    }
}
