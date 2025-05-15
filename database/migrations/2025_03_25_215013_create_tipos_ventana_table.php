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
        Schema::create('tipos_ventana', function (Blueprint $table) {
            $table->id();
            $table->string('nombre'); // Ej: Fija AL42, Proyectante S60, etc.
            $table->unsignedBigInteger('material_id'); // Relación con tipos_material
            $table->timestamps();
    
            // 🔗 Clave foránea
            $table->foreign('material_id')
                  ->references('id')
                  ->on('tipos_material')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipos_ventana');
    }
};
