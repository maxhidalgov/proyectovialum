<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Regla: pagos al SII (IVA, retención honorarios, etc.) → categoría "Impuestos".
        // tipo 'D' (cargo/egreso). Idempotente por patrón.
        $reglas = [
            ['nombre' => 'Pago SII (impuestos)', 'patron' => 'Sii.cl'],
            ['nombre' => 'Pago SII',             'patron' => 'Pago En Sii'],
        ];

        foreach ($reglas as $r) {
            $existe = DB::table('reglas_conciliacion')->where('patron', $r['patron'])->exists();
            if (!$existe) {
                DB::table('reglas_conciliacion')->insert([
                    'nombre'     => $r['nombre'],
                    'patron'     => $r['patron'],
                    'categoria'  => 'Impuestos',
                    'tipo'       => 'D',
                    'prioridad'  => 10,
                    'activa'     => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('reglas_conciliacion')
            ->whereIn('patron', ['Sii.cl', 'Pago En Sii'])
            ->where('categoria', 'Impuestos')
            ->delete();
    }
};
