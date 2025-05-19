<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EstadoCotizacion;

class EstadosCotizacionSeeder extends Seeder
{
    public function run()
    {
        $estados = ['Evaluación', 'Aprobada', 'Rechazada', 'Anulada', 'Enviada'];

        foreach ($estados as $estado) {
            EstadoCotizacion::firstOrCreate(['nombre' => $estado]);
        }
    }
}