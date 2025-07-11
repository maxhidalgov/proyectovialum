<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('colores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique(); // 👈 Nombre del color
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('colores');
    }
};