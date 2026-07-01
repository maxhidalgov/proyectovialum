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
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->date('fecha_entrega_real')->nullable()->after('fecha_entrega');
            $table->enum('tipo_vidrio', ['simple', 'termopanel'])->nullable()->after('fecha_entrega_real');
            $table->boolean('fabricar_termopanel')->default(false)->after('tipo_vidrio');
            $table->boolean('cortar_vidrio_cnc')->default(false)->after('fabricar_termopanel');
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropColumn(['fecha_entrega_real', 'tipo_vidrio', 'fabricar_termopanel', 'cortar_vidrio_cnc']);
        });
    }
};
