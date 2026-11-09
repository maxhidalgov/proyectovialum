<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// El tipo de ventana 55 se creó como "Ventana CorrderaAL25" (falta una "e" y un espacio) y el nombre sale
// tal cual en el PDF de cotización. El código usa el id (55), no el nombre, así que solo se corrige el texto.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tipos_ventana')) return;

        DB::table('tipos_ventana')
            ->where('id', 55)
            ->where('nombre', 'Ventana CorrderaAL25')
            ->update(['nombre' => 'Ventana Corredera AL25']);
    }

    public function down(): void
    {
        // sin reversa: es solo la corrección de un error de tipeo
    }
};
