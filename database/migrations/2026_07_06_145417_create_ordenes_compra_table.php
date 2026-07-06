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
        if (Schema::hasTable('ordenes_compra')) {
            return;
        }
        Schema::create('ordenes_compra', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->nullable();
            $table->foreignId('cotizacion_id')->nullable()->constrained('cotizaciones')->nullOnDelete();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedors')->nullOnDelete();
            $table->string('proveedor_nombre')->nullable();
            $table->text('observaciones')->nullable();
            $table->json('items');                       // [{categoria, referencia, descripcion, detalle, cantidad}]
            $table->string('estado')->default('generada'); // generada | enviada | recibida
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ordenes_compra');
    }
};
