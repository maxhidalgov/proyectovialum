<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('cotizaciones', 'public_token')) {
            Schema::table('cotizaciones', function (Blueprint $table) {
                $table->string('public_token', 48)->nullable()->after('token_bsale');
            });
        }

        // Backfill: token aleatorio no adivinable para las cotizaciones existentes.
        DB::table('cotizaciones')->whereNull('public_token')->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('cotizaciones')->where('id', $row->id)
                        ->update(['public_token' => Str::random(40)]);
                }
            });

        // Índice único (idempotente: solo si no existe ya)
        $indexes = collect(DB::select("SHOW INDEX FROM cotizaciones WHERE Key_name = 'cotizaciones_public_token_unique'"));
        if ($indexes->isEmpty()) {
            Schema::table('cotizaciones', function (Blueprint $table) {
                $table->unique('public_token');
            });
        }
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropUnique('cotizaciones_public_token_unique');
            $table->dropColumn('public_token');
        });
    }
};
