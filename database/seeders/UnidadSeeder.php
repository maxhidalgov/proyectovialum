<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnidadSeeder extends Seeder
{
    public function run()
    {
        DB::table('unidades')->insert([
            [
                'nombre' => 'unidad',
                'requiere_division' => false,
                'descripcion' => 'Producto individual, no requiere división',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nombre' => 'barra',
                'requiere_division' => true,
                'descripcion' => 'Producto en barras largas que se divide según largo',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nombre' => 'metro',
                'requiere_division' => false,
                'descripcion' => 'Producto calculado directamente por metro lineal',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}