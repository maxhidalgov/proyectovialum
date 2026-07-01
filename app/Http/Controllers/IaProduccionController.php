<?php

namespace App\Http\Controllers;

use App\Models\EtapaProduccion;
use App\Models\IaMensaje;
use App\Models\IncidenteProduccion;
use App\Services\IaProduccionService;
use App\Services\WorkeraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IaProduccionController extends Controller
{
    public function __construct(
        private IaProduccionService $ia,
        private WorkeraService $workera,
    ) {}

    // ── Chat ──────────────────────────────────────────────────────────────────

    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'mensaje' => 'required|string|max:2000',
        ]);

        $resultado = $this->ia->chat($request->mensaje, 'app');

        return response()->json($resultado);
    }

    // ── Historial ─────────────────────────────────────────────────────────────

    public function historial(Request $request): JsonResponse
    {
        $mensajes = IaMensaje::orderBy('created_at', 'desc')
            ->limit($request->integer('limit', 50))
            ->get()
            ->reverse()
            ->values();

        return response()->json($mensajes);
    }

    // ── Contexto / Dashboard ──────────────────────────────────────────────────

    public function contexto(): JsonResponse
    {
        $incidentesAbiertos = IncidenteProduccion::with(['cotizacion.cliente', 'empleadoResponsable'])
            ->whereIn('estado', ['abierto', 'en_resolucion'])
            ->orderBy('created_at', 'desc')
            ->get();

        $etapasRecientes = EtapaProduccion::with(['cotizacion.cliente', 'empleado'])
            ->whereIn('estado', ['en_progreso'])
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json([
            'incidentes_abiertos' => $incidentesAbiertos,
            'etapas_en_progreso'  => $etapasRecientes,
        ]);
    }

    // ── Workera ───────────────────────────────────────────────────────────────

    public function syncWorkera(): JsonResponse
    {
        $resultado = $this->workera->syncEmpleados();

        return response()->json([
            'ok' => true,
            ...$resultado,
        ]);
    }

    public function asistenciaHoy(): JsonResponse
    {
        $resultado = $this->workera->analizarAsistenciaHoy();

        return response()->json($resultado);
    }
}
