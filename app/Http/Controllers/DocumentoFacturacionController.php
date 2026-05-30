<?php

namespace App\Http\Controllers;

use App\Models\Cotizacion;
use App\Models\DocumentoFacturacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentoFacturacionController extends Controller
{
    /**
     * Documentos emitidos en Bsale sin cotización asignada.
     * Se usan para vincular facturas/anticipos creados directamente en Bsale.
     */
    public function huerfanos(Request $request)
    {
        $q = DB::table('documentos_facturacion as df')
            ->leftJoin('clientes as c', 'c.id', '=', 'df.cliente_id')
            ->whereNull('df.cotizacion_id')
            ->where('df.estado', 'emitido')
            ->when($request->buscar, function ($q, $b) {
                $q->where(function ($w) use ($b) {
                    $w->where('df.numero_documento_bsale', 'like', "%{$b}%")
                      ->orWhere('df.tipo', 'like', "%{$b}%")
                      ->orWhere('df.nro_comprobante', 'like', "%{$b}%")
                      ->orWhere('df.bsale_cliente_nombre', 'like', "%{$b}%")
                      ->orWhere('df.bsale_cliente_rut', 'like', "%{$b}%")
                      ->orWhere('c.razon_social', 'like', "%{$b}%");
                });
            })
            ->orderByDesc('df.fecha_emision')
            ->limit(100)
            ->select([
                'df.id',
                'df.tipo',
                'df.monto',
                'df.neto',
                'df.porcentaje',
                'df.estado',
                'df.numero_documento_bsale',
                'df.url_pdf_bsale',
                'df.fecha_emision',
                'df.nro_comprobante',
                'df.bsale_cliente_rut',
                'df.bsale_cliente_nombre',
                DB::raw("COALESCE(c.razon_social, CONCAT(c.first_name, ' ', c.last_name)) as cliente_nombre_local"),
            ])
            ->get();

        return response()->json($q);
    }

    /**
     * Vincula un documento huérfano a una cotización.
     * Recalcula el porcentaje en base al total de la cotización.
     */
    public function vincular(Request $request, int $id)
    {
        $request->validate([
            'cotizacion_id' => 'required|exists:cotizaciones,id',
        ]);

        $doc = DocumentoFacturacion::findOrFail($id);

        if ($doc->cotizacion_id) {
            return response()->json([
                'message' => 'Este documento ya está vinculado a la cotización #' . $doc->cotizacion_id,
            ], 422);
        }

        $cotizacion = Cotizacion::findOrFail($request->cotizacion_id);

        // Recalcular porcentaje respecto al total de la cotización
        $pct = $cotizacion->total > 0
            ? round(($doc->monto / $cotizacion->total) * 100, 2)
            : 0;

        $doc->update([
            'cotizacion_id' => $cotizacion->id,
            'porcentaje'    => $pct,
        ]);

        return response()->json($doc->fresh());
    }


    // Listar documentos de una cotización
    public function index($cotizacionId)
    {
        $cotizacion = Cotizacion::findOrFail($cotizacionId);
        $docs = $cotizacion->documentosFacturacion()->orderBy('created_at')->get();

        return response()->json([
            'cotizacion_id' => $cotizacion->id,
            'total'         => (float) $cotizacion->total,
            'documentos'    => $docs,
        ]);
    }

    // Crear plan de facturación (reemplaza los pendientes existentes)
    public function store(Request $request, $cotizacionId)
    {
        $request->validate([
            'documentos'                => 'required|array|min:1|max:3',
            'documentos.*.tipo'         => 'required|in:anticipo,saldo,total',
            'documentos.*.porcentaje'   => 'required|numeric|min:1|max:100',
            'documentos.*.nota'         => 'nullable|string|max:255',
        ]);

        $cotizacion = Cotizacion::findOrFail($cotizacionId);

        // Validar que los porcentajes sumen 100
        $totalPct = collect($request->documentos)->sum('porcentaje');
        if (abs($totalPct - 100) > 0.01) {
            return response()->json([
                'message' => "Los porcentajes deben sumar 100% (suman {$totalPct}%)"
            ], 422);
        }

        // Eliminar solo los pendientes (no tocar los ya emitidos)
        $cotizacion->documentosFacturacion()->where('estado', 'pendiente')->delete();

        $creados = [];
        foreach ($request->documentos as $doc) {
            $monto = round($cotizacion->total * $doc['porcentaje'] / 100);
            $creados[] = DocumentoFacturacion::create([
                'cotizacion_id' => $cotizacion->id,
                'tipo'          => $doc['tipo'],
                'porcentaje'    => $doc['porcentaje'],
                'monto'         => $monto,
                'nota'          => $doc['nota'] ?? null,
                'estado'        => 'pendiente',
            ]);
        }

        return response()->json($creados, 201);
    }

    // Marcar como emitido (después de emitir en Bsale)
    public function marcarEmitido(Request $request, $id)
    {
        $request->validate([
            'id_documento_bsale'      => 'nullable|string',
            'numero_documento_bsale'  => 'nullable|string',
            'url_pdf_bsale'           => 'nullable|string',
            'fecha_emision'           => 'nullable|date',
        ]);

        $doc = DocumentoFacturacion::findOrFail($id);

        if ($doc->estado === 'emitido') {
            return response()->json(['message' => 'Este documento ya fue emitido'], 422);
        }

        $doc->update([
            'estado'                  => 'emitido',
            'id_documento_bsale'      => $request->id_documento_bsale,
            'numero_documento_bsale'  => $request->numero_documento_bsale,
            'url_pdf_bsale'           => $request->url_pdf_bsale,
            'fecha_emision'           => $request->fecha_emision ?? now()->toDateString(),
        ]);

        return response()->json($doc);
    }

    // Eliminar un documento pendiente
    public function destroy($id)
    {
        $doc = DocumentoFacturacion::findOrFail($id);

        if ($doc->estado === 'emitido') {
            return response()->json(['message' => 'No se puede eliminar un documento ya emitido'], 422);
        }

        $doc->delete();
        return response()->json(['success' => true]);
    }
}
