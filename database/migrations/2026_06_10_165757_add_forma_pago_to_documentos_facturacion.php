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
        Schema::table('documentos_facturacion', function (Blueprint $table) {
            $table->unsignedTinyInteger('payment_type_id')->nullable()->after('pagado_con_tarjeta');
            $table->string('forma_pago', 30)->nullable()->after('payment_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('documentos_facturacion', function (Blueprint $table) {
            $table->dropColumn(['payment_type_id', 'forma_pago']);
        });
    }
};
