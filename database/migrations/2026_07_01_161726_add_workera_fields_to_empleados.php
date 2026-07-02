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
        Schema::table('empleados', function (Blueprint $table) {
            if (!Schema::hasColumn('empleados', 'workera_code')) {
                $table->string('workera_code', 50)->nullable()->unique()->after('chipax_id');
            }
            if (!Schema::hasColumn('empleados', 'telefono')) {
                $table->string('telefono', 20)->nullable()->after('workera_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropUnique(['workera_code']);
            $table->dropColumn(['workera_code', 'telefono']);
        });
    }
};
