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
        // Verificar si la tabla existe y tiene columnas
        if (Schema::hasTable('cotizacion_detalles')) {
            // Si no tiene las columnas básicas, agregarlas
            Schema::table('cotizacion_detalles', function (Blueprint $table) {
                if (!Schema::hasColumn('cotizacion_detalles', 'cotizacion_id')) {
                    $table->unsignedBigInteger('cotizacion_id')->after('id');
                    $table->foreign('cotizacion_id')->references('id')->on('cotizaciones')->onDelete('cascade');
                }
                
                if (!Schema::hasColumn('cotizacion_detalles', 'descripcion')) {
                    $table->text('descripcion')->nullable();
                }
                
                if (!Schema::hasColumn('cotizacion_detalles', 'cantidad')) {
                    $table->decimal('cantidad', 10, 2)->default(1);
                }
                
                if (!Schema::hasColumn('cotizacion_detalles', 'precio_unitario')) {
                    $table->decimal('precio_unitario', 12, 2)->default(0);
                }
                
                if (!Schema::hasColumn('cotizacion_detalles', 'total')) {
                    $table->decimal('total', 12, 2)->default(0);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No hacer nada en el down para no perder datos
    }
};
