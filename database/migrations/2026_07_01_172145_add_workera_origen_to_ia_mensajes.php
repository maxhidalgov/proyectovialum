<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE ia_mensajes MODIFY COLUMN origen ENUM('app','whatsapp','workera') NOT NULL DEFAULT 'app'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE ia_mensajes MODIFY COLUMN origen ENUM('app','whatsapp') NOT NULL DEFAULT 'app'");
    }
};
