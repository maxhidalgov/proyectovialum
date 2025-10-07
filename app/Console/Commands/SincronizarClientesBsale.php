<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cliente;
use App\Services\BsaleClientService;
use Illuminate\Support\Facades\Log;

class SincronizarClientesBsale extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bsale:sincronizar-clientes {--limit= : Número máximo de clientes a sincronizar (dejar vacío para todos)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza todos los clientes de Bsale con la base de datos local';

    protected $bsaleService;

    public function __construct(BsaleClientService $bsaleService)
    {
        parent::__construct();
        $this->bsaleService = $bsaleService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Iniciando sincronización de clientes de Bsale...');
        
        $limit = $this->option('limit') ? (int)$this->option('limit') : PHP_INT_MAX;
        $offset = 0;
        $pageSize = 50;
        $totalProcesados = 0;
        $totalCreados = 0;
        $totalActualizados = 0;
        $errores = 0;

        try {
            do {
                $this->info("📥 Obteniendo clientes (offset: $offset)...");
                
                // Obtener clientes de Bsale
                $resultado = $this->bsaleService->getClients($pageSize, $offset);
                
                if (!$resultado || !isset($resultado['items']) || empty($resultado['items'])) {
                    $this->info('✅ No hay más clientes para sincronizar');
                    break;
                }

                $clientes = $resultado['items'];
                $this->info("   Procesando " . count($clientes) . " clientes...");

                foreach ($clientes as $clienteBsale) {
                    try {
                        // Preparar datos del cliente
                        $razonSocial = $clienteBsale['company'] ?? 
                                      trim(($clienteBsale['firstName'] ?? '') . ' ' . ($clienteBsale['lastName'] ?? ''));
                        
                        if (empty($razonSocial)) {
                            $razonSocial = 'Sin nombre';
                        }

                        $datosCliente = [
                            'bsale_id' => $clienteBsale['id'],
                            'razon_social' => $razonSocial,
                            'identification' => $clienteBsale['identification'] ?? null,  // ✅ CORREGIDO: usar 'identification' no 'code'
                            'email' => $clienteBsale['email'] ?? null,
                            'phone' => $clienteBsale['phone'] ?? null,
                            'address' => $clienteBsale['address'] ?? null,
                            'ciudad' => $clienteBsale['city'] ?? null,
                            'comuna' => $clienteBsale['municipality'] ?? null,
                            'first_name' => $clienteBsale['firstName'] ?? null,
                            'last_name' => $clienteBsale['lastName'] ?? null,
                            'tipo_cliente' => ($clienteBsale['companyOrPerson'] ?? 0) == 1 ? 'empresa' : 'persona',
                            'giro' => $clienteBsale['giro'] ?? null,
                        ];

                        // Buscar si el cliente ya existe por bsale_id O por RUT
                        $clienteExistente = Cliente::where('bsale_id', $clienteBsale['id'])
                            ->orWhere(function($query) use ($clienteBsale) {
                                if (!empty($clienteBsale['identification'])) {
                                    $query->where('identification', $clienteBsale['identification']);
                                }
                            })
                            ->first();

                        if ($clienteExistente) {
                            // Actualizar cliente existente (incluyendo el bsale_id si no lo tenía)
                            $clienteExistente->update($datosCliente);
                            $totalActualizados++;
                            
                            if (empty($clienteExistente->bsale_id)) {
                                $this->line("   ✅ Cliente actualizado con bsale_id: {$datosCliente['razon_social']} (RUT: {$datosCliente['identification']})");
                            }
                        } else {
                            // Crear nuevo cliente
                            Cliente::create($datosCliente);
                            $totalCreados++;
                        }

                        $totalProcesados++;

                    } catch (\Exception $e) {
                        $errores++;
                        Log::error('Error sincronizando cliente Bsale ID ' . ($clienteBsale['id'] ?? 'unknown'), [
                            'error' => $e->getMessage(),
                            'cliente' => $clienteBsale
                        ]);
                        $this->error("   ❌ Error con cliente ID " . ($clienteBsale['id'] ?? 'unknown') . ": " . $e->getMessage());
                    }
                }

                $offset += $pageSize;

                // Mostrar progreso
                $this->info("   ✅ Procesados: $totalProcesados | Creados: $totalCreados | Actualizados: $totalActualizados | Errores: $errores");

            } while ($totalProcesados < $limit && count($clientes) == $pageSize);

            // Resumen final
            $this->newLine();
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('✅ SINCRONIZACIÓN COMPLETADA');
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info("📊 Total procesados: $totalProcesados");
            $this->info("➕ Nuevos clientes: $totalCreados");
            $this->info("🔄 Actualizados: $totalActualizados");
            $this->info("❌ Errores: $errores");
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            Log::info('Sincronización de clientes Bsale completada', [
                'total_procesados' => $totalProcesados,
                'nuevos' => $totalCreados,
                'actualizados' => $totalActualizados,
                'errores' => $errores
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error general en la sincronización: ' . $e->getMessage());
            Log::error('Error en sincronización de clientes Bsale', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
}
