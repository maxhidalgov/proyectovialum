<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Agrupa las boletas de documentos_facturacion por periodo+forma_pago
 * y crea/actualiza boleta_resumenes.
 *
 * Solo procesa documentos con fecha_emision >= 2026-01-01.
 *
 * Uso:
 *   php artisan boletas:recalcular-resumenes
 *   php artisan boletas:recalcular-resumenes --periodo=2026-06
 */
class BoletasRecalcularResumenes extends Command
{
    protected $signature = 'boletas:recalcular-resumenes
                            {--periodo= : Solo recalcular un periodo YYYY-MM}';

    protected $description = 'Agrupa boletas por periodo+forma_pago y actualiza boleta_resumenes';

    // tipo_documento_bsale_id que corresponden a boletas (tipo 1 = Boleta Electrónica en esta cuenta Bsale)
    private const TIPOS_BOLETA = [1];

    public function handle(): int
    {
        $periodo = $this->option('periodo');

        $query = DB::table('documentos_facturacion')
            ->whereIn('tipo_documento_bsale_id', self::TIPOS_BOLETA)
            ->whereNotNull('forma_pago')
            ->where('fecha_emision', '>=', '2026-01-01')
            ->selectRaw("DATE_FORMAT(fecha_emision, '%Y-%m') as periodo, forma_pago, COUNT(*) as total_boletas, SUM(monto) as monto_total");

        if ($periodo) {
            $query->whereRaw("DATE_FORMAT(fecha_emision, '%Y-%m') = ?", [$periodo]);
        }

        $grupos = $query->groupBy('periodo', 'forma_pago')->get();

        if ($grupos->isEmpty()) {
            $this->warn('Sin boletas con forma_pago en el rango indicado.');
            $this->line('  ¿Ya sincronizaste las boletas de 2026? (POST /api/ventas/sincronizar)');
            return 0;
        }

        $creados     = 0;
        $actualizados = 0;

        foreach ($grupos as $g) {
            $existing = DB::table('boleta_resumenes')
                ->where('periodo', $g->periodo)
                ->where('forma_pago', $g->forma_pago)
                ->first();

            if ($existing) {
                DB::table('boleta_resumenes')
                    ->where('id', $existing->id)
                    ->update([
                        'total_boletas' => $g->total_boletas,
                        'monto_total'   => $g->monto_total,
                        'updated_at'    => now(),
                    ]);
                $actualizados++;
            } else {
                DB::table('boleta_resumenes')->insert([
                    'periodo'       => $g->periodo,
                    'forma_pago'    => $g->forma_pago,
                    'total_boletas' => $g->total_boletas,
                    'monto_total'   => $g->monto_total,
                    'conciliado'    => false,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
                $creados++;
            }
        }

        $this->info("✅ boleta_resumenes actualizados: $creados creados, $actualizados actualizados.");

        $this->table(
            ['Periodo', 'Forma Pago', 'Boletas', 'Monto Total'],
            $grupos->map(fn($g) => [
                $g->periodo,
                $g->forma_pago,
                $g->total_boletas,
                '$' . number_format($g->monto_total, 0, ',', '.'),
            ])->toArray()
        );

        return 0;
    }
}
