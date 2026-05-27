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
        Schema::table('movimientos_bancarios', function (Blueprint $table) {
            // El índice único compuesto ya no tiene sentido con chipax_id como PK única.
            // Múltiples transferencias del mismo monto en el mismo día son legítimas.
            $table->dropUnique('mov_unique');
            // Mantenemos un índice normal para búsquedas por cuenta+fecha
            $table->index(['cuenta', 'fecha_contable', 'monto'], 'mov_busqueda');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_bancarios', function (Blueprint $table) {
            $table->dropIndex('mov_busqueda');
            $table->unique(['cuenta', 'fecha_contable', 'numero_documento', 'monto'], 'mov_unique');
        });
    }
};
