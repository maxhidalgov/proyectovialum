<?php

namespace App\Http\Controllers;

use App\Services\WorkeraService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Reportes de asistencia (en vivo desde Workera).
 * Calcula atrasos comparando la marcación de entrada real contra el
 * horario asignado (workshift/schedules), respetando permisos/licencias.
 */
class AsistenciaController extends Controller
{
    public function __construct(private WorkeraService $workera) {}

    // GET /api/asistencia/diario?fecha=YYYY-MM-DD&tolerancia=5
    public function diario(Request $request)
    {
        if (!$this->workera->configurado()) {
            return response()->json(['error' => 'Falta configurar API_USER / API_KEY de Workera.'], 422);
        }

        $fecha      = $request->get('fecha', now()->toDateString());
        $tolerancia = (int) $request->get('tolerancia', 5);

        $rep = $this->construirReporte($fecha, $fecha, $tolerancia);

        // Resumen del día
        $dias = $rep['dias'];
        $resumenDia = [
            'fecha'      => $fecha,
            'con_horario'=> count($dias),
            'a_tiempo'   => collect($dias)->where('estado', 'A tiempo')->count(),
            'atrasos'    => collect($dias)->where('estado', 'Atraso')->count(),
            'ausentes'   => collect($dias)->where('estado', 'Ausente')->count(),
            'permisos'   => collect($dias)->where('estado', 'Permiso')->count(),
            'min_atraso' => collect($dias)->where('estado', 'Atraso')->sum('atraso_min'),
        ];

        return response()->json([
            'fecha'      => $fecha,
            'tolerancia' => $tolerancia,
            'resumen'    => $resumenDia,
            'dias'       => $dias,
        ]);
    }

    // GET /api/asistencia/semanal?desde=YYYY-MM-DD&hasta=YYYY-MM-DD&tolerancia=5
    public function semanal(Request $request)
    {
        if (!$this->workera->configurado()) {
            return response()->json(['error' => 'Falta configurar API_USER / API_KEY de Workera.'], 422);
        }

        $desde = $request->get('desde', now()->startOfWeek()->toDateString());
        $hasta = $request->get('hasta', now()->endOfWeek()->toDateString());
        $tolerancia = (int) $request->get('tolerancia', 5);

        // Workera limita schedules a 60 días
        if (Carbon::parse($desde)->diffInDays(Carbon::parse($hasta)) > 60) {
            return response()->json(['error' => 'El rango no puede superar los 60 días.'], 422);
        }

        $rep = $this->construirReporte($desde, $hasta, $tolerancia);

        return response()->json([
            'desde'      => $desde,
            'hasta'      => $hasta,
            'tolerancia' => $tolerancia,
            'resumen'    => array_values($rep['resumen']),
            'dias'       => $rep['dias'],
        ]);
    }

