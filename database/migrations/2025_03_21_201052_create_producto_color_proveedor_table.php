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
            $table->unsignedBigInteger('producto_id')->nullable();
            $table->unsignedBigInteger('proveedor_id')->nullable();
            $table->unsignedBigInteger('color_id')->nullable(); // AGREGADO
            $table->integer('costo')->nullable();
            $table->integer('stock')->nullable();
            $table->timestamps();

            // Claves foráneas
            $table->foreign('producto_id')->references('id')->on('productos')->onDelete('cascade');
            $table->foreign('proveedor_id')->references('id')->on('proveedors')->onDelete('cascade');
            $table->foreign('color_id')->references('id')->on('colores')->onDelete('cascade'); // AGREGADO
        });
    }

    public function down()
    {
        Schema::dropIfExists('producto_color_proveedor');
    }
}
