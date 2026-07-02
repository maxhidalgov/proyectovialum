<?php

namespace App\Console\Commands;

use App\Services\IaProduccionService;
use Illuminate\Console\Command;

class WorkeraTest extends Command
{
    protected $signature   = 'workera:test {mensaje?}';
    protected $description = 'Prueba el chat IA con un mensaje';

    public function handle(IaProduccionService $ia): int
    {
        $mensaje = $this->argument('mensaje') ?? 'a que hora marcó Gerson hoy?';
        $this->info("Enviando: {$mensaje}");

        $r = $ia->chat($mensaje);
        $this->line('');
        $this->line($r['respuesta']);

        return 0;
    }
}
