<?php

namespace App\Console\Commands;

use App\Services\ChipaxApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ChipaxImportarImpuestos extends Command
{
    protected $signature   = 'chipax:importar-impuestos {--dry-run : Solo mostrar, no guardar}';
    protected $description = 'Importa pagos de impuestos de Chipax → tabla gastos (chipax_tipo=impuesto)';

    // idTipoImpuesto conocidos en Chipax
    private const TIPOS = [
        1 => 'IVA',
        2 => 'PPM',
        3 => 'Retención Honorarios',
        4 => 'Impuesto Único',
        5 => 'Otros Impuestos',
    ];

    public function handle(ChipaxApiService $api): int
    {
        $dry = $this->option('dry-run');
        $this->info($dry ? '[DRY-RUN] ' : '' . 'Importando impuestos de Chipax…');

        $page       = 1;
        $totalPages = 1;
        $stats      = ['creados' => 0, 'actualizados' => 0, 'pivots' => 0, 'sin_match_cartola' => 0];

        do {
            $data  = $api->get('/impuestos', ['page' => $page]);
            $pg    = $data['paginationAttributes'] ?? [];
            if ($page === 1) {
                $totalPages = (int)($pg['totalPages'] ?? 1);
                $this->line("Total impuestos: " . ($pg['count'] ?? '?') . " | Páginas: {$totalPages}");
            }

            $items = $data['items'] ?? $data['docs'] ?? [];
            foreach ($items as $imp) {
                $chipaxId    = (int) $imp['id'];
                $periodo     = substr($imp['periodo'] ?? date('Y-m-01'), 0, 10);
                $monto       = (float)($imp['monto'] ?? 0);
                if ($monto <= 0) continue;

                $tipoId      = (int)($imp['idTipoImpuesto'] ?? 0);
                $tipoNombre  = self::TIPOS[$tipoId] ?? "Impuesto tipo {$tipoId}";
                $anio        = (int)substr($periodo, 0, 4);
                $pagadoHist  = $anio <= 2024;
                $desc        = "{$tipoNombre} " . substr($periodo, 0, 7);

                if (!$dry) {
                    $existing = DB::table('gastos')
                        ->where('chipax_id', $chipaxId)
                        ->where('chipax_tipo', 'impuesto')
                        ->first();

                    if ($existing) {
                        DB::table('gastos')->where('id', $existing->id)->update([
                            'fecha'            => $periodo,
                            'descripcion'      => $desc,
                            'monto'            => $monto,
                            'pagado_historico' => $pagadoHist,
                            'updated_at'       => now(),
                        ]);
                        $gastoId = $existing->id;
                        $stats['actualizados']++;
                    } else {
                        $gastoId = DB::table('gastos')->insertGetId([
                            'chipax_id'   => $chipaxId,
                            'chipax_tipo' => 'impuesto',
                            'fecha'       => $periodo,
                            'descripcion' => $desc,
                            'categoria'   => 'Impuestos',
                            'monto'       => $monto,
                            'notas'       => "idTipoImpuesto={$tipoId}",
                            'pagado_historico' => $pagadoHist,
                            'created_at'  => now(),
                            'updated_at'  => now(),
                        ]);
                        $stats['creados']++;
                    }

                    // Vincular cartolas → gasto_movimiento
                    foreach ($imp['Cartolas'] ?? [] as $cartola) {
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
                    $this->line("  [DRY] {$desc} | monto={$monto} | hist={$pagadoHist}");
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
