<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE cotizacion_detalles MODIFY COLUMN tipo_item ENUM('ventana','producto','winperfil','item_libre') NOT NULL DEFAULT 'ventana'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE cotizacion_detalles MODIFY COLUMN tipo_item ENUM('ventana','producto','winperfil') NOT NULL DEFAULT 'ventana'");
    }
};
