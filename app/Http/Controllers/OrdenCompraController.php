<?php

namespace App\Http\Controllers;

use App\Mail\OrdenCompraMail;
use App\Models\OrdenCompra;
use App\Models\Proveedor;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

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
                'enviado_via' => $o->enviado_via,
                'enviado_a'   => $o->enviado_a,
                'enviado_at'  => $o->enviado_at?->toDateTimeString(),
                'proveedor_email'    => $o->proveedor?->email,
                'proveedor_telefono' => $o->proveedor?->telefono,
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
            // Usar input() completo: validate() descarta los campos sin regla
            // (referencia, categoria, detalle) y se perderían.
            'items'            => $request->input('items'),
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

        return $this->construirPdf($orden)->download("{$orden->numero}.pdf");
    }

    private function construirPdf(OrdenCompra $orden)
    {
        return Pdf::loadView('ordenes-compra.pdf', [
            'orden'      => $orden,
            'logoBase64' => $this->cargarLogo(),
        ]);
    }

    /**
     * Enviar la orden al proveedor (correo con PDF adjunto o WhatsApp prellenado)
     * y dejar el registro en operaciones (marca "pedido proveedor" en la cotización).
     */
    public function enviar(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'via'      => 'required|in:email,whatsapp',
            'email'    => 'nullable|email',
            'telefono' => 'nullable|string',
            'mensaje'  => 'nullable|string',
        ]);

        $orden = OrdenCompra::with(['proveedor', 'cotizacion.cliente'])->findOrFail($id);

        // Guardar contacto en el proveedor para reutilizarlo la próxima vez
        if ($orden->proveedor) {
            $cambios = [];
            if (!empty($data['email']))    $cambios['email']    = $data['email'];
            if (!empty($data['telefono'])) $cambios['telefono'] = $data['telefono'];
            if ($cambios) $orden->proveedor->update($cambios);
        }

        $mensaje = trim($data['mensaje'] ?? '') ?: $this->mensajePorDefecto($orden);

        if ($data['via'] === 'email') {
            $dest = $data['email'] ?: $orden->proveedor?->email;
            if (!$dest) {
                return response()->json(['error' => 'No hay correo de destino para el proveedor.'], 422);
            }

            try {
                $pdfContent = $this->construirPdf($orden)->output();
                Mail::to($dest)->send(new OrdenCompraMail($orden, $mensaje, $pdfContent));
            } catch (\Throwable $e) {
                return response()->json([
                    'error' => 'No se pudo enviar el correo. Revisa la configuración de correo (SMTP) en el servidor.',
                    'detalle' => $e->getMessage(),
                ], 500);
            }

            $this->registrarEnvio($orden, 'email', $dest);

            return response()->json(['ok' => true, 'mensaje' => "Orden enviada a {$dest}", 'orden' => $this->resumen($orden)]);
        }

        // WhatsApp: construimos el enlace wa.me y el frontend lo abre
        $tel = $data['telefono'] ?: $orden->proveedor?->telefono;
        if (!$tel) {
            return response()->json(['error' => 'No hay teléfono de destino para el proveedor.'], 422);
        }

        $digitos = preg_replace('/\D+/', '', $tel);
        // Si viene sin código de país y parte con 9 (celular chileno), anteponer 56
        if (strlen($digitos) === 9 && str_starts_with($digitos, '9')) {
            $digitos = '56' . $digitos;
        }
        $waUrl = 'https://wa.me/' . $digitos . '?text=' . rawurlencode($mensaje);

        $this->registrarEnvio($orden, 'whatsapp', $tel);

        return response()->json(['ok' => true, 'wa_url' => $waUrl, 'orden' => $this->resumen($orden)]);
    }

    private function mensajePorDefecto(OrdenCompra $orden): string
    {
        $prov = $orden->proveedor_nombre ?? $orden->proveedor?->nombre;
        $saludo = $prov ? "Hola {$prov}," : 'Hola,';

        return "{$saludo}\n\nAdjunto la orden de compra {$orden->numero}. Quedo atento a la confirmación.\n\nSaludos,\nVialum";
    }

    private function registrarEnvio(OrdenCompra $orden, string $via, string $dest): void
    {
        $orden->update([
            'estado'      => 'enviada',
            'enviado_at'  => now(),
            'enviado_via' => $via,
            'enviado_a'   => $dest,
        ]);

        // Dejar el registro en operaciones: marcar pedido al proveedor + nota
        if ($orden->cotizacion) {
            $viaTxt = $via === 'email' ? 'correo' : 'WhatsApp';
            $nota   = "OC {$orden->numero} enviada por {$viaTxt} (" . now()->format('d-m-Y') . ')';
            $notasPrev = trim((string) $orden->cotizacion->notas_operaciones);

            $orden->cotizacion->update([
                'pedido_proveedor'  => true,
                'notas_operaciones' => $notasPrev ? ($notasPrev . ' · ' . $nota) : $nota,
            ]);
        }
    }

    private function resumen(OrdenCompra $orden): array
    {
        return [
            'id'          => $orden->id,
            'estado'      => $orden->estado,
            'enviado_via' => $orden->enviado_via,
            'enviado_a'   => $orden->enviado_a,
            'enviado_at'  => $orden->enviado_at?->toDateTimeString(),
        ];
    }

    private function cargarLogo(): ?string
    {
        try {
            $logoUrl = env('LOGO_URL', 'https://pub-7467388c2656489e9222164e85545a03.r2.dev/assets/logovialum.png');
            $ctx     = stream_context_create(['http' => ['timeout' => 10]]);
            $data    = @file_get_contents($logoUrl, false, $ctx);
            if ($data !== false) {
                return 'data:image/png;base64,' . base64_encode($data);
            }
        } catch (\Throwable $e) {
            // sin logo, el PDF se genera igual
        }

        return null;
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
