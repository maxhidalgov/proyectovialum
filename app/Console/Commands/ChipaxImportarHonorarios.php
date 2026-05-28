<?php

namespace App\Console\Commands;

use App\Services\ChipaxApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ChipaxImportarHonorarios extends Command
{
    protected $signature   = 'chipax:importar-honorarios {--dry-run : Solo mostrar, no guardar}';
    protected $description = 'Importa honorarios de Chipax → tabla gastos (chipax_tipo=honorario)';

    public function handle(ChipaxApiService $api): int
    {
        $dry = $this->option('dry-run');
        $this->info($dry ? '[DRY-RUN] ' : '' . 'Importando honorarios de Chipax…');

        $page       = 1;
        $totalPages = 1;
        $stats      = ['creados' => 0, 'actualizados' => 0, 'pivots' => 0, 'sin_match_cartola' => 0];

        do {
            $data  = $api->get('/honorarios', ['page' => $page]);
            $pg    = $data['paginationAttributes'] ?? [];
            if ($page === 1) {
                $totalPages = (int)($pg['totalPages'] ?? 1);
                $this->line("Total honorarios: " . ($pg['count'] ?? '?') . " | Páginas: {$totalPages}");
            }

            foreach ($data['items'] ?? [] as $h) {
                $chipaxId  = (int) $h['id'];
                $fecha     = substr($h['fechaEmision'] ?? date('Y-m-d'), 0, 10);
                $monto     = (float)($h['montoBruto'] ?? 0);
                $proveedor = $h['nombreEmisor'] ?? null;
                $folio     = $h['numeroBoleta'] ?? null;
                $desc      = trim("Honorario #$folio - $proveedor");

                // pagado_historico si es <= 2024
                $anio            = (int)substr($fecha, 0, 4);
                $pagadoHistorico = $anio <= 2024;

                if (!$dry) {
                    $existing = DB::table('gastos')
                        ->where('chipax_id', $chipaxId)
                        ->where('chipax_tipo', 'honorario')
                        ->first();

                    if ($existing) {
                        DB::table('gastos')->where('id', $existing->id)->update([
                            'fecha'            => $fecha,
                            'descripcion'      => $desc,
                            'monto'            => $monto,
                            'proveedor'        => $proveedor,
                            'numero_documento' => $folio,
                            'pagado_historico' => $pagadoHistorico,
                            'updated_at'       => now(),
                        ]);
                        $gastoId = $existing->id;
                        $stats['actualizados']++;
                    } else {
                        $gastoId = DB::table('gastos')->insertGetId([
                            'chipax_id'        => $chipaxId,
                            'chipax_tipo'      => 'honorario',
                            'chipax_proveedor' => $proveedor,
                            'fecha'            => $fecha,
                            'descripcion'      => $desc,
                            'categoria'        => 'Honorarios',
                            'monto'            => $monto,
                            'proveedor'        => $proveedor,
                            'numero_documento' => $folio,
                            'pagado_historico' => $pagadoHistorico,
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ]);
                        $stats['creados']++;
                    }

                    // Vincular cartolas → gasto_movimiento
                    foreach ($h['Cartolas'] ?? [] as $cartola) {
                        $cartolaChipaxId = (int)($cartola['id'] ?? 0);
                        $montoCartola    = abs((float)($cartola['cargo'] ?? $cartola['CartolaDocumento']['monto'] ?? 0));
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
                    $this->line("  [DRY] Honorario #{$folio} - {$proveedor} | {$fecha} | monto={$monto} | hist={$pagadoHistorico}");
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
