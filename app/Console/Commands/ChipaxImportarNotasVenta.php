<?php

namespace App\Console\Commands;

use App\Services\ChipaxApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importa Notas de Venta (OTs) desde la API oficial de Chipax.
 *
 * Una "Nota de Venta" en Chipax (endpoint /notas-venta) representa
 * ingresos de caja sin boleta ni factura SII. Cada una puede estar
 * vinculada a varios movimientos bancarios (Cartolas).
 *
 * Por cada nota de venta:
 *  1. Crea/actualiza un registro en `ingresos_manuales` (chipax_id único).
 *  2. Por cada cartola vinculada, crea `ingreso_movimiento` pivot
 *     si existe el movimiento en nuestra BD (por chipax_id).
 *  3. Marca el movimiento como conciliado si está totalmente cubierto.
 *
 * Uso:
 *   php artisan chipax:importar-notas-venta
 *   php artisan chipax:importar-notas-venta --dry-run
 */
class ChipaxImportarNotasVenta extends Command
{
    protected $signature = 'chipax:importar-notas-venta
                            {--dry-run : Solo muestra estadísticas, no modifica la BD}';

    protected $description = 'Importa Notas de Venta (OTs) de Chipax → ingresos_manuales + ingreso_movimiento';

    public function handle(): int
    {
        $dry = $this->option('dry-run');
        if ($dry) $this->warn('[DRY-RUN: no se modificará la BD]');

        $api = new ChipaxApiService();

        // Pre-cargar chipax_ids de movimientos para búsqueda rápida
        $this->line('⟳ Cargando movimientos bancarios...');
        $movPorChipaxId = DB::table('movimientos_bancarios')
            ->whereNotNull('chipax_id')
            ->pluck('id', 'chipax_id');
        $this->line("  → {$movPorChipaxId->count()} movimientos con chipax_id");

        $page        = 1;
        $totalPages  = 1;
        $stats = [
            'notas_creadas'     => 0,
            'notas_actualizadas'=> 0,
            'pivots_creados'    => 0,
            'pivots_existian'   => 0,
            'movs_conciliados'  => 0,
            'cartolas_sin_mov'  => 0,
        ];
        $sinMov = [];

        $this->info("🗒️  Chipax → importar Notas de Venta");
        $bar = null;

        do {
            $data  = $api->get('/notas-venta', ['page' => $page]);
            $notas = $data['docs'] ?? [];

            if ($page === 1) {
                $total      = $data['total'] ?? count($notas);
                $totalPages = $data['pages'] ?? 1;
                $this->line("  → $total notas de venta en $totalPages páginas");
                $bar = $this->output->createProgressBar($totalPages);
                $bar->start();
            }

            foreach ($notas as $nota) {
                $chipaxId   = $nota['id'] ?? null;
                $folio      = $nota['folio'] ?? null;
                $montoTotal = (float) ($nota['montoTotal'] ?? 0);
                $fecha      = $nota['fecha'] ?? now()->format('Y-m-d');
                $cartolas   = $nota['cartolas'] ?? [];

                if (!$chipaxId) continue;

                // 1. Crear o actualizar el ingreso manual
                if (!$dry) {
                    $existing = DB::table('ingresos_manuales')
                        ->where('chipax_id', $chipaxId)
                        ->first();

                    if ($existing) {
                        DB::table('ingresos_manuales')
                            ->where('id', $existing->id)
                            ->update([
                                'fecha'        => $fecha,
                                'monto'        => $montoTotal,
                                'chipax_folio' => $folio,
                                'updated_at'   => now(),
                            ]);
                        $ingresoId = $existing->id;
                        $stats['notas_actualizadas']++;
                    } else {
                        $ingresoId = DB::table('ingresos_manuales')->insertGetId([
                            'chipax_id'    => $chipaxId,
                            'chipax_folio' => $folio,
                            'fecha'        => $fecha,
                            'descripcion'  => "Nota de Venta #$folio",
                            'monto'        => $montoTotal,
                            'categoria'    => 'Nota de Venta',
                            'created_at'   => now(),
                            'updated_at'   => now(),
                        ]);
                        $stats['notas_creadas']++;
                    }
                } else {
                    $ingresoId = null; // dry run
                    $existing  = DB::table('ingresos_manuales')->where('chipax_id', $chipaxId)->first();
                    if ($existing) $stats['notas_actualizadas']++; else $stats['notas_creadas']++;
                }

                // 2. Vincular a movimientos bancarios via cartolas
                foreach ($cartolas as $cartola) {
                    $chipaxCartolaId = $cartola['id'] ?? null;
                    $montoCartola    = (float) ($cartola['CartolaDocumento']['monto']
                                          ?? $cartola['abono']
                                          ?? 0);

                    if (!$chipaxCartolaId || $montoCartola <= 0) continue;

                    $movId = $movPorChipaxId->get($chipaxCartolaId);

                    if (!$movId) {
                        $stats['cartolas_sin_mov']++;
                        $sinMov[] = ['nota' => $folio, 'cartola_id' => $chipaxCartolaId, 'monto' => $montoCartola];
                        continue;
                    }

                    // Verificar si el pivot ya existe
                    $existe = DB::table('ingreso_movimiento')
                        ->where('ingreso_id', $ingresoId ?? $existing?->id ?? 0)
                        ->where('movimiento_id', $movId)
                        ->exists();

                    if ($existe) {
                        $stats['pivots_existian']++;
                        continue;
                    }

                    if (!$dry && $ingresoId) {
                        DB::table('ingreso_movimiento')->insert([
                            'ingreso_id'    => $ingresoId,
                            'movimiento_id' => $movId,
                            'monto'         => $montoCartola,
                            'created_at'    => now(),
                            'updated_at'    => now(),
                        ]);

                        // Recalcular si el movimiento queda conciliado
                        $this->marcarConciliadoSiCubierto($movId);
                    }

                    $stats['pivots_creados']++;
                }
            }

            $bar?->advance();
            $page++;

        } while ($page <= $totalPages);

        $bar?->finish();
        $this->newLine(2);

        $this->info('✅ Importación de Notas de Venta completa:');
        $this->table(['Operación', 'Cantidad'], [
            ['Notas de venta nuevas',              $stats['notas_creadas']],
            ['Notas de venta actualizadas',         $stats['notas_actualizadas']],
            ['Pivots ingreso_movimiento creados',   $stats['pivots_creados']],
            ['Pivots ya existían',                  $stats['pivots_existian']],
            ['Movimientos marcados conciliados',    $stats['movs_conciliados']],
            ['Cartolas sin movimiento en BD local', $stats['cartolas_sin_mov']],
        ]);

        if (!empty($sinMov)) {
            $this->warn(count($sinMov) . ' cartolas sin match en BD:');
            $this->table(['Nota', 'Cartola Chipax ID', 'Monto'],
                array_map(fn($r) => [$r['nota'], $r['cartola_id'], '$' . number_format($r['monto'], 0, ',', '.')],
                array_slice($sinMov, 0, 10))
            );
        }

        return 0;
    }

    private function marcarConciliadoSiCubierto(int $movId): void
    {
        $mov = DB::table('movimientos_bancarios')->where('id', $movId)->first(['monto', 'conciliado']);
        if (!$mov) return;

        $asignado = $this->calcularTotalAsignado($movId);
        if ($asignado >= (float) $mov->monto) {
            DB::table('movimientos_bancarios')->where('id', $movId)->update(['conciliado' => true]);
        }
    }

    private function calcularTotalAsignado(int $movId): float
    {
        return (float) DB::table('compra_movimiento')->where('movimiento_id', $movId)->sum('monto')
             + (float) DB::table('gasto_movimiento')->where('movimiento_id', $movId)->sum('monto')
             + (float) DB::table('pagos_empleado')->where('movimiento_id', $movId)->sum('monto')
             + (float) DB::table('venta_movimiento')->where('movimiento_id', $movId)->sum('monto')
             + (float) DB::table('ingreso_movimiento')->where('movimiento_id', $movId)->sum('monto');
    }
}
