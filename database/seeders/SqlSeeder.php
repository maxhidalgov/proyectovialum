<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SqlSeeder extends Seeder
{
    public function run()
    {
        $sql = File::get(database_path('seeders/data/proyectovialum_max.sql'));
        DB::unprepared($sql);
    }
}
