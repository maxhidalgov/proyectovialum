<?php

namespace App\Services;

use App\Models\AusenciaEmpleado;
use App\Models\Empleado;
use App\Models\HorasExtra;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente para la API de Workera (reloj control de asistencia).
 *
 * Configura en .env:
 *   WORKERA_API_USER=tu_usuario
 *   WORKERA_API_KEY=tu_clave
 */
class WorkeraService
{
    private string $baseUrl = 'https://workera.com/apiClient/v1';

    private function headers(): array
    {
        return [
            'API_USER'     => config('services.workera.api_user'),
            'API_KEY'      => config('services.workera.api_key'),
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];
    }

    private function get(string $endpoint, array $params = []): array
    {
        $response = Http::withHeaders($this->headers())
            ->timeout(15)
            ->get("{$this->baseUrl}/{$endpoint}", $params);

        if ($response->failed()) {
            Log::error("WorkeraService GET /{$endpoint} falló", [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return [];
        }

        return $response->json() ?? [];
    }

    private function post(string $endpoint, array $data): array
    {
        $response = Http::withHeaders($this->headers())
            ->timeout(15)
            ->post("{$this->baseUrl}/{$endpoint}", $data);

        if ($response->failed()) {
            Log::error("WorkeraService POST /{$endpoint} falló", [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return [];
        }

        return $response->json() ?? [];
    }

    // ── Empleados ─────────────────────────────────────────────────────────────

    public function getEmpleados(int $page = 1): array
    {
        return $this->get('employee', ['page' => $page]);
    }

    /**
     * Sincroniza empleados de Workera a la tabla local.
     * Usa RUT como clave de cruce. Actualiza workera_code y telefono.
     */
    public function syncEmpleados(): array
    {
        $synced  = 0;
        $skipped = 0;
        $page    = 1;

        do {
            $data      = $this->getEmpleados($page);
            $empleados = $data['data'] ?? [];
            $totalPages = (int) ($data['totalPages'] ?? 1);

            if (empty($empleados)) {
                break;
            }

            foreach ($empleados as $w) {
                $rutWorkera = $this->limpiarRut($w['identification'] ?? '');

                if (!$rutWorkera) {
                    $skipped++;
                    continue;
                }

                // Buscar por RUT limpio (sin puntos ni guión)
                $local = Empleado::all()->first(function ($e) use ($rutWorkera) {
                    return $this->limpiarRut($e->rut ?? '') === $rutWorkera;
                });

                if (!$local) {
                    $skipped++;
                    continue;
                }

                $local->update([
                    'workera_code' => (string) ($w['code'] ?? ''),
                    'telefono'     => $w['phone'] ?? null,
                ]);

                $synced++;
            }

            $page++;
        } while ($page <= $totalPages);

        return ['synced' => $synced, 'skipped' => $skipped];
    }

    // ── Asistencia ────────────────────────────────────────────────────────────

    /**
     * Retorna marcaciones del día indicado.
     * date: 'Y-m-d'
     */
    public function getAsistencia(string $date, ?string $endDate = null): array
    {
        $end   = $endDate ?? $date;
        $todos = [];
        $page  = 1;

        do {
            $resp      = $this->get('attendanceData', ['start' => $date, 'end' => $end, 'page' => $page]);
            $registros = $resp['data'] ?? [];
            $todos     = array_merge($todos, $registros);
            $totalPages = (int) ($resp['totalPages'] ?? 1);
            $page++;
        } while ($page <= $totalPages && !empty($registros));

        return ['data' => $todos];
    }

    /**
     * Lee asistencia del día y detecta ausentes e impuntuales.
     *
     * Retorna:
     *   ausentes    → empleados sin marcación de entrada
     *   tarde       → llegaron después de 15 min del inicio del turno (08:00)
     *   presentes   → marcaron dentro del margen
     */
    public function analizarAsistenciaHoy(): array
    {
        $hoy  = now()->toDateString();
        $data = $this->getAsistencia($hoy);

        // Indexar primera marcación de ENTRADA (attendanceType=0) por employee.code
        $entradas = [];
        foreach ($data['data'] ?? $data as $r) {
            $code = (string) ($r['employee']['code'] ?? '');
            if (!$code || ($r['attendanceType'] ?? -1) !== 0) {
                continue;
            }
            if (!isset($entradas[$code])) {
                $entradas[$code] = $r['attendanceDate'];
            }
        }

        $ausentes  = [];
        $tarde     = [];
        $presentes = [];

        $empleados = Empleado::whereNotNull('workera_code')->where('activo', true)->get();

        foreach ($empleados as $emp) {
            $code = (string) $emp->workera_code;

            if (!isset($entradas[$code])) {
                $ausentes[] = ['empleado_id' => $emp->id, 'nombre' => $emp->nombre];
                continue;
            }

            $horaEntrada = Carbon::parse($entradas[$code]);
            $horaInicio  = config('services.workera.turno_inicio', '08:00');
            $turnoInicio = Carbon::parse($hoy . ' ' . $horaInicio . ':00');
            $minutosTarde = $horaEntrada->diffInMinutes($turnoInicio, false);

            // diffInMinutes(false): negativo si $horaEntrada > $turnoInicio (llegó tarde)
            if ($minutosTarde < -15) {
                $tarde[] = [
                    'empleado_id'   => $emp->id,
                    'nombre'        => $emp->nombre,
                    'hora_entrada'  => $horaEntrada->format('H:i'),
                    'minutos_tarde' => (int) abs($minutosTarde),
                ];
                continue;
            }

            $presentes[] = [
                'empleado_id'  => $emp->id,
                'nombre'       => $emp->nombre,
                'hora_entrada' => $horaEntrada->format('H:i'),
            ];
        }

        return compact('ausentes', 'tarde', 'presentes');
    }

    // ── Horas extra ───────────────────────────────────────────────────────────

    public function getHorasExtra(string $startDate, string $endDate): array
    {
        return $this->get('overtimeAuthorization', [
            'start' => $startDate,
            'end'   => $endDate,
            'page'  => 1,
        ]);
    }

    /**
     * Registra autorización de horas extra en Workera.
     */
    public function autorizarHorasExtra(string $workeraCode, string $fecha, float $horas, string $motivo = ''): array
    {
        return $this->post('overtimeAuthorization', [
            'employeeCode' => $workeraCode,
            'date'         => $fecha,
            'hours'        => $horas,
            'reason'       => $motivo,
        ]);
    }

    // ── Permisos / Ausencias ──────────────────────────────────────────────────

    public function getPermisos(string $startDate, string $endDate): array
    {
        return $this->get('permission', [
            'startDate' => $startDate,
            'endDate'   => $endDate,
        ]);
    }

    /**
     * Registra una ausencia/permiso en Workera y la guarda localmente.
     */
    public function registrarPermiso(int $empleadoId, string $fecha, string $tipo, string $motivo = ''): array
    {
        $empleado = Empleado::find($empleadoId);

        if (!$empleado || !$empleado->workera_code) {
            return ['error' => 'Empleado sin workera_code configurado'];
        }

        $tipoWorkera = match ($tipo) {
            'dia_completo'  => 'FULL_DAY',
            'media_manana'  => 'HALF_DAY_AM',
            'media_tarde'   => 'HALF_DAY_PM',
            'llegada_tarde' => 'LATE_ARRIVAL',
            default         => 'FULL_DAY',
        };

        $respuesta = $this->post('permission', [
            'employeeCode' => $empleado->workera_code,
            'date'         => $fecha,
            'type'         => $tipoWorkera,
            'reason'       => $motivo,
        ]);

        $workeraId = $respuesta['id'] ?? $respuesta['data']['id'] ?? null;

        AusenciaEmpleado::updateOrCreate(
            ['empleado_id' => $empleadoId, 'fecha' => $fecha, 'tipo' => $tipo],
            ['motivo' => $motivo, 'workera_permission_id' => $workeraId]
        );

        return ['ok' => true, 'workera_id' => $workeraId];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function limpiarRut(string $rut): string
    {
        return preg_replace('/[^0-9kK]/', '', strtolower($rut));
    }
}
