<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Endpoints máquina-a-máquina para el bot de WhatsApp.
 * Protegidos por un token estático (config services.bot.token), sin login JWT,
 * igual que el endpoint de cron. Devuelven texto listo para postear en el grupo.
 */
class BotController extends Controller
{
    // Verifica el token del bot; aborta con 403 si no calza.
    private function verificarToken(Request $r): void
    {
        $token = config('services.bot.token');
        if (empty($token) || !hash_equals($token, (string) $r->query('token'))) {
            abort(403, 'Token inválido');
        }
    }

    /**
     * GET /api/bot/ausentes-hoy?token=XXXX[&fecha=YYYY-MM-DD&tolerancia=5]
     * Devuelve quién no marcó asistencia hoy (y quién llegó atrasado),
     * reutilizando el reporte de AsistenciaController::diario (Workera).
     */
    public function ausentesHoy(Request $r)
    {
        $this->verificarToken($r);

        // La app corre en UTC: "hoy" debe ser el día en Chile, no el de UTC
        // (pasadas las 21:00 hora chilena UTC ya cambió de día).
        $fecha      = $r->query('fecha', now('America/Santiago')->toDateString());
        $tolerancia = (int) $r->query('tolerancia', 5);

        // Reutiliza el cálculo existente (horarios + marcaciones + permisos + feriados).
        $data = app(AsistenciaController::class)
            ->diario(new Request(['fecha' => $fecha, 'tolerancia' => $tolerancia]))
            ->getData(true);

        // Workera sin configurar u otro error → pasa el mensaje tal cual.
        if (isset($data['error'])) {
            return response()->json(['ok' => false, 'error' => $data['error']], 422);
        }

        $dias     = $data['dias'] ?? [];
        $ausentes = array_values(array_filter($dias, fn ($d) => ($d['estado'] ?? '') === 'Ausente'));
        $atrasos  = array_values(array_filter($dias, fn ($d) => ($d['estado'] ?? '') === 'Atraso'));

        return response()->json([
            'ok'       => true,
            'fecha'    => $fecha,
            'texto'    => $this->formatearTexto($fecha, $dias, $ausentes, $atrasos),
            'ausentes' => array_map(fn ($d) => ['nombre' => $d['nombre'], 'turno' => $d['turno'] ?? null], $ausentes),
            'atrasos'  => array_map(fn ($d) => [
                'nombre'     => $d['nombre'],
                'esperada'   => $d['esperada'] ?? null,
                'real'       => $d['real'] ?? null,
                'atraso_min' => $d['atraso_min'] ?? 0,
            ], $atrasos),
        ]);
    }

    // Arma el mensaje para WhatsApp (formato *negrita* con asteriscos simples).
    private function formatearTexto(string $fecha, array $dias, array $ausentes, array $atrasos): string
    {
        $dia = \Carbon\Carbon::parse($fecha)->locale('es')->isoFormat('dddd D [de] MMMM');

        // Sin jornada asignada hoy (domingo, feriado, o sin horarios cargados).
        if (empty($dias)) {
            return "📋 *Asistencia — {$dia}*\nHoy no hay jornada con horario asignado.";
        }

        $partes = ["📋 *Asistencia — {$dia}*"];

        if (empty($ausentes)) {
            $partes[] = '✅ Todos marcaron asistencia.';
        } else {
            $n = count($ausentes);
            $partes[] = '';
            $partes[] = "⚠️ *No marcaron ({$n}):*";
            foreach ($ausentes as $d) {
                $partes[] = "• {$d['nombre']}";
            }
        }

        if (!empty($atrasos)) {
            $partes[] = '';
            $partes[] = '🕐 *Atrasos:*';
            foreach ($atrasos as $d) {
                $partes[] = "• {$d['nombre']} — marcó {$d['real']} (esperado {$d['esperada']}, +{$d['atraso_min']} min)";
            }
        }

        return implode("\n", $partes);
    }
}
