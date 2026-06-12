<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->string('categoria', 100)->nullable()->after('estado');
        });

        Schema::create('reglas_categoria_proveedor', function (Blueprint $table) {
            $table->id();
            $table->string('rut_emisor', 20)->unique();
            $table->string('nombre_emisor', 255)->nullable();
            $table->string('categoria', 100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reglas_categoria_proveedor');
        Schema::table('compras', function (Blueprint $table) {
            $table->dropColumn('categoria');
        });
    }
};
