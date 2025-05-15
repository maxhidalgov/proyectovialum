<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('colors', function (Blueprint $table) {
            $table->id();
            $table->string('nombre'); // 👈 Asegúrate de que esta línea está aquí
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('colors');
    }
};