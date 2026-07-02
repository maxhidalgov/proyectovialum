<?php

namespace App\Console\Commands;

use App\Services\IaProduccionService;
use App\Services\WorkeraService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Ejecutar diariamente vía scheduler para inyectar el resumen
 * de asistencia Workera en el chat IA de producción.
 *
 * Registro en app/Console/Kernel.php (o routes/console.php):
 *   $schedule->command('workera:check-diario')->weekdays()->at('09:30');
 */
class WorkeraCheckDiario extends Command
{
    protected $signature   = 'workera:check-diario {--dry-run : Solo muestra el mensaje sin enviarlo al chat}';
    protected $description = 'Lee asistencia de Workera e inyecta el reporte en el chat IA';

    public function handle(WorkeraService $workera, IaProduccionService $ia): int
    {
        $hoy = Carbon::now()->locale('es')->isoFormat('dddd D [de] MMMM');

        $this->info("Workera check diario — {$hoy}");

        try {
            $asistencia = $workera->analizarAsistenciaHoy();
        } catch (\Throwable $e) {
            Log::error('workera:check-diario falló al obtener asistencia', ['error' => $e->getMessage()]);
            $this->error("Error al conectar con Workera: {$e->getMessage()}");
            return 1;
        }

        $mensaje = $this->construirMensaje($asistencia, $hoy);
        $this->line('');
        $this->line($mensaje);

        if ($this->option('dry-run')) {
            $this->info('[dry-run] Mensaje NO enviado al chat.');
            return 0;
        }

        try {
            $resultado = $ia->chat($mensaje, 'workera');
            $this->line('');
            $this->info('IA respondió: ' . ($resultado['respuesta'] ?? '(sin texto)'));
        } catch (\Throwable $e) {
            Log::error('workera:check-diario falló al enviar al chat IA', ['error' => $e->getMessage()]);
            $this->error("Error al enviar al chat IA: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }

    private function construirMensaje(array $asistencia, string $hoy): string
    {
        $lineas = ["📋 Reporte automático de asistencia — {$hoy}:"];

        $presentes = $asistencia['presentes'] ?? [];
        $tarde     = $asistencia['tarde'] ?? [];
        $ausentes  = $asistencia['ausentes'] ?? [];

        if (!empty($presentes)) {
            $detalles = array_map(
                fn($p) => "{$p['nombre']} (llegó {$p['hora_entrada']})",
                $presentes
            );
            $lineas[] = "✅ A tiempo (" . count($presentes) . "): " . implode('; ', $detalles) . ".";
        }

        if (!empty($tarde)) {
            $detalles = array_map(
                fn($t) => "{$t['nombre']} (llegó {$t['hora_entrada']}, {$t['minutos_tarde']} min tarde)",
                $tarde
            );
            $lineas[] = "⏰ Llegaron tarde (" . count($tarde) . "): " . implode('; ', $detalles) . ".";
        }

        if (!empty($ausentes)) {
            $nombres = implode(', ', array_column($ausentes, 'nombre'));
            $lineas[] = "❌ Ausentes sin registro (" . count($ausentes) . "): {$nombres}.";
        }

        if (empty($tarde) && empty($ausentes)) {
            $lineas[] = "Todo el equipo llegó a tiempo hoy.";
        } else {
            $lineas[] = "Por favor registra las ausencias y tardanzas correspondientes.";
        }

        return implode("\n", $lineas);
    }
}
