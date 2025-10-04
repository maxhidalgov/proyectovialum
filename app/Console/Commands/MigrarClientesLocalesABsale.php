<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cliente;
use App\Models\Cotizacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrarClientesLocalesABsale extends Command
{
    protected $signature = 'clientes:migrar-a-bsale {--dry-run : Solo mostrar lo que se haría sin hacer cambios}';
    protected $description = 'Migra clientes locales a sus equivalentes de Bsale y elimina duplicados';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->warn('🔍 MODO DRY-RUN: No se harán cambios permanentes');
        }
        
        $this->info('🔄 Iniciando migración de clientes locales a Bsale...');
        $this->newLine();
        
        // Paso 1: Obtener clientes locales (sin bsale_id)
        $clientesLocales = Cliente::whereNull('bsale_id')->get();
        $this->info("📊 Clientes locales encontrados: {$clientesLocales->count()}");
        
        $migrados = 0;
        $eliminados = 0;
        $noEncontrados = 0;
        
        DB::beginTransaction();
        
        try {
            foreach ($clientesLocales as $clienteLocal) {
                // Buscar si existe en Bsale por RUT
                $clienteBsale = null;
                
                if ($clienteLocal->identification) {
                    $clienteBsale = Cliente::where('identification', $clienteLocal->identification)
                        ->whereNotNull('bsale_id')
                        ->first();
                }
                
                // Verificar si este cliente local está siendo usado en cotizaciones
                $cotizaciones = Cotizacion::where('cliente_id', $clienteLocal->id)->get();
                
                if ($clienteBsale) {
                    // Existe en Bsale - migrar cotizaciones
                    if ($cotizaciones->count() > 0) {
                        $this->info("✅ Cliente local #{$clienteLocal->id} ({$clienteLocal->razon_social}) → Bsale #{$clienteBsale->id}");
                        $this->info("   Migrando {$cotizaciones->count()} cotización(es)...");
                        
                        if (!$dryRun) {
                            foreach ($cotizaciones as $cot) {
                                $cot->cliente_id = $clienteBsale->id;
                                $cot->save();
                            }
                        }
                        $migrados++;
                    }
                    
                    // Eliminar cliente local duplicado
                    if (!$dryRun) {
                        $clienteLocal->delete();
                    }
                    $eliminados++;
                    
                } else {
                    // NO existe en Bsale
                    if ($cotizaciones->count() > 0) {
                        $this->warn("⚠️ Cliente local #{$clienteLocal->id} ({$clienteLocal->razon_social} - RUT: {$clienteLocal->identification})");
                        $this->warn("   NO existe en Bsale pero tiene {$cotizaciones->count()} cotización(es)");
                        $this->warn("   Se mantiene como cliente local");
                        $noEncontrados++;
                    } else {
                        // No tiene cotizaciones y no está en Bsale - eliminar
                        if (!$dryRun) {
                            $clienteLocal->delete();
                        }
                        $eliminados++;
                    }
                }
            }
            
            if ($dryRun) {
                DB::rollBack();
                $this->newLine();
                $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                $this->info('🔍 DRY-RUN COMPLETADO (sin cambios)');
                $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            } else {
                DB::commit();
                $this->newLine();
                $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                $this->info('✅ MIGRACIÓN COMPLETADA');
                $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            }
            
            $this->info("📊 Clientes migrados: {$migrados}");
            $this->info("🗑️ Clientes eliminados: {$eliminados}");
            $this->info("⚠️ Clientes sin equivalente en Bsale: {$noEncontrados}");
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            
            if ($noEncontrados > 0 && !$dryRun) {
                $this->newLine();
                $this->warn('⚠️ IMPORTANTE: Hay clientes locales que no existen en Bsale');
                $this->warn('   Deberías crearlos en Bsale manualmente o eliminar sus cotizaciones');
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Error en la migración: ' . $e->getMessage());
            Log::error('Error migrando clientes locales', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
}
