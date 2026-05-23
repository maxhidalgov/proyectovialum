<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_bancarios', function (Blueprint $table) {
            $table->string('bch_codigo', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_bancarios', function (Blueprint $table) {
            $table->string('bch_codigo', 20)->nullable()->change();
        });
    }
};
