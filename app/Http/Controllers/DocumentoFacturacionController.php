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
            // Excluir los que ya están repartidos entre varias cotizaciones
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))->from('documento_cotizacion as dc')
                    ->whereColumn('dc.documento_facturacion_id', 'df.id');
            })
            ->when($request->buscar, function ($q, $b) {
                $q->where(function ($w) use ($b) {
                    $w->where('df.numero_documento_bsale', 'like', "%{$b}%")
                      ->orWhere('df.tipo', 'like', "%{$b}%")
                      ->orWhere('df.nro_comprobante_transbank', 'like', "%{$b}%")
                      ->orWhere('df.bsale_cliente_nombre', 'like', "%{$b}%")
                      ->orWhere('df.bsale_cliente_rut', 'like', "%{$b}%")
                      ->orWhere('c.razon_social', 'like', "%{$b}%");
                });
            })
            ->when($request->desde, fn ($q, $d) => $q->whereDate('df.fecha_emision', '>=', $d))
            ->when($request->hasta, fn ($q, $d) => $q->whereDate('df.fecha_emision', '<=', $d))
            ->orderByDesc('df.fecha_emision')
            ->limit(300)
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
                'df.forma_pago',
                'df.nro_comprobante_transbank',
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

        // Recalcular porcentaje respecto al total de la cotización.
        // total en NETO; doc.monto en BRUTO → comparar en bruto (×1.19).
        $pct = $cotizacion->total > 0
            ? round(($doc->monto / ($cotizacion->total * 1.19)) * 100, 2)
            : 0;

        $doc->update([
            'cotizacion_id' => $cotizacion->id,
            'porcentaje'    => $pct,
        ]);

        return response()->json($doc->fresh());
    }

    /**
     * Reparte UN documento (una factura) entre VARIAS cotizaciones.
     * Cada asignación lleva la porción (bruto) del documento que corresponde a esa
     * cotización. El pivot documento_cotizacion pasa a ser la fuente de verdad y el
     * cotizacion_id directo del documento se anula (para no contar doble en las
     * vistas 1:1). Ver también los consumidores del pivot en Facturación/Operaciones.
     */
    public function repartir(Request $request, int $id)
    {
        $data = $request->validate([
            'asignaciones'                 => 'required|array|min:1',
            'asignaciones.*.cotizacion_id' => 'required|integer|exists:cotizaciones,id',
            'asignaciones.*.monto'         => 'required|numeric|min:0',
        ]);

        $doc = DocumentoFacturacion::findOrFail($id);

        // Validar que la suma no exceda el monto del documento (con tolerancia de $1)
        $suma = collect($data['asignaciones'])->sum(fn ($a) => (float) $a['monto']);
        if ($suma <= 0) {
            return response()->json(['message' => 'La suma de los montos debe ser mayor a 0'], 422);
        }
        if ($suma > (float) $doc->monto + 1) {
            return response()->json(['message' => 'La suma asignada ($' . number_format($suma, 0, ',', '.') . ') supera el monto del documento ($' . number_format($doc->monto, 0, ',', '.') . ')'], 422);
        }

        // Evitar cotizaciones repetidas
        $ids = collect($data['asignaciones'])->pluck('cotizacion_id');
        if ($ids->count() !== $ids->unique()->count()) {
            return response()->json(['message' => 'Hay cotizaciones repetidas en el reparto'], 422);
        }

        DB::transaction(function () use ($doc, $data) {
            DB::table('documento_cotizacion')->where('documento_facturacion_id', $doc->id)->delete();
            $now = now();
            foreach ($data['asignaciones'] as $a) {
                if ((float) $a['monto'] <= 0) continue;
                DB::table('documento_cotizacion')->insert([
                    'documento_facturacion_id' => $doc->id,
                    'cotizacion_id'            => $a['cotizacion_id'],
                    'monto'                    => (float) $a['monto'],
                    'created_at'               => $now,
                    'updated_at'               => $now,
                ]);
            }
            // El pivot manda: quitar el enlace 1:1 para no duplicar en vistas legacy
            $doc->update(['cotizacion_id' => null]);
        });

        return response()->json(['ok' => true, 'asignadas' => $ids->count()]);
    }

    /** Deshace el reparto (vuelve a dejar el documento como huérfano). */
    public function desrepartir(int $id)
    {
        DB::table('documento_cotizacion')->where('documento_facturacion_id', $id)->delete();
        return response()->json(['ok' => true]);
    }

    /**
     * Anula un documento de facturación local (cuando la factura se anuló en Bsale
     * con NC y se re-emitió por fuera). Queda 'anulado': no cuenta como facturado.
     * Si la cotización estaba marcada Facturada y ya no cubre el total, se revierte.
     */
    public function anular($id)
    {
        $doc = DocumentoFacturacion::findOrFail($id);

        if ($doc->estado === 'anulado') {
            return response()->json(['message' => 'El documento ya está anulado'], 422);
        }

        $doc->update(['estado' => 'anulado']);

        // Revertir el estado de la cotización si ya no está totalmente facturada
        if ($doc->cotizacion_id) {
            $cot = Cotizacion::find($doc->cotizacion_id);
            if ($cot) {
                $emitido = DocumentoFacturacion::where('cotizacion_id', $cot->id)
                    ->where('estado', 'emitido')->sum('monto');
                $totalBruto = round(((float) $cot->total) * 1.19); // total es NETO
                $facturadaId = optional(\App\Models\EstadoCotizacion::where('nombre', 'Facturada')->first())->id;
                if ($emitido < $totalBruto && $facturadaId && $cot->estado_cotizacion_id == $facturadaId) {
                    $aprobadaId = optional(\App\Models\EstadoCotizacion::where('nombre', 'Aprobada')->first())->id;
                    if ($aprobadaId) {
                        $cot->update(['estado_cotizacion_id' => $aprobadaId]);
                    }
                }
            }
        }

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
            // total en NETO → monto del plan en BRUTO (con IVA)
            $monto = round($cotizacion->total * $doc['porcentaje'] / 100 * 1.19);
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
