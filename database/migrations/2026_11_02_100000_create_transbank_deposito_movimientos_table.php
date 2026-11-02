<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Un depósito Transbank (período + fecha de abono) puede llegar al banco en VARIOS movimientos
// (ej. 19/08: 92.579 + 11.896 = 104.475). transbank_abonos.movimiento_bancario_id solo guarda uno,
// así que esta tabla guarda todos; esa columna sigue apuntando al primero por compatibilidad.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('transbank_deposito_movimientos')) {
            Schema::create('transbank_deposito_movimientos', function (Blueprint $table) {
                $table->id();
                $table->char('periodo', 7);
                $table->date('fecha_abono');
                $table->unsignedBigInteger('movimiento_bancario_id');
                $table->timestamps();

                $table->unique(['periodo', 'fecha_abono', 'movimiento_bancario_id'], 'tdm_deposito_mov_unique');
                $table->index('movimiento_bancario_id');
                $table->foreign('movimiento_bancario_id')->references('id')->on('movimientos_bancarios')->onDelete('cascade');
            });
        }

        // Relleno: los vínculos que ya existían en transbank_abonos pasan a la tabla nueva (idempotente)
        DB::statement("
            INSERT IGNORE INTO transbank_deposito_movimientos (periodo, fecha_abono, movimiento_bancario_id, created_at, updated_at)
            SELECT DISTINCT tf.periodo, ta.fecha_abono, ta.movimiento_bancario_id, NOW(), NOW()
            FROM transbank_abonos ta
            JOIN transbank_archivos tf ON tf.id = ta.archivo_id
            WHERE ta.movimiento_bancario_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('transbank_deposito_movimientos');
    }
};
