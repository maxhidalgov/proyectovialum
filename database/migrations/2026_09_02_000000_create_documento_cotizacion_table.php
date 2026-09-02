<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('documento_cotizacion')) {
            Schema::create('documento_cotizacion', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('documento_facturacion_id');
                $table->unsignedBigInteger('cotizacion_id');
                // Porción del documento (BRUTO) asignada a esta cotización
                $table->decimal('monto', 15, 2)->default(0);
                $table->timestamps();

                $table->unique(['documento_facturacion_id', 'cotizacion_id'], 'doc_cot_unique');
                $table->index('cotizacion_id');
                $table->foreign('documento_facturacion_id')->references('id')->on('documentos_facturacion')->cascadeOnDelete();
                $table->foreign('cotizacion_id')->references('id')->on('cotizaciones')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_cotizacion');
    }
};
