<?php

namespace App\Http\Controllers;

use App\Models\OrdenCompra;
use App\Models\Proveedor;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrdenCompraController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $ordenes = OrdenCompra::with(['proveedor', 'cotizacion.cliente', 'creador'])
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $t = '%' . $request->buscar . '%';
                $q->where('numero', 'like', $t)->orWhere('proveedor_nombre', 'like', $t);
            })
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn ($o) => [
                'id'          => $o->id,
                'numero'      => $o->numero,
                'proveedor'   => $o->proveedor_nombre ?? $o->proveedor?->nombre,
                'cotizacion_id' => $o->cotizacion_id,
                'cliente'     => $o->cotizacion?->cliente?->razon_social
                                 ?? trim(($o->cotizacion?->cliente?->first_name ?? '') . ' ' . ($o->cotizacion?->cliente?->last_name ?? '')),
                'items_count' => is_array($o->items) ? count($o->items) : 0,
                'estado'      => $o->estado,
                'creador'     => $o->creador?->name,
                'fecha'       => $o->created_at?->toDateTimeString(),
            ]);

        return response()->json($ordenes);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cotizacion_id'   => 'nullable|integer|exists:cotizaciones,id',
            'proveedor_id'    => 'nullable|integer|exists:proveedors,id',
            'observaciones'   => 'nullable|string',
            'items'           => 'required|array|min:1',
            'items.*.descripcion' => 'required|string',
            'items.*.cantidad'    => 'required|numeric',
        ]);

        $proveedorNombre = null;
        if (!empty($data['proveedor_id'])) {
            $proveedorNombre = Proveedor::find($data['proveedor_id'])?->nombre;
        }

        $orden = OrdenCompra::create([
            'cotizacion_id'    => $data['cotizacion_id'] ?? null,
            'proveedor_id'     => $data['proveedor_id'] ?? null,
            'proveedor_nombre' => $proveedorNombre,
            'observaciones'    => $data['observaciones'] ?? null,
            'items'            => $data['items'],
            'estado'           => 'generada',
            'created_by'       => auth()->id(),
        ]);

        // Número correlativo basado en el id
        $orden->update(['numero' => 'OC-' . str_pad($orden->id, 5, '0', STR_PAD_LEFT)]);

        return response()->json($orden->load(['proveedor', 'cotizacion.cliente']), 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(
            OrdenCompra::with(['proveedor', 'cotizacion.cliente', 'creador'])->findOrFail($id)
        );
    }

    public function pdf(int $id)
    {
        $orden = OrdenCompra::with(['proveedor', 'cotizacion.cliente'])->findOrFail($id);

        $pdf = Pdf::loadView('ordenes-compra.pdf', ['orden' => $orden]);

        return $pdf->download("{$orden->numero}.pdf");
    }

    public function excel(int $id)
    {
        $orden = OrdenCompra::with('proveedor')->findOrFail($id);

        $filename = "{$orden->numero}.csv";
        $columnas = ['Categoría', 'Referencia', 'Descripción', 'Detalle', 'Cantidad'];

        $callback = function () use ($orden, $columnas) {
            $out = fopen('php://output', 'w');
            // BOM para que Excel abra bien los acentos
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($out, ["Orden de compra: {$orden->numero}"]);
            fputcsv($out, ['Proveedor:', $orden->proveedor_nombre ?? $orden->proveedor?->nombre ?? '—']);
            fputcsv($out, ['Fecha:', $orden->created_at?->format('d-m-Y')]);
            fputcsv($out, []);
            fputcsv($out, $columnas);

            foreach ($orden->items as $it) {
                fputcsv($out, [
                    $it['categoria']   ?? '',
                    $it['referencia']  ?? '',
                    $it['descripcion'] ?? '',
                    $it['detalle']     ?? '',
                    $it['cantidad']    ?? '',
                ]);
            }
            fclose($out);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
