<?php

namespace App\Http\Controllers;

use App\Models\Abono;
use App\Models\Cotizacion;
use Illuminate\Http\Request;

class OperacionesController extends Controller
{
    // Cotizaciones aprobadas/en producción/entregadas con datos de operaciones
    public function index()
    {
        $cotizaciones = Cotizacion::with(['cliente', 'vendedor', 'estado', 'abonos', 'ventanas'])
            ->whereHas('estado', fn($q) => $q->whereNotIn('nombre', [
                'Evaluación', 'Rechazada', 'Anulada',
            ]))
            ->latest()
            ->get();

        $items = $cotizaciones->map(function ($c) {
            $totalAbonado    = $c->abonos->sum('monto');
            $cantVentanas    = $c->ventanas->sum('cantidad');
            $m2              = $c->ventanas->sum(fn($v) =>
                ($v->ancho / 1000) * ($v->alto / 1000) * ($v->cantidad ?? 1)
            );

            return [
                'id'                 => $c->id,
                'cliente'            => $c->cliente?->razon_social
                                    ?? trim(($c->cliente?->first_name ?? '') . ' ' . ($c->cliente?->last_name ?? '')),
                'vendedor'           => $c->vendedor?->name,
                'fecha'              => $c->fecha,
                'estado'             => $c->estado?->nombre,
                'total'              => (float) $c->total,
                'total_abonado'      => (float) $totalAbonado,
                'saldo'              => (float) ($c->total - $totalAbonado),
                'pedido_proveedor'   => (bool) $c->pedido_proveedor,
                'estado_produccion'  => $c->estado_produccion,
                'fecha_entrega'      => $c->fecha_entrega,
                'notas_operaciones'  => $c->notas_operaciones,
                'cant_ventanas'      => (int) $cantVentanas,
                'm2'                 => round($m2, 2),
                'abonos'             => $c->abonos->map(fn($a) => [
                    'id'    => $a->id,
                    'monto' => (float) $a->monto,
                    'fecha' => $a->fecha,
                    'nota'  => $a->nota,
                ]),
            ];
        });

        // Totales globales para las stat cards
        $stats = [
            'total_cotizaciones' => $items->count(),
            'total_ventanas'     => $items->sum('cant_ventanas'),
            'total_m2'           => round($items->sum('m2'), 2),
            'total_facturado'    => $items->sum('total'),
            'total_abonado'      => $items->sum('total_abonado'),
            'total_saldo'        => $items->sum('saldo'),
        ];

        return response()->json([
            'cotizaciones' => $items,
            'stats'        => $stats,
        ]);
    }

    // Actualizar campos de operaciones inline
    public function update(Request $request, $id)
    {
        $request->validate([
            'pedido_proveedor'  => 'sometimes|boolean',
            'estado_produccion' => 'sometimes|nullable|in:En Espera de Medidas,Lista para Corte,En Fabricación,Fabricadas OK,Instalada',
            'fecha_entrega'     => 'sometimes|nullable|date',
            'notas_operaciones' => 'sometimes|nullable|string|max:1000',
        ]);

        $cotizacion = Cotizacion::findOrFail($id);
        $cotizacion->update($request->only([
            'pedido_proveedor', 'estado_produccion', 'fecha_entrega', 'notas_operaciones',
        ]));

        return response()->json(['success' => true]);
    }

    // Agregar abono
    public function storeAbono(Request $request, $id)
    {
        $request->validate([
            'monto' => 'required|numeric|min:1',
            'fecha' => 'required|date',
            'nota'  => 'nullable|string|max:255',
        ]);

        $cotizacion = Cotizacion::findOrFail($id);
        $abono = $cotizacion->abonos()->create($request->only(['monto', 'fecha', 'nota']));

        return response()->json([
            'id'    => $abono->id,
            'monto' => (float) $abono->monto,
            'fecha' => $abono->fecha,
            'nota'  => $abono->nota,
        ], 201);
    }

    // Eliminar abono
    public function destroyAbono($abonoId)
    {
        Abono::findOrFail($abonoId)->delete();
        return response()->json(['success' => true]);
    }
}
