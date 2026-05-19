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
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bsale_id')->unique();
            $table->integer('folio');
            $table->integer('tipo_dte')->default(46);
            $table->string('rut_emisor', 20)->nullable();
            $table->string('nombre_emisor')->nullable();
            $table->date('fecha_emision')->nullable();
            $table->date('fecha_recepcion')->nullable();
            $table->bigInteger('neto')->default(0);
            $table->bigInteger('iva')->default(0);
            $table->bigInteger('total')->default(0);
            $table->string('estado')->nullable();
            $table->string('xml_url')->nullable();
            $table->string('pdf_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
