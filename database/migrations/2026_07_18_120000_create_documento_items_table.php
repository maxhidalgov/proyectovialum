<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('documento_items')) {
            return;
        }

        Schema::create('documento_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_facturacion_id')->constrained('documentos_facturacion')->cascadeOnDelete();
            $table->unsignedBigInteger('producto_id')->nullable(); // producto de la app (null = ítem manual)
            $table->string('nombre');
            $table->decimal('cantidad', 12, 4)->default(0);        // unidades o m² (vidrio)
            $table->integer('precio_unitario')->default(0);         // neto unitario
            $table->decimal('descuento', 5, 2)->default(0);
            $table->integer('total_neto')->default(0);
            // Detalle de vidrio (para orden de corte)
            $table->boolean('es_vidrio')->default(false);
            $table->unsignedInteger('ancho')->nullable();           // mm
            $table->unsignedInteger('alto')->nullable();            // mm
            $table->unsignedInteger('piezas')->nullable();
            $table->boolean('pulido')->default(false);
            $table->timestamps();

            $table->index('producto_id');
            $table->index('es_vidrio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_items');
    }
};
