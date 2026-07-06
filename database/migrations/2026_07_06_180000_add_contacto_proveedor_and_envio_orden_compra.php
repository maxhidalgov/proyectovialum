<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proveedors', function (Blueprint $table) {
            if (!Schema::hasColumn('proveedors', 'email')) {
                $table->string('email')->nullable()->after('contacto');
            }
            if (!Schema::hasColumn('proveedors', 'telefono')) {
                $table->string('telefono')->nullable()->after('email');
            }
        });

        Schema::table('ordenes_compra', function (Blueprint $table) {
            if (!Schema::hasColumn('ordenes_compra', 'enviado_at')) {
                $table->timestamp('enviado_at')->nullable()->after('estado');
            }
            if (!Schema::hasColumn('ordenes_compra', 'enviado_via')) {
                $table->string('enviado_via')->nullable()->after('enviado_at'); // email | whatsapp
            }
            if (!Schema::hasColumn('ordenes_compra', 'enviado_a')) {
                $table->string('enviado_a')->nullable()->after('enviado_via'); // correo o teléfono destino
            }
        });
    }

    public function down(): void
    {
        Schema::table('proveedors', function (Blueprint $table) {
            foreach (['email', 'telefono'] as $col) {
                if (Schema::hasColumn('proveedors', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('ordenes_compra', function (Blueprint $table) {
            foreach (['enviado_at', 'enviado_via', 'enviado_a'] as $col) {
                if (Schema::hasColumn('ordenes_compra', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
