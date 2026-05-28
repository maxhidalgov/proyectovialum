<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Materializa los links de Chipax (raw.linked_docs) en nuestras tablas pivot locales.
 *
 * Para cada movimiento con linked_docs sincronizados (chipax:sync-docs), intenta
 * encontrar el documento equivalente en nuestra BD y crea el registro pivot:
 *
 *   Chipax "Compra"  → compras (por folio)  → compra_movimiento
 *   Chipax "DTE"     → documentos_facturacion (por numero_documento_bsale) → venta_movimiento
 *
 * Esto resuelve el problema de facturas de compra que siguen apareciendo como
 * "no conciliadas" aunque Chipax ya las tenga vinculadas al movimiento bancario.
 *
 * Uso:
 *   php artisan chipax:link-local
 *   php artisan chipax:link-local --dry-run
 */
class ChipaxLinkLocal extends Command
{
    protected $signature = 'chipax:link-local
                            {--dry-run : Solo muestra qué haría, sin modificar la BD}';

    protected $description = 'Crea los links pivot locales desde los docs Chipax ya sincronizados';

    public function handle(): int
    {
        $dry = $this->option('dry-run');

        if ($dry) $this->warn('[DRY-RUN: no se modificará la BD]');

        $movimientos = DB::table('movimientos_bancarios')
            ->whereRaw("raw LIKE '%linked_docs%'")
            ->whereNotNull('chipax_id')
            ->get(['id', 'chipax_id', 'monto', 'tipo', 'raw', 'conciliado']);

        $this->line("Movimientos con linked_docs: {$movimientos->count()}");
        $this->newLine();

        $stats = [
            'compras_vinculadas'     => 0,
            'compras_no_encontradas' => 0,
            'compras_ya_existia'     => 0,
            'dtes_vinculadas'        => 0,
            'dtes_no_encontradas'    => 0,
            'dtes_ya_existia'        => 0,
            'ots_vinculadas'         => 0,
            'ots_no_encontradas'     => 0,
            'ots_ya_existia'         => 0,
            'movimientos_conciliados'=> 0,
            'otros_ignorados'        => 0,
        ];

        $noEncontrados = [];

        foreach ($movimientos as $mov) {
            $raw        = is_string($mov->raw) ? json_decode($mov->raw, true) : (array) ($mov->raw ?? []);
            $linkedDocs = $raw['linked_docs'] ?? [];

            if (empty($linkedDocs)) continue;

            $montoTotalAsignado = 0;

            foreach ($linkedDocs as $doc) {
                $tipo  = $doc['tipo'];
                $monto = (float) ($doc['monto'] ?? 0);

                $result = null;

                if ($tipo === 'Compra') {
                    $result = $this->linkCompra($mov->id, $doc, $monto, $dry);
                    $stats["compras_{$result}"]++;
                    if ($result === 'vinculadas') $montoTotalAsignado += $monto;

                } elseif ($tipo === 'DTE') {
                    $result = $this->linkDte($mov->id, $doc, $monto, $dry);
                    $stats["dtes_{$result}"]++;
                    if ($result === 'vinculadas') $montoTotalAsignado += $monto;

                } elseif ($tipo === 'OT') {
                    // OT = Nota de Venta importada vía chipax:importar-notas-venta
                    $result = $this->linkOt($mov->id, $doc, $monto, $dry);
                    $stats["ots_{$result}"]++;
                    if ($result === 'vinculadas') $montoTotalAsignado += $monto;

                } else {
                    // Gasto, Honorario, Remuneracion, Previred, Impuesto, Boleta
                    // → se importan con chipax:importar-gastos / chipax:importar-remuneraciones
                    $stats['otros_ignorados']++;
                }

                if ($result && in_array($result, ['no_encontradas', 'no_encontrada'])) {
                    $noEncontrados[] = [
                        'mov_id'    => $mov->id,
                        'tipo'      => $tipo,
                        'folio'     => $doc['folio'] ?? null,
                        'label'     => $doc['label'] ?? '',
                        'monto'     => $monto,
                    ];
                }
            }

            // Marcar el movimiento como conciliado si el total asignado cubre el monto
            if (!$dry && $montoTotalAsignado > 0) {
                $totalRealAsignado = $this->calcularTotalAsignado($mov->id);
                if ($totalRealAsignado >= (float) $mov->monto) {
                    DB::table('movimientos_bancarios')
                        ->where('id', $mov->id)
                        ->update(['conciliado' => true]);
                    $stats['movimientos_conciliados']++;
                }
            }
        }

        // Mostrar resumen
        $this->info('✅ Resultado:');
        $this->table(
            ['Operación', 'Cantidad'],
            [
                ['Compras vinculadas (nuevas)',              $stats['compras_vinculadas']],
                ['Compras ya tenían pivot',                  $stats['compras_ya_existia']],
                ['Compras sin match en BD local',            $stats['compras_no_encontradas']],
                ['DTEs vinculadas (nuevas)',                  $stats['dtes_vinculadas']],
                ['DTEs ya tenían pivot',                     $stats['dtes_ya_existia']],
                ['DTEs sin match en BD local',               $stats['dtes_no_encontradas']],
                ['OTs (notas venta) vinculadas (nuevas)',    $stats['ots_vinculadas']],
                ['OTs ya tenían pivot',                      $stats['ots_ya_existia']],
                ['OTs sin ingreso_manual en BD local',       $stats['ots_no_encontradas']],
                ['Movimientos marcados conciliados',         $stats['movimientos_conciliados']],
                ['Otros ignorados (Gasto/Remun → usar importar-*)', $stats['otros_ignorados']],
            ]
        );

        if (!empty($noEncontrados)) {
            $this->newLine();
            $this->warn(count($noEncontrados) . ' documentos sin match en BD local:');
            $rows = array_slice($noEncontrados, 0, 20);
            $this->table(['Mov ID', 'Tipo', 'Folio', 'Label', 'Monto'], array_map(
                fn($r) => [$r['mov_id'], $r['tipo'], $r['folio'], $r['label'], '$' . number_format($r['monto'], 0, ',', '.')],
                $rows
            ));
            if (count($noEncontrados) > 20) {
                $this->line('  ... y ' . (count($noEncontrados) - 20) . ' más');
            }
        }

        return 0;
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Vincula una Compra de Chipax al movimiento.
     * Busca por folio en nuestra tabla compras.
     * Retorna: 'vinculadas' | 'ya_existia' | 'no_encontradas'
     */
    private function linkCompra(int $movId, array $doc, float $monto, bool $dry): string
    {
        $folio = $doc['folio'] ?? null;
        if (!$folio) return 'no_encontradas';

        // Buscar por folio (puede haber varios con mismo folio de distintos proveedores)
        // Si viene el RUT, usarlo para afinar el match
        $query = DB::table('compras')->where('folio', $folio);

        if (!empty($doc['rut'])) {
            $rutNorm = $this->normalizarRut($doc['rut']);
            // Intentar match exacto por folio+rut; si no, solo folio
            $compra = $query->where('rut_emisor', $rutNorm)->first()
                   ?? $query->first();
        } else {
            $compra = $query->first();
        }

        if (!$compra) return 'no_encontradas';

        // Verificar si el pivot ya existe
        $existe = DB::table('compra_movimiento')
            ->where('movimiento_id', $movId)
            ->where('compra_id', $compra->id)
            ->exists();

        if ($existe) return 'ya_existia';

        if (!$dry) {
            DB::table('compra_movimiento')->insert([
                'movimiento_id' => $movId,
                'compra_id'     => $compra->id,
                'monto'         => $monto,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        return 'vinculadas';
    }

    /**
     * Vincula un DTE de Chipax al movimiento.
     * Busca en documentos_facturacion por numero_documento_bsale (el folio).
     * Retorna: 'vinculadas' | 'ya_existia' | 'no_encontradas'
     */
    private function linkDte(int $movId, array $doc, float $monto, bool $dry): string
    {
        $folio = $doc['folio'] ?? null;
        if (!$folio) return 'no_encontradas';

        // numero_documento_bsale está guardado como string
        $venta = DB::table('documentos_facturacion')
            ->where('numero_documento_bsale', (string) $folio)
            ->where('estado', 'emitido')
            ->first();

        if (!$venta) return 'no_encontradas';

        // Verificar si el pivot ya existe
        $existe = DB::table('venta_movimiento')
            ->where('movimiento_id', $movId)
            ->where('venta_id', $venta->id)
            ->exists();

        if ($existe) return 'ya_existia';

        if (!$dry) {
            DB::table('venta_movimiento')->insert([
                'venta_id'     => $venta->id,
                'movimiento_id' => $movId,
                'monto'        => $monto,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }

        return 'vinculadas';
    }

    /**
     * Suma todos los montos asignados al movimiento via pivot tables.
     */
    private function calcularTotalAsignado(int $movId): float
    {
        $compra  = (float) DB::table('compra_movimiento')->where('movimiento_id', $movId)->sum('monto');
        $gasto   = (float) DB::table('gasto_movimiento')->where('movimiento_id', $movId)->sum('monto');
        $sueldo  = (float) DB::table('pagos_empleado')->where('movimiento_id', $movId)->sum('monto');
        $venta   = (float) DB::table('venta_movimiento')->where('movimiento_id', $movId)->sum('monto');
        $ingreso = (float) DB::table('ingreso_movimiento')->where('movimiento_id', $movId)->sum('monto');

        return $compra + $gasto + $sueldo + $venta + $ingreso;
    }

    /**
     * Vincula una OT (Nota de Venta) de Chipax al movimiento.
     * Busca en ingresos_manuales por chipax_id = doc['id'].
     * Requiere haber corrido chipax:importar-notas-venta previamente.
     * Retorna: 'vinculadas' | 'ya_existia' | 'no_encontradas'
     */
    private function linkOt(int $movId, array $doc, float $monto, bool $dry): string
    {
        $chipaxOtId = $doc['id'] ?? null;
        if (!$chipaxOtId) return 'no_encontradas';

        $ingreso = DB::table('ingresos_manuales')
            ->where('chipax_id', $chipaxOtId)
            ->first();

        if (!$ingreso) return 'no_encontradas'; // No importado aún

        // Verificar si el pivot ya existe
        $existe = DB::table('ingreso_movimiento')
            ->where('ingreso_id', $ingreso->id)
            ->where('movimiento_id', $movId)
            ->exists();

        if ($existe) return 'ya_existia';

        if (!$dry) {
            DB::table('ingreso_movimiento')->insert([
                'ingreso_id'    => $ingreso->id,
                'movimiento_id' => $movId,
                'monto'         => $monto,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        return 'vinculadas';
    }

    /**
     * Normaliza el RUT al formato estándar XX.XXX.XXX-X → XXXXXXXX-X (sin puntos).
     */
    private function normalizarRut(string $rut): string
    {
        return str_replace('.', '', trim($rut));
    }
}
