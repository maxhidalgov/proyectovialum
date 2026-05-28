<?php

namespace App\Console\Commands;

use App\Services\ChipaxApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ChipaxImportarPrevired extends Command
{
    protected $signature   = 'chipax:importar-previred {--dry-run : Solo mostrar, no guardar}';
    protected $description = 'Importa pagos Previred de Chipax → tabla gastos (chipax_tipo=previred)';

    public function handle(ChipaxApiService $api): int
    {
        $dry = $this->option('dry-run');
        $this->info($dry ? '[DRY-RUN] ' : '' . 'Importando Previred de Chipax…');

        $page       = 1;
        $totalPages = 1;
        $stats      = ['creados' => 0, 'actualizados' => 0, 'pivots' => 0, 'sin_match_cartola' => 0];

        do {
            $data  = $api->get('/previred', ['page' => $page]);
            $pg    = $data['paginationAttributes'] ?? [];
            if ($page === 1) {
                $totalPages = (int)($pg['totalPages'] ?? 1);
                $this->line("Total Previred: " . ($pg['count'] ?? '?') . " | Páginas: {$totalPages}");
            }

            $items = $data['items'] ?? $data['docs'] ?? [];
            foreach ($items as $p) {
                $chipaxId = (int) $p['id'];
                // periodo viene como "YYYY-MM-01"
                $periodo  = substr($p['periodo'] ?? date('Y-m-01'), 0, 10);
                $monto    = (float)($p['monto'] ?? 0);
                if ($monto <= 0) continue;

                $anio            = (int)substr($periodo, 0, 4);
                $pagadoHistorico = $anio <= 2024;
                $desc            = 'Previred ' . substr($periodo, 0, 7); // "Previred 2025-01"

                if (!$dry) {
                    $existing = DB::table('gastos')
                        ->where('chipax_id', $chipaxId)
                        ->where('chipax_tipo', 'previred')
                        ->first();

                    if ($existing) {
                        DB::table('gastos')->where('id', $existing->id)->update([
                            'fecha'            => $periodo,
                            'descripcion'      => $desc,
                            'monto'            => $monto,
                            'pagado_historico' => $pagadoHistorico,
                            'updated_at'       => now(),
                        ]);
                        $gastoId = $existing->id;
                        $stats['actualizados']++;
                    } else {
                        $gastoId = DB::table('gastos')->insertGetId([
                            'chipax_id'        => $chipaxId,
                            'chipax_tipo'      => 'previred',
                            'fecha'            => $periodo,
                            'descripcion'      => $desc,
                            'categoria'        => 'Previred',
                            'monto'            => $monto,
                            'pagado_historico' => $pagadoHistorico,
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ]);
                        $stats['creados']++;
                    }

                    // Vincular cartolas → gasto_movimiento
                    foreach ($p['Cartolas'] ?? [] as $cartola) {
                        $cartolaChipaxId = (int)($cartola['id'] ?? 0);
                        $montoCartola    = abs((float)(
                            $cartola['cargo'] ??
                            $cartola['CartolaDocumento']['monto'] ?? 0
                        ));
                        if ($cartolaChipaxId <= 0 || $montoCartola <= 0) continue;

                        $mov = DB::table('movimientos_bancarios')
                            ->where('chipax_id', $cartolaChipaxId)
                            ->first();

                        if (!$mov) { $stats['sin_match_cartola']++; continue; }

                        $existe = DB::table('gasto_movimiento')
                            ->where('gasto_id', $gastoId)
                            ->where('movimiento_id', $mov->id)
                            ->exists();

                        if (!$existe) {
                            DB::table('gasto_movimiento')->insert([
                                'gasto_id'      => $gastoId,
                                'movimiento_id' => $mov->id,
                                'monto'         => $montoCartola,
                                'created_at'    => now(),
                                'updated_at'    => now(),
                            ]);
                            $stats['pivots']++;
                        }
                    }
                } else {
                    $this->line("  [DRY] Previred {$desc} | monto={$monto} | hist={$pagadoHistorico}");
                    $stats['creados']++;
                }
            }
            $page++;
        } while ($page <= $totalPages);

        $this->table(
            ['Creados', 'Actualizados', 'Pivots gasto_mov', 'Sin match cartola'],
            [[$stats['creados'], $stats['actualizados'], $stats['pivots'], $stats['sin_match_cartola']]]
        );

        return 0;
    }
}
