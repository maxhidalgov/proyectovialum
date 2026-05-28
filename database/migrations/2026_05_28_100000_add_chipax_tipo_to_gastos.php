<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            // Tipo de origen en Chipax: gasto | honorario | previred | impuesto
            $table->string('chipax_tipo', 30)->nullable()->after('chipax_id');
        });

        // Drop el unique individual en chipax_id y reemplazar por composite
        Schema::table('gastos', function (Blueprint $table) {
            $table->dropUnique(['chipax_id']);
            // Unique compuesto: un mismo chipax_id puede existir como gasto y como honorario
            $table->unique(['chipax_id', 'chipax_tipo'], 'gastos_chipax_id_tipo_unique');
        });

        // Marcar los gastos ya existentes de /gastos como tipo 'gasto'
        DB::table('gastos')
            ->whereNotNull('chipax_id')
            ->whereNull('chipax_tipo')
            ->update(['chipax_tipo' => 'gasto']);
    }

    public function down(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            $table->dropUnique('gastos_chipax_id_tipo_unique');
            $table->dropColumn('chipax_tipo');
            $table->unique('chipax_id');
        });
    }
};
