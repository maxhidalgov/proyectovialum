<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\CompraItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CompraController extends Controller
{
    private string $baseUrl;
    private ?string $accessToken;

    public function __construct()
    {
        $this->baseUrl    = config('services.bsale.base_url', 'https://api.bsale.cl/v1/');
        $this->accessToken = config('services.bsale.access_token');
    }

    private function headers(): array
    {
        return ['access_token' => $this->accessToken, 'Accept' => 'application/json'];
    }

    // -------------------------------------------------------------------------
    // GET /api/compras
    // -------------------------------------------------------------------------
    public function index(Request $request)
    {
        $q = Compra::with('items')->orderByDesc('fecha_emision');

        if ($search = $request->query('search')) {
            $q->where(function ($query) use ($search) {
                $query->where('nombre_emisor', 'like', "%$search%")
                      ->orWhere('rut_emisor', 'like', "%$search%")
                      ->orWhere('folio', 'like', "%$search%")
                      ->orWhereHas('items', fn($i) => $i->where('nombre', 'like', "%$search%"));
            });
        }

        if ($from = $request->query('desde')) {
            $q->where('fecha_emision', '>=', $from);
        }
        if ($to = $request->query('hasta')) {
            $q->where('fecha_emision', '<=', $to);
        }

        return response()->json($q->paginate(25));
    }

    // -------------------------------------------------------------------------
    // GET /api/compras/{id}
    // -------------------------------------------------------------------------
    public function show(Compra $compra)
    {
        return response()->json($compra->load('items'));
    }

    // -------------------------------------------------------------------------
    // GET /api/compras/buscar-producto?q=tubular
    // -------------------------------------------------------------------------
    public function buscarProducto(Request $request)
    {
        $q = $request->query('q', '');
        if (strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $palabras = array_filter(explode(' ', trim($q)));

        $items = CompraItem::with(['compra' => fn($r) => $r->select('id', 'folio', 'nombre_emisor', 'fecha_emision', 'pdf_url')])
            ->join('compras', 'compra_items.compra_id', '=', 'compras.id')
            ->where(function ($query) use ($palabras) {
                foreach ($palabras as $palabra) {
                    $query->where('compra_items.nombre', 'like', "%$palabra%");
                }
            })
            ->orderByDesc('compras.fecha_emision')
            ->select('compra_items.*')
            ->limit(200)
            ->get();

        // Agrupar por nombre normalizado y calcular último precio neto
        $agrupado = [];
        foreach ($items as $item) {
            $key = strtolower(trim($item->nombre));
            $precioNeto = $item->descuento > 0
                ? round($item->precio_unitario * (1 - $item->descuento / 100))
                : $item->precio_unitario;

            if (!isset($agrupado[$key])) {
                $agrupado[$key] = [
                    'nombre'             => $item->nombre,
                    'ultimo_precio_neto' => $precioNeto,
                    'ultimo_descuento'   => $item->descuento,
                    'ultima_compra'      => $item->compra?->fecha_emision,
                    'proveedor'          => $item->compra?->nombre_emisor,
                    'historial'          => [],
                ];
            }
            $agrupado[$key]['historial'][] = [
                'fecha'           => $item->compra?->fecha_emision,
                'folio'           => $item->compra?->folio,
                'pdf_url'         => $item->compra?->pdf_url,
                'proveedor'       => $item->compra?->nombre_emisor,
                'cantidad'        => $item->cantidad,
                'unidad'          => $item->unidad,
                'precio_unitario' => $item->precio_unitario,
                'descuento'       => $item->descuento,
                'precio_neto'     => $precioNeto,
                'total_linea'     => $item->total_linea,
            ];
        }

        return response()->json(['data' => array_values($agrupado)]);
    }

    // -------------------------------------------------------------------------
    // POST /api/compras/sincronizar
    // -------------------------------------------------------------------------
    public function sincronizar(Request $request)
    {
        set_time_limit(120);

        $maxDocs   = (int)($request->input('max', 0));
        $smart     = (bool)($request->input('smart', false));
        $chunkSize = 200;
        $fetchXml  = $smart || $maxDocs > 0;   // bulk sin XML, el resto sí
        $nuevas    = 0;
        $errores   = 0;
        $totalBsale = 0;
        $batchSize = 50;
        $years     = range(date('Y'), 2024);

        // Calcular total global ANTES de procesar para que has_more sea correcto
        $yearTotals = [];
        foreach ($years as $year) {
            $r = Http::timeout(15)->withHeaders($this->headers())
                ->get("{$this->baseUrl}third_party_documents.json", ['limit' => 1, 'year' => $year]);
            $cnt = $r->successful() ? (int)($r->json()['count'] ?? 0) : 0;
            $yearTotals[$year] = $cnt;
            $totalBsale += $cnt;
        }

        foreach ($years as $year) {
            $totalYear = $yearTotals[$year] ?? 0;
            if ($totalYear === 0) continue;

            if ($smart) {
                // Empezar desde el final, parar al primer doc ya existente
                $offset = max(0, $totalYear - $batchSize);
                do {
                    $resp = Http::timeout(15)->withHeaders($this->headers())
                        ->get("{$this->baseUrl}third_party_documents.json", [
                            'limit'  => $batchSize,
                            'offset' => $offset,
                            'year'   => $year,
                        ]);
                    if (!$resp->successful()) break;

                    $items = $resp->json()['items'] ?? [];
                    $foundExisting = false;

                    foreach (array_reverse($items) as $doc) {
                        try {
                            $resultado = $this->procesarDocumento($doc, true);
                            if ($resultado === 0) { $foundExisting = true; break; }
                            $nuevas++;
                        } catch (\Throwable $e) {
                            $errores++;
                        }
                    }

                    if ($foundExisting || $offset === 0) break;
                    $offset = max(0, $offset - $batchSize);
                } while (true);

                // En modo smart solo procesamos el año actual (los nuevos siempre son recientes)
                break;
            }

            $limit  = ($maxDocs > 0) ? $maxDocs : $chunkSize;
            if (($nuevas + $errores) >= $limit) break;

            $offset = ($maxDocs > 0)
                ? max(0, $totalYear - max(0, $maxDocs - ($nuevas + $errores)))
                : 0;

            do {
                $resp = Http::timeout(15)->withHeaders($this->headers())
                    ->get("{$this->baseUrl}third_party_documents.json", [
                        'limit'  => $batchSize,
                        'offset' => $offset,
                        'year'   => $year,
                    ]);
                if (!$resp->successful()) break;

                $items = $resp->json()['items'] ?? [];

                foreach (array_reverse($items) as $doc) {
                    if (($nuevas + $errores) >= $limit) break 2;
                    try {
                        $nuevas += $this->procesarDocumento($doc, $fetchXml);
                    } catch (\Throwable $e) {
                        $errores++;
                        Log::error('CompraController@sincronizar', ['bsale_id' => $doc['id'] ?? null, 'error' => $e->getMessage()]);
                    }
                }

                $offset += $batchSize;
            } while (
                $offset < $totalYear &&
                count($items) === $batchSize &&
                ($nuevas + $errores) < $limit
            );
        }

        $hasMas = ($maxDocs === 0 && !$smart) ? Compra::count() < $totalBsale : false;

        return response()->json([
            'success'     => true,
            'nuevas'      => $nuevas,
            'errores'     => $errores,
            'total_bsale' => $totalBsale,
            'has_more'    => $hasMas,
        ]);
    }

    // -------------------------------------------------------------------------
    // Procesa un documento de Bsale y devuelve 1 si fue nuevo, 0 si ya existía
    // -------------------------------------------------------------------------
    private function procesarDocumento(array $doc, bool $fetchXml = true): int
    {
        $bsaleId = (int)$doc['id'];

        if (Compra::where('bsale_id', $bsaleId)->exists()) {
            return 0;
        }

        $fechaEmision   = isset($doc['emissionDate'])     ? date('Y-m-d', $doc['emissionDate'])     : null;
        $fechaRecepcion = isset($doc['siiReceptionDate']) ? date('Y-m-d', $doc['siiReceptionDate']) : null;

        $compra = Compra::create([
            'bsale_id'        => $bsaleId,
            'folio'           => $doc['number']         ?? 0,
            'tipo_dte'        => $doc['codeSii']        ?? 46,
            'rut_emisor'      => $doc['clientCode']     ?? null,
            'nombre_emisor'   => $doc['clientActivity'] ?? null,
            'fecha_emision'   => $fechaEmision,
            'fecha_recepcion' => $fechaRecepcion,
            'neto'            => (int)($doc['netAmount']   ?? 0),
            'iva'             => (int)($doc['ivaAmount']   ?? 0),
            'total'           => (int)($doc['totalAmount'] ?? 0),
            'estado'          => is_array($doc['siiStatus'] ?? null) ? implode(',', $doc['siiStatus']) : ($doc['siiStatus'] ?? null),
            'xml_url'         => $doc['urlXml'] ?? null,
            'pdf_url'         => $doc['urlPdf'] ?? null,
        ]);

        if ($fetchXml && !empty($doc['urlXml'])) {
            $this->parsearLineasXml($compra, $doc['urlXml']);
        }

        return 1;
    }

    // -------------------------------------------------------------------------
    // POST /api/compras/cargar-xmls-pendientes  — procesa XMLs en lote
    // -------------------------------------------------------------------------
    public function cargarXmlsPendientes(Request $request)
    {
        set_time_limit(0);

        $lote = (int)($request->input('lote', 100));

        $procesadas = 0;
        $errores    = 0;

        if ($lote > 0) {
            $pendientes = Compra::whereNotNull('xml_url')
                ->whereDoesntHave('items')
                ->limit($lote)
                ->get();

            foreach ($pendientes as $compra) {
                try {
                    $this->parsearLineasXml($compra, $compra->xml_url);
                    $procesadas++;
                } catch (\Throwable $e) {
                    $errores++;
                }
            }
        }

        $restantes = Compra::whereNotNull('xml_url')
            ->whereDoesntHave('items')
            ->count();

        return response()->json([
            'success'    => true,
            'procesadas' => $procesadas,
            'errores'    => $errores,
            'restantes'  => $restantes,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/compras/{compra}/cargar-xml  — carga líneas bajo demanda
    // -------------------------------------------------------------------------
    public function cargarXml(Compra $compra)
    {
        if ($compra->items()->exists()) {
            return response()->json(['success' => true, 'mensaje' => 'Ya tiene líneas cargadas', 'items' => $compra->items]);
        }

        if (empty($compra->xml_url)) {
            return response()->json(['success' => false, 'mensaje' => 'Esta factura no tiene XML disponible'], 404);
        }

        $this->parsearLineasXml($compra, $compra->xml_url);

        return response()->json(['success' => true, 'items' => $compra->fresh()->items]);
    }

    // -------------------------------------------------------------------------
    // Descarga y parsea el XML DTE chileno extrayendo las líneas de detalle
    // -------------------------------------------------------------------------
    private function parsearLineasXml(Compra $compra, string $xmlUrl): void
    {
        try {
            $resp = Http::withHeaders($this->headers())->get($xmlUrl);

            if (!$resp->successful()) return;

            $xml = @simplexml_load_string($resp->body());
            if (!$xml) return;

            // Registrar namespaces si los hay
            $xml->registerXPathNamespace('sii', 'http://www.sii.cl/SiiDte');

            // Intentar con namespace y sin él
            $detalles = $xml->xpath('//sii:Detalle') ?: $xml->xpath('//Detalle');

            if (empty($detalles)) {
                // Navegar manualmente por la estructura DTE
                $dte = $xml->Documento ?? $xml->DTE ?? $xml;
                $documento = $dte->Documento ?? $dte;
                $detalles  = [];

                if (isset($documento->Detalle)) {
                    foreach ($documento->Detalle as $d) {
                        $detalles[] = $d;
                    }
                }
            }

            foreach ($detalles as $det) {
                $nombre   = (string)($det->NmbItem   ?? $det->DscItem ?? '');
                $cantidad = (float)($det->QtyItem    ?? 1);
                $precio   = (int)($det->PrcItem      ?? 0);
                $descto   = (float)($det->DescuentoPct ?? 0);
                $total    = (int)($det->MontoItem    ?? round($precio * $cantidad * (1 - $descto / 100)));
                $unidad   = (string)($det->UnmdItem  ?? '');
                $codigo   = (string)($det->CdgItem->VlrCodigo ?? $det->CdgItem ?? '');

                if ($nombre === '') continue;

                CompraItem::create([
                    'compra_id'       => $compra->id,
                    'codigo'          => $codigo ?: null,
                    'nombre'          => $nombre,
                    'cantidad'        => $cantidad,
                    'unidad'          => $unidad ?: null,
                    'precio_unitario' => $precio,
                    'descuento'       => $descto,
                    'total_linea'     => $total,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('CompraController: no se pudo parsear XML', [
                'compra_id' => $compra->id,
                'url'       => $xmlUrl,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/compras/estadisticas?mes=5&anio=2026
    // -------------------------------------------------------------------------
    public function estadisticas(Request $request)
    {
        $mes  = (int)($request->query('mes',  date('n')));
        $anio = (int)($request->query('anio', date('Y')));

        // --- KPIs del mes ---
        $kpis = Compra::whereYear('fecha_emision', $anio)
            ->whereMonth('fecha_emision', $mes)
            ->selectRaw('COUNT(*) as cantidad, SUM(neto) as total_neto, SUM(iva) as total_iva, SUM(total) as total_bruto')
            ->first();

        // --- Por día ---
        $porDia = Compra::whereYear('fecha_emision', $anio)
            ->whereMonth('fecha_emision', $mes)
            ->selectRaw('DAY(fecha_emision) as dia, SUM(neto) as neto, COUNT(*) as cantidad')
            ->groupBy('dia')
            ->orderBy('dia')
            ->get()
            ->keyBy('dia');

        // Rellenar todos los días del mes
        $diasEnMes = (int) date('t', mktime(0, 0, 0, $mes, 1, $anio));
        $labels = [];
        $serieNeto = [];
        $serieCantidad = [];
        for ($d = 1; $d <= $diasEnMes; $d++) {
            $labels[]        = $d;
            $serieNeto[]     = (int)($porDia[$d]->neto ?? 0);
            $serieCantidad[] = (int)($porDia[$d]->cantidad ?? 0);
        }

        // --- Top 8 proveedores del mes ---
        $proveedores = Compra::whereYear('fecha_emision', $anio)
            ->whereMonth('fecha_emision', $mes)
            ->whereNotNull('nombre_emisor')
            ->selectRaw('nombre_emisor, COUNT(*) as cantidad, SUM(neto) as total')
            ->groupBy('nombre_emisor')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $totalNeto = (int)($kpis->total_neto ?? 0);
        $proveedores = $proveedores->map(fn($p) => [
            'nombre'    => $p->nombre_emisor,
            'cantidad'  => $p->cantidad,
            'total'     => (int)$p->total,
            'porcentaje' => $totalNeto > 0 ? round($p->total / $totalNeto * 100, 1) : 0,
        ]);

        // --- Evolución últimos 12 meses ---
        $evolucion = Compra::selectRaw('YEAR(fecha_emision) as anio, MONTH(fecha_emision) as mes, SUM(neto) as total, COUNT(*) as cantidad')
            ->where('fecha_emision', '>=', now()->subMonths(11)->startOfMonth())
            ->groupByRaw('YEAR(fecha_emision), MONTH(fecha_emision)')
            ->orderByRaw('YEAR(fecha_emision), MONTH(fecha_emision)')
            ->get();

        $mesesNombres = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $evolucionLabels = $evolucion->map(fn($r) => $mesesNombres[$r->mes] . ' ' . $r->anio)->values();
        $evolucionNeto   = $evolucion->map(fn($r) => (int)$r->total)->values();

        return response()->json([
            'kpis' => [
                'cantidad'    => (int)($kpis->cantidad   ?? 0),
                'total_neto'  => (int)($kpis->total_neto  ?? 0),
                'total_iva'   => (int)($kpis->total_iva   ?? 0),
                'total_bruto' => (int)($kpis->total_bruto ?? 0),
                'promedio'    => $kpis->cantidad > 0 ? (int)($kpis->total_neto / $kpis->cantidad) : 0,
            ],
            'diario' => [
                'labels'    => $labels,
                'neto'      => $serieNeto,
                'cantidad'  => $serieCantidad,
            ],
            'proveedores' => $proveedores,
            'evolucion'   => [
                'labels' => $evolucionLabels,
                'neto'   => $evolucionNeto,
            ],
        ]);
    }
}
