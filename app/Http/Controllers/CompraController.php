<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\CompraItem;
use App\Models\ProductoColorProveedor;
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
                'codigo'          => $item->codigo,
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

        // Si no entró ningún doc nuevo en esta ronda, no tiene sentido seguir
        $hasMas = ($maxDocs === 0 && !$smart && $nuevas > 0) ? Compra::count() < $totalBsale : false;

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

        $rutEmisor = $doc['clientCode'] ?? null;
        $categoria = null;
        if ($rutEmisor) {
            $regla = DB::table('reglas_categoria_proveedor')
                ->where('rut_emisor', mb_strtolower($rutEmisor))
                ->value('categoria');
            $categoria = $regla ?: null;
        }

        $compra = Compra::create([
            'bsale_id'        => $bsaleId,
            'folio'           => $doc['number']         ?? 0,
            'tipo_dte'        => $doc['codeSii']        ?? 46,
            'rut_emisor'      => $rutEmisor,
            'nombre_emisor'   => $doc['clientActivity'] ?? null,
            'fecha_emision'   => $fechaEmision,
            'fecha_recepcion' => $fechaRecepcion,
            'neto'            => (int)($doc['netAmount']   ?? 0),
            'iva'             => (int)($doc['ivaAmount']   ?? 0),
            'total'           => (int)($doc['totalAmount'] ?? 0),
            'estado'          => is_array($doc['siiStatus'] ?? null) ? implode(',', $doc['siiStatus']) : ($doc['siiStatus'] ?? null),
            'xml_url'         => $doc['urlXml'] ?? null,
            'pdf_url'         => $doc['urlPdf'] ?? null,
            'categoria'       => $categoria,
        ]);

        if ($fetchXml && !empty($doc['urlXml'])) {
            $this->parsearLineasXml($compra, $doc['urlXml']);
        }

        return 1;
    }

    // -------------------------------------------------------------------------
    // POST /api/compras/vincular-ncs  — re-procesa NCs sin referencia desde XML
    // -------------------------------------------------------------------------
    public function vincularNcsPendientes(Request $request)
    {
        set_time_limit(0);

        $ncs = Compra::where('tipo_dte', 61)
            ->whereNull('nc_referencia_id')
            ->whereNotNull('xml_url')
            ->get();

        $vinculadas = 0;
        $errores    = 0;

        foreach ($ncs as $nc) {
            try {
                $resp = Http::timeout(15)->withHeaders($this->headers())->get($nc->xml_url);
                if (!$resp->successful()) { $errores++; continue; }
                $xml = @simplexml_load_string($resp->body());
                if (!$xml) { $errores++; continue; }
                $xml->registerXPathNamespace('sii', 'http://www.sii.cl/SiiDte');
                $antesDe = $nc->nc_referencia_id;
                $this->vincularReferenciaNC($nc, $xml);
                $nc->refresh();
                if ($nc->nc_referencia_id && !$antesDe) $vinculadas++;
            } catch (\Throwable $e) {
                $errores++;
            }
        }

        return response()->json([
            'total'     => $ncs->count(),
            'vinculadas'=> $vinculadas,
            'errores'   => $errores,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/compras/limpiar-badges-nc
    // Para facturas con nc_revision_estado='requiere_revision' donde TODAS las NCs
    // que la referencian ya tienen compra_nc_aplicacion → marca como 'aplicado'.
    // Para facturas sin ninguna NC apuntando a ellas → limpia el estado (dato obsoleto).
    // -------------------------------------------------------------------------
    public function limpiarBadgesNc()
    {
        // 1. Facturas donde TODAS las NCs que la referencian ya tienen compra_nc_aplicacion
        //    JOIN con subquery para evitar error MySQL 1093 (no self-reference en UPDATE WHERE)
        DB::statement("
            UPDATE compras f
            JOIN (
                SELECT nc.nc_referencia_id AS factura_id
                FROM compras nc
                LEFT JOIN compra_nc_aplicacion ap ON ap.nc_id = nc.id
                WHERE nc.tipo_dte = 61 AND nc.nc_referencia_id IS NOT NULL
                GROUP BY nc.nc_referencia_id
                HAVING COUNT(nc.id) > 0
                   AND SUM(ap.nc_id IS NULL) = 0
            ) ncs_ok ON ncs_ok.factura_id = f.id
            SET f.nc_revision_estado = 'aplicado', f.updated_at = NOW()
            WHERE f.nc_revision_estado = 'requiere_revision'
              AND f.pagado_historico = 0
        ");

        // 2. Facturas con requiere_revision sin ninguna NC apuntando → dato obsoleto
        DB::statement("
            UPDATE compras f
            LEFT JOIN compras nc ON nc.nc_referencia_id = f.id AND nc.tipo_dte = 61
            SET f.nc_revision_estado = NULL, f.updated_at = NOW()
            WHERE f.nc_revision_estado = 'requiere_revision'
              AND f.pagado_historico = 0
              AND nc.id IS NULL
        ");

        $restantes = DB::table('compras')
            ->where('nc_revision_estado', 'requiere_revision')
            ->where('pagado_historico', false)
            ->count();

        return response()->json([
            'ok'        => true,
            'restantes' => $restantes,
        ]);
    }

    // -------------------------------------------------------------------------
    // -------------------------------------------------------------------------
    // POST /api/compras/vincular-ncs-por-monto
    // Para facturas con saldo parcial: busca NCs del mismo proveedor cuyo total
    // coincide con el saldo restante y las vincula (nc_referencia_id).
    // Solo vincula cuando hay match único (misma proveedor + monto exacto).
    // Si hay ambigüedad de monto, elige la NC con fecha más cercana a la factura.
    // -------------------------------------------------------------------------
    public function vincularNcsPorMonto()
    {
        set_time_limit(0);

        // Facturas no históricas con pago bancario parcial y saldo > 0
        $facturas = DB::table('compras as f')
            ->leftJoin(
                DB::raw('(SELECT compra_id, SUM(monto) pagado FROM compra_movimiento GROUP BY compra_id) p'),
                'p.compra_id', '=', 'f.id'
            )
            ->leftJoin(
                DB::raw('(SELECT factura_id, SUM(monto) monto_nc FROM compra_nc_aplicacion GROUP BY factura_id) nca'),
                'nca.factura_id', '=', 'f.id'
            )
            ->where('f.pagado_historico', false)
            ->whereNotIn('f.tipo_dte', [61])
            ->whereNotNull('p.pagado')
            ->select(
                'f.id', 'f.folio', 'f.rut_emisor', 'f.total', 'f.fecha_emision',
                DB::raw('f.total - COALESCE(p.pagado,0) - COALESCE(nca.monto_nc,0) as saldo')
            )
            ->havingRaw('saldo >= 1')
            ->get();

        $vinculadas = 0;
        $ambiguos   = 0;
        $sinMatch   = 0;

        foreach ($facturas as $factura) {
            // NCs del mismo proveedor con total == saldo y sin referencia asignada
            $ncs = DB::table('compras')
                ->where('tipo_dte', 61)
                ->where('rut_emisor', $factura->rut_emisor)
                ->whereNull('nc_referencia_id')
                ->where('total', (int) round($factura->saldo))
                ->orderByRaw('ABS(DATEDIFF(fecha_emision, ?))', [$factura->fecha_emision])
                ->get();

            if ($ncs->isEmpty()) {
                $sinMatch++;
                continue;
            }

            // Tomar la NC con fecha más cercana a la factura
            $nc = $ncs->first();

            // Si hay más de una con la misma fecha diferencia → ambigüedad real
            if ($ncs->count() > 1) {
                $diff0 = abs(strtotime($nc->fecha_emision) - strtotime($factura->fecha_emision));
                $diff1 = abs(strtotime($ncs[1]->fecha_emision) - strtotime($factura->fecha_emision));
                if ($diff0 === $diff1) {
                    $ambiguos++;
                    continue;
                }
            }

            DB::table('compras')
                ->where('id', $nc->id)
                ->update(['nc_referencia_id' => $factura->id, 'updated_at' => now()]);

            $vinculadas++;
        }

        return response()->json([
            'facturas_con_saldo' => $facturas->count(),
            'vinculadas'         => $vinculadas,
            'ambiguos'           => $ambiguos,
            'sin_match'          => $sinMatch,
        ]);
    }

    // POST /api/compras/sincronizar-conciliacion-chipax
    // Ejecuta chipax:sync-docs (trae conciliación de Chipax) y luego
    // chipax:link-local (crea compra_movimiento desde linked_docs).
    // -------------------------------------------------------------------------
    public function sincronizarConciliacionChipax(Request $request)
    {
        set_time_limit(0);

        $desde = $request->get('desde', now()->startOfYear()->format('Y-m-d'));
        $hasta = $request->get('hasta', now()->format('Y-m-d'));

        // Paso 1: sync-docs — descarga linked_docs de Chipax
        $exitSync = \Artisan::call('chipax:sync-docs', [
            '--desde' => $desde,
            '--hasta' => $hasta,
        ]);
        $outputSync = strip_tags(\Artisan::output());

        // Paso 2: link-local — materializa linked_docs en pivotes locales
        $exitLink = \Artisan::call('chipax:link-local');
        $outputLink = strip_tags(\Artisan::output());

        return response()->json([
            'ok'          => $exitSync === 0 && $exitLink === 0,
            'sync_docs'   => trim($outputSync),
            'link_local'  => trim($outputLink),
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/compras/aplicar-ncs-revision
    // Crea compra_nc_aplicacion para todas las NCs con nc_referencia_id seteado
    // que aún no tienen registro en compra_nc_aplicacion, y limpia nc_revision_estado.
    // -------------------------------------------------------------------------
    public function aplicarNcsPendientesRevision()
    {
        $ncs = DB::table('compras as nc')
            ->join('compras as f', 'f.id', '=', 'nc.nc_referencia_id')
            ->where('nc.tipo_dte', 61)
            ->whereNotNull('nc.nc_referencia_id')
            ->where('nc.pagado_historico', false)
            ->whereNotExists(fn($q) => $q->from('compra_nc_aplicacion')->whereColumn('compra_nc_aplicacion.nc_id', 'nc.id'))
            ->select(
                'nc.id as nc_id',
                'nc.total as nc_total',
                'nc.fecha_emision',
                'f.id as factura_id',
            )
            ->get();

        $aplicadas = 0;
        $sinSaldo  = 0;

        foreach ($ncs as $nc) {
            // Calcular cuánto saldo queda en la factura después de pagos bancarios y otras NCs
            $bancoPagado   = DB::table('compra_movimiento')->where('compra_id', $nc->factura_id)->sum('monto');
            $ncYaAplicado  = DB::table('compra_nc_aplicacion')->where('factura_id', $nc->factura_id)->sum('monto');
            $facturaTotal  = DB::table('compras')->where('id', $nc->factura_id)->value('total') ?? 0;
            $saldoRestante = (float) $facturaTotal - (float) $bancoPagado - (float) $ncYaAplicado;

            // Si el saldo restante es negativo o cero, la factura ya está cubierta; aplicamos 0 para limpiar el flag
            $monto = max(0, min((float) $nc->nc_total, $saldoRestante));

            if ($monto > 0) {
                DB::table('compra_nc_aplicacion')->insert([
                    'nc_id'      => $nc->nc_id,
                    'factura_id' => $nc->factura_id,
                    'monto'      => $monto,
                    'fecha'      => $nc->fecha_emision,
                    'nota'       => 'Auto-aplicado (panel admin)',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $aplicadas++;
            } else {
                $sinSaldo++;
            }

            // Limpiar el flag en la factura siempre (ya sea que se aplicó o no)
            DB::table('compras')->where('id', $nc->factura_id)
                ->update(['nc_revision_estado' => null, 'updated_at' => now()]);
        }

        return response()->json([
            'total'     => $ncs->count(),
            'aplicadas' => $aplicadas,
            'sin_saldo' => $sinSaldo,
        ]);
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
            $resp = Http::timeout(15)->withHeaders($this->headers())->get($xmlUrl);

            if (!$resp->successful()) {
                return;
            }

            $xml = @simplexml_load_string($resp->body());
            if (!$xml) {
                return;
            }

            $xml->registerXPathNamespace('sii', 'http://www.sii.cl/SiiDte');

            // Para NCs (DTE 61): leer <Referencia> para auto-vincular a su factura original
            if ($compra->tipo_dte == 61 && !$compra->nc_referencia_id) {
                $this->vincularReferenciaNC($compra, $xml);
            }

            $detalles = $xml->xpath('//sii:Detalle') ?: $xml->xpath('//Detalle');

            if (empty($detalles)) {
                $dte      = $xml->Documento ?? $xml->DTE ?? $xml;
                $documento = $dte->Documento ?? $dte;
                $detalles  = [];
                if (isset($documento->Detalle)) {
                    foreach ($documento->Detalle as $d) {
                        $detalles[] = $d;
                    }
                }
            }

            if (empty($detalles)) {
                $compra->update(['xml_url' => null]);
                return;
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

    private function vincularReferenciaNC(Compra $nc, \SimpleXMLElement $xml): void
    {
        // Intentar xpath con namespace y sin él
        $refs = $xml->xpath('//sii:Referencia') ?: $xml->xpath('//Referencia');

        if (empty($refs)) {
            $dte  = $xml->Documento ?? $xml->DTE ?? $xml;
            $doc  = $dte->Documento ?? $dte;
            $refs = [];
            if (isset($doc->Referencia)) {
                foreach ($doc->Referencia as $r) $refs[] = $r;
            }
        }

        foreach ($refs as $ref) {
            $tipoRef  = (int)($ref->TpoDocRef ?? 0);
            $folioRef = (string)($ref->FolioRef ?? '');

            if (!in_array($tipoRef, [33, 34]) || $folioRef === '') continue;

            $factura = DB::table('compras')
                ->where('folio', $folioRef)
                ->where('rut_emisor', $nc->rut_emisor)
                ->whereIn('tipo_dte', [33, 34])
                ->first(['id']);

            if (!$factura) break;

            $nc->update(['nc_referencia_id' => $factura->id]);

            // Si la factura no tiene pagos bancarios → auto-aplicar la NC
            $tienePagos = DB::table('compra_movimiento')->where('compra_id', $factura->id)->exists();
            if (!$tienePagos) {
                $yaAplicado = (float) DB::table('compra_nc_aplicacion')
                    ->where('nc_id', $nc->id)->sum('monto');
                $saldo = (float) $nc->total - $yaAplicado;
                if ($saldo > 0) {
                    DB::table('compra_nc_aplicacion')->insert([
                        'nc_id'      => $nc->id,
                        'factura_id' => $factura->id,
                        'monto'      => $saldo,
                        'fecha'      => $nc->fecha_emision,
                        'nota'       => 'Auto-aplicado desde XML Bsale',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } else {
                // Factura ya pagada → marcar para revisión
                DB::table('compras')->where('id', $factura->id)
                    ->whereNull('nc_revision_estado')
                    ->update(['nc_revision_estado' => 'requiere_revision', 'updated_at' => now()]);
            }

            break; // Una sola referencia por NC
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/compras/vincular-ncs-via-bsale
    // Para NCs en DB sin nc_referencia_id: las busca en Bsale third_party_documents,
    // descarga su XML y llama vincularReferenciaNC para extraer la factura referenciada.
    // -------------------------------------------------------------------------
    public function vincularNcsViaBsale(Request $request)
    {
        set_time_limit(0);

        $debug = (bool) $request->get('debug', false);

        $years = $request->get('years')
            ? array_unique(array_map('intval', explode(',', $request->get('years'))))
            : [2024, 2025, (int) date('Y')];

        // Lookup local: folio => registro NC sin nc_referencia_id
        $ncsLocales = DB::table('compras')
            ->where('tipo_dte', 61)
            ->whereNull('nc_referencia_id')
            ->select('id', 'folio', 'rut_emisor', 'xml_url')
            ->get()
            ->keyBy('folio');

        if ($ncsLocales->isEmpty()) {
            return response()->json(['ok' => true, 'vinculadas' => 0, 'mensaje' => 'Sin NCs pendientes']);
        }

        $vinculadas   = 0;
        $sinXml       = 0;
        $errores      = 0;
        $procesados   = 0;
        $limit        = 50;
        $debugInfo    = [];

        foreach ($years as $year) {
            $offset = 0;

            do {
                $resp = Http::timeout(20)->withHeaders($this->headers())
                    ->get("{$this->baseUrl}third_party_documents.json", [
                        'year'    => $year,
                        'codesii' => 61,
                        'limit'   => $limit,
                        'offset'  => $offset,
                    ]);

                if (!$resp->successful()) break;

                $data  = $resp->json();
                $items = $data['items'] ?? [];
                $total = (int)($data['count'] ?? 0);

                foreach ($items as $doc) {
                    if ((int)($doc['codeSii'] ?? 0) !== 61) continue;

                    $folio  = (string)($doc['number']     ?? '');
                    $rutDoc = (string)($doc['clientCode'] ?? '');

                    if (!isset($ncsLocales[$folio])) continue;

                    $ncLocal = $ncsLocales[$folio];

                    // Verificar rut si ambos están disponibles
                    if (!empty($rutDoc) && !empty($ncLocal->rut_emisor)) {
                        $rutNorm = fn($r) => strtolower(preg_replace('/[^0-9kK]/', '', $r));
                        if ($rutNorm($rutDoc) !== $rutNorm($ncLocal->rut_emisor)) continue;
                    }

                    $xmlUrl = $doc['urlXml'] ?? null;

                    if (empty($xmlUrl)) {
                        $sinXml++;
                        continue;
                    }

                    // Guardar xml_url si faltaba en DB
                    if (empty($ncLocal->xml_url)) {
                        DB::table('compras')->where('id', $ncLocal->id)->update(['xml_url' => $xmlUrl, 'updated_at' => now()]);
                    }

                    $procesados++;

                    try {
                        $xmlResp = Http::timeout(15)->withHeaders($this->headers())->get($xmlUrl);
                        if (!$xmlResp->successful()) { $errores++; continue; }

                        $xml = @simplexml_load_string($xmlResp->body());
                        if (!$xml) { $errores++; continue; }

                        $xml->registerXPathNamespace('sii', 'http://www.sii.cl/SiiDte');

                        // ── Modo debug: inspeccionar las primeras 5 NCs ────────
                        if ($debug && count($debugInfo) < 5) {
                            $refs = $xml->xpath('//sii:Referencia') ?: $xml->xpath('//Referencia');
                            if (empty($refs)) {
                                $dte  = $xml->Documento ?? $xml->DTE ?? $xml;
                                $docu = $dte->Documento ?? $dte;
                                $refs = [];
                                if (isset($docu->Referencia)) {
                                    foreach ($docu->Referencia as $r) $refs[] = $r;
                                }
                            }

                            $refsData = [];
                            foreach ($refs as $ref) {
                                $tipoRef  = (int)($ref->TpoDocRef ?? 0);
                                $folioRef = (string)($ref->FolioRef ?? '');
                                $existe   = DB::table('compras')
                                    ->where('folio', $folioRef)
                                    ->whereIn('tipo_dte', [33, 34])
                                    ->exists();
                                $existeConRut = $folioRef ? DB::table('compras')
                                    ->where('folio', $folioRef)
                                    ->where('rut_emisor', $ncLocal->rut_emisor)
                                    ->whereIn('tipo_dte', [33, 34])
                                    ->exists() : false;
                                $refsData[] = [
                                    'TpoDocRef'        => $tipoRef,
                                    'FolioRef'         => $folioRef,
                                    'existe_sin_rut'   => $existe,
                                    'existe_con_rut'   => $existeConRut,
                                    'rut_nc_local'     => $ncLocal->rut_emisor,
                                    'rut_bsale_doc'    => $rutDoc,
                                ];
                            }

                            $debugInfo[] = [
                                'nc_folio'     => $folio,
                                'nc_id'        => $ncLocal->id,
                                'year'         => $year,
                                'referencias'  => $refsData,
                                'refs_count'   => count($refs),
                            ];
                        }
                        // ────────────────────────────────────────────────────────

                        $ncModel = Compra::find($ncLocal->id);
                        if (!$ncModel) continue;

                        $antesRef = $ncModel->nc_referencia_id;
                        $this->vincularReferenciaNC($ncModel, $xml);
                        $ncModel->refresh();

                        if ($ncModel->nc_referencia_id && !$antesRef) {
                            $vinculadas++;
                            $ncsLocales->forget($folio);
                        }
                    } catch (\Throwable $e) {
                        $errores++;
                        Log::warning('vincularNcsViaBsale: error procesando NC', [
                            'nc_folio' => $folio,
                            'error'    => $e->getMessage(),
                        ]);
                    }
                }

                $offset += $limit;

                // En modo debug, basta con procesar la primera página
                if ($debug && count($debugInfo) >= 5) break 2;

            } while ($offset < $total && count($items) === $limit);
        }

        $restantes = DB::table('compras')
            ->where('tipo_dte', 61)
            ->whereNull('nc_referencia_id')
            ->count();

        $result = [
            'ok'         => true,
            'vinculadas' => $vinculadas,
            'procesados' => $procesados,
            'sin_xml'    => $sinXml,
            'errores'    => $errores,
            'restantes'  => $restantes,
        ];

        if ($debug) {
            $result['debug'] = $debugInfo;
        }

        return response()->json($result);
    }

    // -------------------------------------------------------------------------
    // GET /api/compras/diagnostico-proveedor?rut=83935900-4
    // Muestra facturas del proveedor con su saldo real y detalle de pagos bancarios
    // -------------------------------------------------------------------------
    public function diagnosticoProveedor(Request $request)
    {
        $rut = $request->get('rut');
        if (!$rut) return response()->json(['error' => 'Falta rut'], 400);

        $facturas = DB::table('compras as c')
            ->where('c.rut_emisor', $rut)
            ->whereNotIn('c.tipo_dte', [61])
            ->where('c.pagado_historico', false)
            ->leftJoin(
                DB::raw('(SELECT compra_id, SUM(monto) as monto_banco, COUNT(*) as cant_movs FROM compra_movimiento GROUP BY compra_id) cm'),
                'cm.compra_id', '=', 'c.id'
            )
            ->leftJoin(
                DB::raw('(SELECT factura_id, SUM(monto) as monto_nc FROM compra_nc_aplicacion GROUP BY factura_id) nca'),
                'nca.factura_id', '=', 'c.id'
            )
            ->leftJoin(
                DB::raw('(SELECT nc.nc_referencia_id as compra_id, SUM(nc.total) as monto_nc_ref
                          FROM compras nc
                          WHERE nc.tipo_dte = 61 AND nc.nc_referencia_id IS NOT NULL
                            AND NOT EXISTS (SELECT 1 FROM compra_nc_aplicacion ap WHERE ap.nc_id = nc.id)
                          GROUP BY nc.nc_referencia_id) ncr'),
                'ncr.compra_id', '=', 'c.id'
            )
            ->select(
                'c.id', 'c.folio', 'c.tipo_dte', 'c.fecha_emision', 'c.total', 'c.nc_revision_estado',
                DB::raw('COALESCE(cm.monto_banco, 0) as monto_banco'),
                DB::raw('COALESCE(cm.cant_movs, 0) as cant_movs'),
                DB::raw('COALESCE(nca.monto_nc, 0) as monto_nc_aplicada'),
                DB::raw('COALESCE(ncr.monto_nc_ref, 0) as monto_nc_ref'),
                DB::raw('c.total - COALESCE(cm.monto_banco,0) - COALESCE(nca.monto_nc,0) - COALESCE(ncr.monto_nc_ref,0) as saldo')
            )
            ->havingRaw('saldo > 0')
            ->orderByDesc('c.fecha_emision')
            ->get();

        $ncs = DB::table('compras as nc')
            ->where('nc.rut_emisor', $rut)
            ->where('nc.tipo_dte', 61)
            ->where('nc.pagado_historico', false)
            ->select('nc.id', 'nc.folio', 'nc.fecha_emision', 'nc.total', 'nc.nc_referencia_id')
            ->orderByDesc('nc.fecha_emision')
            ->get();

        return response()->json([
            'rut'      => $rut,
            'facturas' => $facturas,
            'ncs'      => $ncs,
            'resumen'  => [
                'total_facturas'    => $facturas->count(),
                'total_monto'       => $facturas->sum('total'),
                'total_banco'       => $facturas->sum('monto_banco'),
                'total_saldo'       => $facturas->sum('saldo'),
                'facturas_saldo_0'  => $facturas->filter(fn($f) => (float)$f->saldo <= 0)->count(),
                'facturas_saldo_pos'=> $facturas->filter(fn($f) => (float)$f->saldo > 0)->count(),
            ],
        ]);
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

    // -------------------------------------------------------------------------
    // GET /api/compras/sin-codigo  — PCPs sin codigo_proveedor
    // -------------------------------------------------------------------------
    public function sinCodigo()
    {
        $pcps = ProductoColorProveedor::with(['producto:id,nombre', 'proveedor:id,nombre', 'color:id,nombre'])
            ->whereNull('codigo_proveedor')
            ->orWhere('codigo_proveedor', '')
            ->orderBy('id')
            ->get()
            ->map(fn($p) => [
                'pcp_id'   => $p->id,
                'producto' => $p->producto?->nombre,
                'color'    => $p->color?->nombre,
                'proveedor'=> $p->proveedor?->nombre,
                'costo'    => $p->costo,
            ]);

        return response()->json(['data' => $pcps]);
    }

    // -------------------------------------------------------------------------
    // PATCH /api/compras/asignar-codigo  — asigna codigo_proveedor a un PCP
    // -------------------------------------------------------------------------
    public function asignarCodigo(Request $request)
    {
        $pcp = ProductoColorProveedor::findOrFail($request->input('pcp_id'));
        $pcp->update(['codigo_proveedor' => $request->input('codigo')]);

        // Intentar vincular compra_items que tengan ese código y este proveedor
        $vinculados = CompraItem::whereNull('pcp_id')
            ->where('codigo', $request->input('codigo'))
            ->whereHas('compra', fn($q) => $q->where('nombre_emisor', 'like', '%' . explode(' ', $pcp->proveedor?->nombre ?? '')[0] . '%'))
            ->update(['pcp_id' => $pcp->id]);

        return response()->json(['success' => true, 'vinculados' => $vinculados]);
    }

    // -------------------------------------------------------------------------
    // PATCH /api/compras/actualizar-costo  — actualiza costo en producto_color_proveedor
    // -------------------------------------------------------------------------
    public function actualizarCosto(Request $request)
    {
        $pcp = ProductoColorProveedor::findOrFail($request->input('pcp_id'));
        $pcp->update(['costo' => (int)$request->input('costo')]);
        return response()->json(['success' => true, 'costo' => $pcp->costo]);
    }

    // -------------------------------------------------------------------------
    // POST /api/compras/matchear  — vincula compra_items con producto_color_proveedor
    // -------------------------------------------------------------------------
    public function matchear()
    {
        // Cargar todos los PCP que tienen codigo_proveedor
        $pcps = ProductoColorProveedor::with(['proveedor', 'producto.tipoProducto'])
            ->whereNotNull('codigo_proveedor')
            ->get()
            ->groupBy('codigo_proveedor'); // clave: codigo → colección de PCPs

        $vinculados  = 0;
        $sinMatch    = 0;
        $yaVinculados = 0;

        // Procesar items que tienen código y aún no están vinculados
        CompraItem::with('compra:id,nombre_emisor')
            ->whereNotNull('codigo')
            ->whereNull('pcp_id')
            ->chunkById(500, function ($items) use ($pcps, &$vinculados, &$sinMatch) {
                foreach ($items as $item) {
                    $codigo = trim($item->codigo);
                    if (!isset($pcps[$codigo])) { $sinMatch++; continue; }

                    $candidatos = $pcps[$codigo];

                    // Si solo hay un PCP con ese código, matchear directo
                    if ($candidatos->count() === 1) {
                        $item->update(['pcp_id' => $candidatos->first()->id]);
                        $vinculados++;
                        continue;
                    }

                    // Más de un PCP: intentar afinar por nombre de proveedor
                    $nombreFactura = strtolower($item->compra?->nombre_emisor ?? '');
                    $match = $candidatos->first(function ($pcp) use ($nombreFactura) {
                        $nombrePcp = strtolower($pcp->proveedor?->nombre ?? '');
                        return $nombreFactura && $nombrePcp &&
                            (str_contains($nombreFactura, explode(' ', $nombrePcp)[0]) ||
                             str_contains($nombrePcp, explode(' ', $nombreFactura)[0]));
                    }) ?? $candidatos->first();

                    $item->update(['pcp_id' => $match->id]);
                    $vinculados++;
                }
            });

        $yaVinculados = CompraItem::whereNotNull('pcp_id')->count();

        return response()->json([
            'success'       => true,
            'vinculados'    => $vinculados,
            'sin_match'     => $sinMatch,
            'total_vinculados' => $yaVinculados,
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/compras/alertas-precio  — items donde precio_neto ≠ costo en PCP
    // -------------------------------------------------------------------------
    public function alertasPrecio(Request $request)
    {
        $umbral = (float)($request->query('umbral', 3)); // % de diferencia mínima

        $alertas = CompraItem::with([
                'compra:id,folio,nombre_emisor,fecha_emision,pdf_url',
                'pcp.producto:id,nombre',
                'pcp.proveedor:id,nombre',
                'pcp.color:id,nombre',
            ])
            ->whereNotNull('pcp_id')
            ->whereRaw('ABS(precio_unitario * (1 - descuento/100) - (SELECT costo FROM producto_color_proveedor WHERE id = pcp_id)) / NULLIF((SELECT costo FROM producto_color_proveedor WHERE id = pcp_id), 0) * 100 > ?', [$umbral])
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(function ($item) {
                $precioNeto  = round($item->precio_unitario * (1 - $item->descuento / 100));
                $costoActual = (int)($item->pcp?->costo ?? 0);
                $diferencia  = $costoActual > 0 ? round(($precioNeto - $costoActual) / $costoActual * 100, 1) : null;
                return [
                    'compra_item_id' => $item->id,
                    'fecha'          => $item->compra?->fecha_emision,
                    'folio'          => $item->compra?->folio,
                    'pdf_url'        => $item->compra?->pdf_url,
                    'proveedor'      => $item->compra?->nombre_emisor,
                    'producto'       => $item->pcp?->producto?->nombre,
                    'color'          => $item->pcp?->color?->nombre,
                    'precio_compra'  => $precioNeto,
                    'costo_bd'       => $costoActual,
                    'diferencia_pct' => $diferencia,
                    'pcp_id'         => $item->pcp_id,
                ];
            });

        return response()->json(['data' => $alertas]);
    }
}
