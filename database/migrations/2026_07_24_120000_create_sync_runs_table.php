<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sync_runs')) {
            Schema::create('sync_runs', function (Blueprint $table) {
                $table->id();
                $table->string('comando', 40)->default('sync:diario');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->boolean('ok')->default(false);
                $table->integer('ventas_nuevas')->default(0);
                $table->integer('compras_nuevas')->default(0);
                $table->integer('clientes_nuevos')->default(0);
                $table->text('detalle')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
    }
};
