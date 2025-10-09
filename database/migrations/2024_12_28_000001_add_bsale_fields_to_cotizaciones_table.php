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
            $table->string('numero_documento_bsale')->nullable()->after('total');
            $table->integer('id_documento_bsale')->nullable()->after('numero_documento_bsale');
            $table->datetime('fecha_documento_bsale')->nullable()->after('id_documento_bsale');
            $table->enum('estado_facturacion', ['aprobada', 'facturada', 'pagada', 'anulada'])
                  ->default('aprobada')
                  ->after('fecha_documento_bsale');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropColumn([
                'numero_documento_bsale', 
                'id_documento_bsale', 
                'fecha_documento_bsale',
                'estado_facturacion'
            ]);
        });
    }
};
