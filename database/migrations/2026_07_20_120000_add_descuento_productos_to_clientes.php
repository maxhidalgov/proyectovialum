<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('clientes', 'descuento_productos')) {
            Schema::table('clientes', function (Blueprint $table) {
                // % de descuento sobre productos de lista de precios (no aplica a ventanas)
                $table->decimal('descuento_productos', 5, 2)->default(0)->after('giro');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('clientes', 'descuento_productos')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropColumn('descuento_productos');
            });
        }
    }
};
