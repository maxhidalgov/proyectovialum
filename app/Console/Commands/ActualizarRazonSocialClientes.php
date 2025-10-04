<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cliente;

class ActualizarRazonSocialClientes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clientes:actualizar-razon-social';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza razon_social de clientes que solo tienen first_name y last_name';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Actualizando razón social de clientes...');
        
        // Buscar clientes sin razon_social pero con nombres
        $clientes = Cliente::where(function($query) {
            $query->whereNull('razon_social')
                  ->orWhere('razon_social', '')
                  ->orWhere('razon_social', 'Sin nombre');
        })
        ->where(function($query) {
            $query->whereNotNull('first_name')
                  ->orWhereNotNull('last_name');
        })
        ->get();

        $actualizados = 0;

        foreach ($clientes as $cliente) {
            $razonSocial = trim(($cliente->first_name ?? '') . ' ' . ($cliente->last_name ?? ''));
            
            if (!empty($razonSocial)) {
                $cliente->razon_social = $razonSocial;
                $cliente->save();
                $actualizados++;
                $this->info("✅ Cliente #{$cliente->id}: {$razonSocial}");
            }
        }

        $this->newLine();
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("✅ Actualización completada");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📊 Total actualizados: $actualizados");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        return Command::SUCCESS;
    }
}
