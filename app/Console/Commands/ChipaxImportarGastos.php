<?php

namespace App\Console\Commands;

use App\Services\ChipaxApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importa Gastos (sin factura) desde la API oficial de Chipax.
 *
 * Un "Gasto" en Chipax es un egreso de caja sin documento SII
 * (sin factura de compra). Ya puede estar vinculado a uno o más
 * movimientos bancarios (Cartolas).
 *
 * Por cada gasto:
 *  1. Crea/actualiza un registro en `gastos` (chipax_id único).
 *  2. Por cada cartola vinculada, crea `gasto_movimiento` pivot.
 *  3. Marca el movimiento como conciliado si está totalmente cubierto.
 *
 * Uso:
 *   php artisan chipax:importar-gastos
 *   php artisan chipax:importar-gastos --dry-run
 */
class ChipaxImportarGastos extends Command
{
    protected $signature = 'chipax:importar-gastos
                            {--dry-run : Solo muestra estadísticas, no modifica la BD}';

    protected $description = 'Importa Gastos sin factura de Chipax → gastos + gasto_movimiento';

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

        $page       = 1;
        $totalPages = 1;
        $stats = [
            'gastos_creados'     => 0,
            'gastos_actualizados'=> 0,
            'pivots_creados'     => 0,
            'pivots_existian'    => 0,
            'movs_conciliados'   => 0,
            'cartolas_sin_mov'   => 0,
            'gastos_sin_cartola' => 0,
        ];
        $sinMov = [];

        $this->info("💸 Chipax → importar Gastos sin factura");
        $bar = null;

        do {
            $data   = $api->get('/gastos', ['page' => $page]);
            $gastos = $data['items'] ?? [];
            $pagination = $data['paginationAttributes'] ?? [];

            if ($page === 1) {
                $total      = $pagination['count'] ?? count($gastos);
                $totalPages = $pagination['totalPages'] ?? 1;
                $this->line("  → $total gastos en $totalPages páginas");
                $bar = $this->output->createProgressBar($totalPages);
                $bar->start();
            }

            foreach ($gastos as $gasto) {
                $chipaxId   = $gasto['id'] ?? null;
                $monto      = (float) ($gasto['monto'] ?? 0);
                $descripcion = trim($gasto['descripcion'] ?? '');
                $fecha      = $gasto['fecha'] ?? now()->format('Y-m-d');
                $proveedor  = $gasto['proveedor'] ?? null;
                $numDoc     = $gasto['numDocumento'] ?? null;
                $cartolas   = $gasto['Cartolas'] ?? [];

                if (!$chipaxId || $monto <= 0) continue;

                // Solo importar los que tienen cartola vinculada (están pagados y conciliados en Chipax)
                $cartolasConMonto = array_filter($cartolas, function ($c) {
                    $m = $c['CartolaDocumento']['monto'] ?? $c['cargo'] ?? 0;
                    return (float)$m > 0 && !empty($c['id']);
                });

                if (empty($cartolasConMonto)) {
                    $stats['gastos_sin_cartola']++;
                    continue; // gasto sin pago bancario registrado
                }

                // 1. Crear o actualizar el gasto
                if (!$dry) {
                    $existing = DB::table('gastos')
                        ->where('chipax_id', $chipaxId)
                        ->first();

                    if ($existing) {
                        DB::table('gastos')->where('id', $existing->id)->update([
                            'fecha'            => $fecha,
                            'descripcion'      => $descripcion ?: $existing->descripcion,
                            'monto'            => $monto,
                            'proveedor'        => $proveedor,
                            'chipax_proveedor' => $proveedor,
                            'numero_documento' => $numDoc ?: null,
                            'updated_at'       => now(),
                        ]);
                        $gastoId = $existing->id;
                        $stats['gastos_actualizados']++;
                    } else {
                        $gastoId = DB::table('gastos')->insertGetId([
                            'chipax_id'        => $chipaxId,
                            'chipax_proveedor' => $proveedor,
                            'fecha'            => $fecha,
                            'descripcion'      => $descripcion ?: 'Gasto importado de Chipax',
                            'monto'            => $monto,
                            'proveedor'        => $proveedor,
                            'numero_documento' => $numDoc ?: null,
                            'notas'            => "Importado desde Chipax (id $chipaxId)",
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ]);
                        $stats['gastos_creados']++;
                    }
                } else {
                    $gastoId  = null;
                    $existing = DB::table('gastos')->where('chipax_id', $chipaxId)->first();
                    if ($existing) $stats['gastos_actualizados']++; else $stats['gastos_creados']++;
                }

                // 2. Vincular a movimientos bancarios via cartolas
                foreach ($cartolasConMonto as $cartola) {
                    $chipaxCartolaId = $cartola['id'] ?? null;
                    $montoCartola    = (float) ($cartola['CartolaDocumento']['monto']
                                          ?? $cartola['cargo']
                                          ?? 0);

                    if (!$chipaxCartolaId || $montoCartola <= 0) continue;

                    $movId = $movPorChipaxId->get($chipaxCartolaId);

                    if (!$movId) {
                        $stats['cartolas_sin_mov']++;
                        $sinMov[] = [
                            'gasto' => $descripcion,
                            'cartola_id' => $chipaxCartolaId,
                            'monto' => $montoCartola,
                        ];
                        continue;
                    }

                    $existe = DB::table('gasto_movimiento')
                        ->where('gasto_id', $gastoId ?? $existing?->id ?? 0)
                        ->where('movimiento_id', $movId)
                        ->exists();

                    if ($existe) {
                        $stats['pivots_existian']++;
                        continue;
                    }

                    if (!$dry && $gastoId) {
                        DB::table('gasto_movimiento')->insert([
                            'gasto_id'      => $gastoId,
                            'movimiento_id' => $movId,
                            'monto'         => $montoCartola,
                            'created_at'    => now(),
                            'updated_at'    => now(),
                        ]);

                        $this->marcarConciliadoSiCubierto($movId);
                        $stats['movs_conciliados']++;
                    }

                    $stats['pivots_creados']++;
                }
            }

            $bar?->advance();
            $page++;

        } while ($page <= $totalPages);

        $bar?->finish();
        $this->newLine(2);

        $this->info('✅ Importación de Gastos completa:');
        $this->table(['Operación', 'Cantidad'], [
            ['Gastos nuevos',                       $stats['gastos_creados']],
            ['Gastos actualizados',                 $stats['gastos_actualizados']],
            ['Gastos sin cartola (no pagados)',      $stats['gastos_sin_cartola']],
            ['Pivots gasto_movimiento creados',      $stats['pivots_creados']],
            ['Pivots ya existían',                   $stats['pivots_existian']],
            ['Movimientos marcados conciliados',     $stats['movs_conciliados']],
            ['Cartolas sin movimiento en BD local',  $stats['cartolas_sin_mov']],
        ]);

        if (!empty($sinMov)) {
            $this->warn(count($sinMov) . ' cartolas sin match en BD:');
            $this->table(['Gasto', 'Cartola Chipax ID', 'Monto'],
                array_map(fn($r) => [
                    mb_substr($r['gasto'], 0, 30),
                    $r['cartola_id'],
                    '$' . number_format($r['monto'], 0, ',', '.'),
                ], array_slice($sinMov, 0, 15))
            );
        }

        return 0;
    }

    private function marcarConciliadoSiCubierto(int $movId): void
    {
        $mov = DB::table('movimientos_bancarios')->where('id', $movId)->first(['monto', 'conciliado']);
        if (!$mov) return;

        if ($this->calcularTotalAsignado($movId) >= (float) $mov->monto) {
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
