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
    Schema::table('ventanas', function (Blueprint $table) {
        $table->integer('costo_unitario')->nullable();
        $table->integer('precio_unitario')->nullable();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventanas', function (Blueprint $table) {
            //
        });
    }
};