    /**
     * Construye el reporte cruzando horarios asignados, marcaciones y permisos.
     * @return array{dias: array, resumen: array}
     */
    private function construirReporte(string $desde, string $hasta, int $tolerancia): array
    {
        $schedules   = $this->workera->getSchedules($desde, $hasta);
        $attendance  = $this->workera->getAsistencia($desde, $hasta)['data'] ?? [];
        $permisos    = $this->workera->getPermisosRango($desde, $hasta);

        // Índice de entradas reales: primera marcación ACTIVA de tipo Entrada (0) por code+fecha
        $entradas = []; // [code][fecha] => 'YYYY-MM-DDTHH:mm:ss'
        foreach ($attendance as $r) {
            $code = (string) ($r['employee']['code'] ?? '');
            if ($code === '' || (int) ($r['attendanceType'] ?? -1) !== 0) continue;
            if (strtoupper($r['attendanceStatus'] ?? 'ACTIVO') === 'INACTIVO') continue;
            $fecha = substr((string) ($r['attendanceDate'] ?? ''), 0, 10);
            if (!$fecha) continue;
            if (!isset($entradas[$code][$fecha]) || $r['attendanceDate'] < $entradas[$code][$fecha]) {
                $entradas[$code][$fecha] = $r['attendanceDate'];
            }
        }

        // Índice de permisos por code => lista de [desde, hasta, nombre]
        $permByCode = [];
        foreach ($permisos as $p) {
            $code = (string) ($p['employee']['code'] ?? '');
            if ($code === '') continue;
            $permByCode[$code][] = [
                'desde'  => substr((string) ($p['start'] ?? ''), 0, 10),
                'hasta'  => substr((string) ($p['end'] ?? ''), 0, 10),
                'nombre' => $p['permissionName'] ?? 'Permiso',
            ];
        }

        $dias = [];
        $resumen = [];

        foreach ($schedules as $emp) {
            $e    = $emp['employee'] ?? [];
            $code = (string) ($e['code'] ?? '');
            $nombre = trim(($e['name'] ?? '') . ' ' . ($e['lastName'] ?? '')) ?: ('#' . $code);
            $sucursal = $e['branchOffice'] ?? '';
            $departamento = $e['department'] ?? '';

            if (!isset($resumen[$code])) {
                $resumen[$code] = [
                    'code' => $code, 'nombre' => $nombre,
                    'sucursal' => $sucursal, 'departamento' => $departamento,
                    'dias_horario' => 0, 'a_tiempo' => 0, 'atrasos' => 0,
                    'ausentes' => 0, 'permisos' => 0, 'min_atraso' => 0,
                ];
            }

            foreach (($emp['schedules'] ?? []) as $sch) {
                $fecha = $sch['date'] ?? substr((string) ($sch['start'] ?? ''), 0, 10);
                if (!$fecha) continue;

                $esperada = Carbon::parse($sch['start']);
                $realStr  = $entradas[$code][$fecha] ?? null;

                // ¿Permiso cubre el día?
                $permiso = null;
                foreach ($permByCode[$code] ?? [] as $pp) {
                    if ($fecha >= $pp['desde'] && $fecha <= $pp['hasta']) { $permiso = $pp['nombre']; break; }
                }

                $atrasoMin = 0;
                $realFmt   = null;

                if ($permiso !== null) {
                    $estado = 'Permiso';
                    $resumen[$code]['permisos']++;
                } elseif ($realStr === null) {
                    $estado = 'Ausente';
                    $resumen[$code]['ausentes']++;
                } else {
                    $real = Carbon::parse($realStr);
                    $realFmt = $real->format('H:i');
                    $atrasoMin = (int) round(($real->getTimestamp() - $esperada->getTimestamp()) / 60);
                    if ($atrasoMin > $tolerancia) {
                        $estado = 'Atraso';
                        $resumen[$code]['atrasos']++;
                        $resumen[$code]['min_atraso'] += $atrasoMin;
                    } else {
                        $estado = 'A tiempo';
                        $resumen[$code]['a_tiempo']++;
                    }
                }

                $resumen[$code]['dias_horario']++;

                $dias[] = [
                    'code'         => $code,
                    'nombre'       => $nombre,
                    'sucursal'     => $sucursal,
                    'departamento' => $departamento,
                    'fecha'        => $fecha,
                    'turno'        => $sch['workshiftName'] ?? ($sch['scheduleName'] ?? ''),
                    'esperada'     => $esperada->format('H:i'),
                    'real'         => $realFmt,
                    'atraso_min'   => $atrasoMin > 0 ? $atrasoMin : 0,
                    'estado'       => $estado,
                    'permiso'      => $permiso,
                ];
            }
        }

        // Ordenar filas por fecha y luego nombre
        usort($dias, fn ($a, $b) => [$a['fecha'], $a['nombre']] <=> [$b['fecha'], $b['nombre']]);

        return ['dias' => $dias, 'resumen' => $resumen];
    }
}
