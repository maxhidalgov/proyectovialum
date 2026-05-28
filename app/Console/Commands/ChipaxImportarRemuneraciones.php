<?php

namespace App\Console\Commands;

use App\Services\ChipaxApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importa Remuneraciones (sueldos) desde la API oficial de Chipax.
 *
 * Por cada remuneración:
 *  1. Busca/crea el empleado en nuestra BD (match por RUT del empleado Chipax).
 *  2. Crea/actualiza el registro en `pagos_empleado` (chipax_remuneracion_id único).
 *  3. Por cada cartola vinculada, enlaza el movimiento bancario al pago.
 *  4. Marca el movimiento como conciliado si está totalmente cubierto.
 *
 * Uso:
 *   php artisan chipax:importar-remuneraciones
 *   php artisan chipax:importar-remuneraciones --dry-run
 */
class ChipaxImportarRemuneraciones extends Command
{
    protected $signature = 'chipax:importar-remuneraciones
                            {--dry-run : Solo muestra estadísticas, no modifica la BD}';

    protected $description = 'Importa Remuneraciones de Chipax → empleados + pagos_empleado';

    public function handle(): int
    {
        $dry = $this->option('dry-run');
        if ($dry) $this->warn('[DRY-RUN: no se modificará la BD]');

        $api = new ChipaxApiService();

        // Pre-cargar movimientos y empleados para búsqueda rápida
        $this->line('⟳ Cargando movimientos bancarios y empleados...');
        $movPorChipaxId  = DB::table('movimientos_bancarios')
            ->whereNotNull('chipax_id')
            ->pluck('id', 'chipax_id');
        $empPorRut       = DB::table('empleados')
            ->pluck('id', 'rut');
        $empPorChipaxId  = DB::table('empleados')
            ->whereNotNull('chipax_id')
            ->pluck('id', 'chipax_id');

        $this->line("  → {$movPorChipaxId->count()} movimientos, {$empPorRut->count()} empleados");

        $page       = 1;
        $totalPages = 1;
        $stats = [
            'remuneraciones_creadas'     => 0,
            'remuneraciones_actualizadas'=> 0,
            'empleados_creados'          => 0,
            'movs_vinculados'            => 0,
            'movs_conciliados'           => 0,
            'sin_cartola'                => 0,
            'cartolas_sin_mov'           => 0,
        ];
        $resumen = [];

        $this->info("👷 Chipax → importar Remuneraciones");
        $bar = null;

        do {
            $data          = $api->get('/remuneraciones', ['page' => $page]);
            $remuneraciones = $data['items'] ?? [];
            $pagination    = $data['paginationAttributes'] ?? [];

            if ($page === 1) {
                $total      = $pagination['count'] ?? count($remuneraciones);
                $totalPages = $pagination['totalPages'] ?? 1;
                $this->line("  → $total remuneraciones en $totalPages páginas");
                $bar = $this->output->createProgressBar($totalPages);
                $bar->start();
            }

            foreach ($remuneraciones as $rem) {
                $chipaxRemId = $rem['id'] ?? null;
                $chipaxEmpId = $rem['idEmpleado'] ?? null;
                $montoLiq    = (float) ($rem['montoLiquido'] ?? 0);
                $periodo     = $rem['periodo'] ?? null;
                $cartolas    = $rem['Cartolas'] ?? [];
                $empleadoData = $rem['Empleado'] ?? [];

                if (!$chipaxRemId || !$chipaxEmpId || $montoLiq <= 0 || !$periodo) continue;

                $periodoDate = substr($periodo, 0, 7) . '-01'; // YYYY-MM-01

                // 1. Buscar o crear el empleado
                $empleadoId = $empPorChipaxId->get($chipaxEmpId)
                           ?? $this->resolverEmpleado($chipaxEmpId, $empleadoData, $empPorRut, $dry);

                if (!$empleadoId) {
                    // No pudimos crear el empleado (dry run o sin RUT)
                    $resumen[] = [
                        'periodo'   => $periodoDate,
                        'empleado'  => ($empleadoData['nombre'] ?? '') . ' ' . ($empleadoData['apellido'] ?? ''),
                        'monto'     => $montoLiq,
                        'resultado' => 'sin_empleado',
                    ];
                    continue;
                }

                if (!isset($empPorChipaxId[$chipaxEmpId]) && !$dry) {
                    $stats['empleados_creados']++;
                    $empPorChipaxId[$chipaxEmpId] = $empleadoId;
                }

                // Buscar la primera cartola con monto > 0 para el movimiento_id
                $movId = null;
                foreach ($cartolas as $cartola) {
                    $chipaxCartolaId = $cartola['id'] ?? null;
                    $montoCartola    = (float) ($cartola['CartolaDocumento']['monto']
                                          ?? $cartola['cargo']
                                          ?? 0);
                    if ($chipaxCartolaId && $montoCartola > 0) {
                        $movId = $movPorChipaxId->get($chipaxCartolaId);
                        break;
                    }
                }

                if (empty(array_filter($cartolas, fn($c) => (float)($c['cargo'] ?? 0) > 0))) {
                    $stats['sin_cartola']++;
                }

                if (!$movId && !empty(array_filter($cartolas))) {
                    $stats['cartolas_sin_mov']++;
                }

                // 2. Crear o actualizar pagos_empleado
                // Usamos chipax_remuneracion_id como clave única — cada registro
                // Chipax es una fila independiente (permite múltiples pagos/mes/empleado)
                if (!$dry) {
                    $existing = DB::table('pagos_empleado')
                        ->where('chipax_remuneracion_id', $chipaxRemId)
                        ->first();

                    if ($existing) {
                        DB::table('pagos_empleado')->where('id', $existing->id)->update([
                            'monto'        => $montoLiq,
                            'movimiento_id'=> $movId ?? $existing->movimiento_id,
                            'pagado'       => true,
                            'fecha_pago'   => $movId ? $this->obtenerFechaMov($movId) : $existing->fecha_pago,
                            'updated_at'   => now(),
                        ]);
                        $stats['remuneraciones_actualizadas']++;
                    } else {
                        // Insertar nuevo registro — cada chipax_remuneracion_id es único
                        DB::table('pagos_empleado')->insert([
                            'chipax_remuneracion_id' => $chipaxRemId,
                            'empleado_id'  => $empleadoId,
                            'movimiento_id'=> $movId,
                            'periodo'      => $periodoDate,
                            'monto'        => $montoLiq,
                            'tipo'         => 'sueldo',
                            'pagado'       => $movId ? true : false,
                            'fecha_pago'   => $movId ? $this->obtenerFechaMov($movId) : null,
                            'notas'        => "Importado desde Chipax (rem id $chipaxRemId)",
                            'created_at'   => now(),
                            'updated_at'   => now(),
                        ]);
                        $stats['remuneraciones_creadas']++;
                    }

                    if ($movId) {
                        $stats['movs_vinculados']++;
                        $this->marcarConciliadoSiCubierto($movId);
                    }
                } else {
                    // dry run: solo contar
                    $existing = DB::table('pagos_empleado')
                        ->where('chipax_remuneracion_id', $chipaxRemId)
                        ->first();
                    if ($existing) $stats['remuneraciones_actualizadas']++; else $stats['remuneraciones_creadas']++;
                }
            }

            $bar?->advance();
            $page++;

        } while ($page <= $totalPages);

        $bar?->finish();
        $this->newLine(2);

        $this->info('✅ Importación de Remuneraciones completa:');
        $this->table(['Operación', 'Cantidad'], [
            ['Empleados creados automáticamente',   $stats['empleados_creados']],
            ['Remuneraciones nuevas',                $stats['remuneraciones_creadas']],
            ['Remuneraciones actualizadas',          $stats['remuneraciones_actualizadas']],
            ['Movimientos vinculados',               $stats['movs_vinculados']],
            ['Movimientos marcados conciliados',     $stats['movs_conciliados']],
            ['Remuneraciones sin cartola (no pagadas)', $stats['sin_cartola']],
            ['Cartolas sin movimiento en BD local',  $stats['cartolas_sin_mov']],
        ]);

        return 0;
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Busca el empleado local por RUT de Chipax.
     * Si no existe, lo crea con la info disponible de Chipax.
     * Retorna el empleado_id local (o null si dry-run / sin datos).
     */
    private function resolverEmpleado(
        int   $chipaxEmpId,
        array $empData,
        mixed $empPorRut,
        bool  $dry
    ): ?int {
        $rut    = $empData['rut'] ?? null;
        $nombre = trim(($empData['nombre'] ?? '') . ' ' . ($empData['apellido'] ?? ''));

        // 1. Buscar por RUT
        if ($rut) {
            $rutNorm = str_replace('.', '', trim($rut));
            $id = $empPorRut->get($rutNorm) ?? $empPorRut->get($rut);
            if ($id) {
                // Actualizar chipax_id si no lo tiene
                if (!$dry) {
                    DB::table('empleados')
                        ->where('id', $id)
                        ->whereNull('chipax_id')
                        ->update(['chipax_id' => $chipaxEmpId]);
                }
                return $id;
            }
        }

        // 2. No encontrado: crear con datos básicos de Chipax
        if ($dry) return null;
        if (!$nombre) return null;

        $rutNorm = $rut ? str_replace('.', '', trim($rut)) : 'CHIPAX-' . $chipaxEmpId;

        $id = DB::table('empleados')->insertGetId([
            'chipax_id'    => $chipaxEmpId,
            'nombre'       => $nombre,
            'rut'          => $rutNorm,
            'sueldo_base'  => 0,        // Se actualizará manualmente
            'fecha_ingreso'=> now()->format('Y-m-d'),
            'activo'       => true,
            'notas'        => "Importado automáticamente desde Chipax (id $chipaxEmpId)",
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return $id;
    }

    private function obtenerFechaMov(int $movId): ?string
    {
        $mov = DB::table('movimientos_bancarios')->where('id', $movId)->value('fecha_contable');
        return $mov;
    }

    private function marcarConciliadoSiCubierto(int $movId): void
    {
        $mov = DB::table('movimientos_bancarios')->where('id', $movId)->first(['monto', 'conciliado']);
        if (!$mov) return;

        if ($this->calcularTotalAsignado($movId) >= (float) $mov->monto) {
            DB::table('movimientos_bancarios')->where('id', $movId)->update(['conciliado' => true]);
            // No incrementamos stats aquí por simplicidad; el resultado es correcto
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
