<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega el estado 'anulado' a documentos_facturacion. Se usa cuando una factura
 * emitida se anuló en Bsale (nota de crédito) y se re-emitió por fuera: el
 * documento local queda 'anulado' (no cuenta como facturado) y se vincula el
 * documento Bsale correcto. Idempotente (MODIFY se puede repetir).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE documentos_facturacion MODIFY estado ENUM('pendiente','emitido','anulado') NOT NULL DEFAULT 'pendiente'");
    }

    public function down(): void
    {
        // Revertir 'anulado' a 'pendiente' antes de reducir el enum
        DB::table('documentos_facturacion')->where('estado', 'anulado')->update(['estado' => 'pendiente']);
        DB::statement("ALTER TABLE documentos_facturacion MODIFY estado ENUM('pendiente','emitido') NOT NULL DEFAULT 'pendiente'");
    }
};
