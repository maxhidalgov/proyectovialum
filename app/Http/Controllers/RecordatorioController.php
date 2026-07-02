<?php

namespace App\Http\Controllers;

use App\Models\Recordatorio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecordatorioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Recordatorio::with(['cliente', 'cotizacion', 'empleado'])
            ->orderBy('fecha')
            ->orderBy('hora');

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'titulo'        => 'required|string|max:255',
            'descripcion'   => 'nullable|string',
            'fecha'         => 'required|date',
            'hora'          => 'nullable|string',
            'tipo'          => 'required|in:llamada,reunion,tarea,pago,seguimiento,entrega,otro',
            'cotizacion_id' => 'nullable|integer|exists:cotizaciones,id',
            'cliente_id'    => 'nullable|integer|exists:clientes,id',
            'empleado_id'   => 'nullable|integer|exists:empleados,id',
        ]);

        $data['origen'] = 'app';
        $data['estado'] = 'pendiente';

        $recordatorio = Recordatorio::create($data);

        return response()->json($recordatorio->load(['cliente', 'empleado']), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $recordatorio = Recordatorio::findOrFail($id);

        $data = $request->validate([
            'titulo'        => 'sometimes|string|max:255',
            'descripcion'   => 'nullable|string',
            'fecha'         => 'sometimes|date',
            'hora'          => 'nullable|string',
            'tipo'          => 'sometimes|in:llamada,reunion,tarea,pago,seguimiento,entrega,otro',
            'estado'        => 'sometimes|in:pendiente,completado,cancelado',
            'cotizacion_id' => 'nullable|integer|exists:cotizaciones,id',
            'cliente_id'    => 'nullable|integer|exists:clientes,id',
            'empleado_id'   => 'nullable|integer|exists:empleados,id',
        ]);

        if (($data['estado'] ?? null) === 'completado') {
            $data['completado_at'] = now();
        } elseif (isset($data['estado'])) {
            $data['completado_at'] = null;
        }

        $recordatorio->update($data);

        return response()->json($recordatorio->load(['cliente', 'empleado']));
    }

    public function destroy(int $id): JsonResponse
    {
        Recordatorio::findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }
}
