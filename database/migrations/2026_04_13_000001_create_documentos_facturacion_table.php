<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_facturacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('cotizaciones')->onDelete('cascade');
            $table->enum('tipo', ['anticipo', 'saldo', 'total']);
            $table->decimal('porcentaje', 5, 2); // ej: 50.00
            $table->decimal('monto', 12, 0);
            $table->enum('estado', ['pendiente', 'emitido'])->default('pendiente');
            $table->string('id_documento_bsale')->nullable();
            $table->string('numero_documento_bsale')->nullable();
            $table->string('url_pdf_bsale')->nullable();
            $table->date('fecha_emision')->nullable();
            $table->string('nota')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_facturacion');
    }
};
