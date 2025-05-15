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
        Schema::create('ventanas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('cotizaciones')->onDelete('cascade');
            $table->foreignId('tipo_ventana_id')->constrained('tipos_ventana');
            $table->integer('ancho');
            $table->integer('alto');
            $table->foreignId('color_id')->constrained('colores');
            $table->string('producto_vidrio_proveedor_id'); // Este campo tipo string compuesto como "33-3"
            $table->decimal('costo', 12, 2);
            $table->decimal('precio', 12, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventanas');
    }
};
