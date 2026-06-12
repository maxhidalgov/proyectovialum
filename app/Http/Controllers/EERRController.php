<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class EERRController extends Controller
{
    private string $token;
    private string $baseUrl;

    public function __construct()
    {
        // Mismo token/URL que CompraController (funciona en Railway)
        $this->token   = config('services.bsale.access_token', '');
        $this->baseUrl = config('services.bsale.base_url', 'https://api.bsale.cl/v1/');
    }

    public function index(Request $request)
    {
        [$desde, $hasta, $modo] = $this->parsePeriodo($request);
        $desdeStr = $desde->toDateString();
        $hastaStr = $hasta->toDateString();
        $mes  = (int) $desde->month;
        $anio = (int) $desde->year;

        // ── Ingresos ──────────────────────────────────────────────────
        if ($modo === 'anual') {
            ['neto' => $totalIngresos, 'bruto' => $ingBruto, 'cantidad' => $ingCantidad] =
                $this->ventasDB($desdeStr, $hastaStr);
        } else {
            ['neto' => $totalIngresos, 'bruto' => $ingBruto, 'cantidad' => $ingCantidad] =
                $this->bsaleVentas($desde->timestamp, $hasta->timestamp);
        }

        $ingManualesCat = DB::table('ingresos_manuales')
            ->whereBetween('fecha', [$desdeStr, $hastaStr])
            ->selectRaw("COALESCE(categoria,'Sin categoría') as categoria, COUNT(*) as cantidad, SUM(monto) as total")
            ->groupBy('categoria')->orderByDesc('total')->get();

        $totalIngManuales       = (float) $ingManualesCat->sum('total');
        $cantidadIngManuales    = (int)   $ingManualesCat->sum('cantidad');
        $totalIngresosCombinado = $totalIngresos + $totalIngManuales;

        $cobradoPeriodo = (float) DB::table('venta_movimiento as vm')
            ->join('movimientos_bancarios as m', 'm.id', '=', 'vm.movimiento_id')
            ->whereBetween('m.fecha_contable', [$desdeStr, $hastaStr])
            ->sum('vm.monto');

        // ── Compras: total bruto/IVA para header (modo puede venir de API) ──
        ['neto' => $totalComprasApi, 'bruto' => $comprasBruto, 'cantidad' => $comprasCantidad] =
            $modo === 'anual'
                ? $this->comprasDB($desdeStr, $hastaStr)
                : $this->bsaleCompras($mes, $anio);

        // ── Data maps para buildSecciones ─────────────────────────────
        // Compras por categoría (siempre desde DB local, excluyendo NCs)
        $cmpMap = DB::table('compras')
            ->whereBetween('fecha_emision', [$desdeStr, $hastaStr])
            ->where(function ($q) { $q->whereNull('tipo_dte')->orWhere('tipo_dte', '!=', 61); })
            ->selectRaw("COALESCE(categoria,'Sin categoría') as cat, COUNT(*) as n, SUM(neto) as total")
            ->groupBy('cat')->get()->keyBy('cat');

        // Gastos (excluye impuesto y previred que van en otras secciones)
        $gasMap = DB::table('gastos')
            ->whereBetween('fecha', [$desdeStr, $hastaStr])
            ->where(function ($q) {
                $q->whereNull('chipax_tipo')->orWhereNotIn('chipax_tipo', ['impuesto', 'previred']);
            })
            ->selectRaw("COALESCE(categoria,'Sin categoría') as cat, COUNT(*) as n, SUM(monto) as total")
            ->groupBy('cat')->get()->keyBy('cat');

        // Pagos empleado por tipo
        $remuMap = DB::table('pagos_empleado')
            ->whereBetween('periodo', [$desdeStr, $hastaStr])
            ->selectRaw('tipo as cat, COUNT(*) as n, SUM(monto) as total')
            ->groupBy('tipo')->get()->keyBy('cat');

        // Previred
        $previredRow = DB::table('gastos')
            ->whereBetween('fecha', [$desdeStr, $hastaStr])
            ->where('chipax_tipo', 'previred')
            ->selectRaw('COUNT(*) as n, SUM(monto) as total')->first();

        // ── Construir secciones jerárquicas ───────────────────────────
        $secciones = $this->buildSecciones($cmpMap, $gasMap, $remuMap, $previredRow);

        // ── Totales por sección para resultados ───────────────────────
        $secTotales = collect($secciones)->keyBy('key')
            ->map(fn($s) => (float) $s['total']);

        $totalCostoVentas  = $secTotales->get('costo_ventas', 0);
        $totalGastosOp     = $secTotales->get('gastos_operacionales', 0);
        $totalRemu         = $secTotales->get('remuneraciones', 0);
        $totalFinanciero   = $secTotales->get('financiero', 0);
        $totalEgresos      = $totalCostoVentas + $totalGastosOp + $totalRemu + $totalFinanciero;

        $utilidadBruta       = $totalIngresosCombinado - $totalCostoVentas;
        $utilidadOperacional = $totalIngresosCombinado - $totalEgresos;
        $margenBruto         = $totalIngresosCombinado > 0 ? round($utilidadBruta / $totalIngresosCombinado * 100, 1) : 0;
        $margenOperacional   = $totalIngresosCombinado > 0 ? round($utilidadOperacional / $totalIngresosCombinado * 100, 1) : 0;

        return response()->json([
            'ingresos' => [
                'total'                  => $totalIngresosCombinado,
                'bsale'                  => $totalIngresos,
                'bruto'                  => round($ingBruto),
                'iva'                    => round($ingBruto - $totalIngresos),
                'cantidad'               => $ingCantidad,
                'cobrado'                => $cobradoPeriodo,
                'manuales_total'         => $totalIngManuales,
                'manuales_cantidad'      => $cantidadIngManuales,
                'manuales_por_categoria' => $ingManualesCat,
            ],

            'secciones' => $secciones,

            'resultados' => [
                'ingresos'             => $totalIngresosCombinado,
                'ingresos_bsale'       => $totalIngresos,
                'ingresos_manuales'    => $totalIngManuales,
                'costo_ventas'         => $totalCostoVentas,
                'gastos_operacionales' => $totalGastosOp,
                'remuneraciones'       => $totalRemu,
                'financiero'           => $totalFinanciero,
                'total_egresos'        => $totalEgresos,
                'utilidad_bruta'       => $utilidadBruta,
                'margen_bruto'         => $margenBruto,
                'utilidad_operacional' => $utilidadOperacional,
                'margen_operacional'   => $margenOperacional,
            ],

            'historico' => $modo === 'anual' ? $this->historicoAnual($anio) : $this->historico(),
            'modo'      => $modo,
            'periodo'   => ['desde' => $desdeStr, 'hasta' => $hastaStr, 'modo' => $modo],
        ]);
    }

    // ── Construye el árbol Sección → Grupo → Líneas ───────────────────────────

    private function buildSecciones($cmpMap, $gasMap, $remuMap, $previredRow): array
    {
        // Helper: extrae {label, cantidad, total} de un mapa
        $l = function (string $label, $map, string $key) {
            $r = $map->get($key);
            return ['label' => $label, 'cantidad' => $r ? (int)$r->n : 0, 'total' => $r ? (float)$r->total : 0.0];
        };

        // Categorías de compras ya asignadas a secciones (para catch-all al final)
        $cmpClaims = [
            'Costos Directo por Venta Aluminio y Termopanel',
            'Costos Directo por Venta PVC',
            'Otros Costos Directos del Giro',
            'Bencina',
            'Transporte/Encomiendas',
            'Repuestos/Arreglos',
            'Gastos Generales',
            'Luz',
            'Gastos de Investigación y Desarrollo',
            'Otros Gastos de Administración y Venta',
            'Otros Egresos Fuera de Explotación',
            'Seguros',
            'Almuerzos',
            'Sueldos Administrativos',
            'Gastos Financieros',
            'Comisiones Pagadas',
        ];

        // Líneas dinámicas del módulo Gastos (todas sus categorías)
        $gastosLineas = $gasMap->map(fn($r) =>
            ['label' => $r->cat, 'cantidad' => (int)$r->n, 'total' => (float)$r->total]
        )->sortByDesc('total')->values()->all();

        // Compras sin categoría o de categorías no clasificadas → van a Gastos Generales
        $sinClasificar = $cmpMap->filter(fn($r, $k) => !in_array($k, $cmpClaims))
            ->map(fn($r) => ['label' => $r->cat . ' *', 'cantidad' => (int)$r->n, 'total' => (float)$r->total])
            ->sortByDesc('total')->values()->all();

        // Construir grupos con totales calculados
        $grupo = function (string $titulo = null, array $lineas) {
            $lineas = array_filter($lineas, fn($l) => $l['total'] > 0);
            $lineas = array_values($lineas);
            return ['titulo' => $titulo, 'total' => array_sum(array_column($lineas, 'total')), 'lineas' => $lineas];
        };

        // ── 1. COSTO DE VENTAS ────────────────────────────────────────
        $g1 = $grupo(null, [
            $l('Costos Directo por Venta Aluminio y Termopanel', $cmpMap, 'Costos Directo por Venta Aluminio y Termopanel'),
            $l('Costos Directo por Venta PVC',                   $cmpMap, 'Costos Directo por Venta PVC'),
            $l('Otros Costos Directos del Giro',                 $cmpMap, 'Otros Costos Directos del Giro'),
        ]);
        $secCV = ['key' => 'costo_ventas', 'titulo' => 'COSTO DE VENTAS',
                  'total' => $g1['total'], 'grupos' => [$g1]];

        // ── 2. GASTOS OPERACIONALES ───────────────────────────────────
        $gLog = $grupo('Logística', [
            $l('Bencina',                    $cmpMap, 'Bencina'),
            $l('Transporte / Encomiendas',   $cmpMap, 'Transporte/Encomiendas'),
            $l('Repuestos / Arreglos',       $cmpMap, 'Repuestos/Arreglos'),
        ]);
        $gGen = $grupo('Gastos Generales', array_merge(
            [
                $l('Gastos Generales',                         $cmpMap, 'Gastos Generales'),
                $l('Luz',                                      $cmpMap, 'Luz'),
                $l('Gastos de Investigación y Desarrollo',     $cmpMap, 'Gastos de Investigación y Desarrollo'),
                $l('Otros Gastos de Administración y Venta',   $cmpMap, 'Otros Gastos de Administración y Venta'),
                $l('Otros Egresos Fuera de Explotación',       $cmpMap, 'Otros Egresos Fuera de Explotación'),
                $l('Seguros',                                  $cmpMap, 'Seguros'),
            ],
            $gastosLineas,   // categorías del módulo Gastos
            $sinClasificar   // compras sin clasificar
        ));
        $secGO = ['key' => 'gastos_operacionales', 'titulo' => 'GASTOS OPERACIONALES',
                  'total' => $gLog['total'] + $gGen['total'], 'grupos' => [$gLog, $gGen]];

        // ── 3. REMUNERACIONES ─────────────────────────────────────────
        $remuLineas = array_filter([
            $remuMap->get('sueldo')   ? ['label' => 'Sueldos líquidos',   'cantidad' => (int)$remuMap->get('sueldo')->n,   'total' => (float)$remuMap->get('sueldo')->total]   : null,
            $remuMap->get('bono')     ? ['label' => 'Bonos',              'cantidad' => (int)$remuMap->get('bono')->n,     'total' => (float)$remuMap->get('bono')->total]     : null,
            $remuMap->get('finiquito')? ['label' => 'Finiquitos',         'cantidad' => (int)$remuMap->get('finiquito')->n,'total' => (float)$remuMap->get('finiquito')->total]: null,
            $previredRow && $previredRow->total > 0
                ? ['label' => 'Previred — cotizaciones', 'cantidad' => (int)$previredRow->n, 'total' => (float)$previredRow->total]
                : null,
            $l('Almuerzos',              $cmpMap, 'Almuerzos'),
            $l('Sueldos Administrativos', $cmpMap, 'Sueldos Administrativos'),
        ]);
        $gRemu = $grupo(null, array_values($remuLineas));
        $secRemu = ['key' => 'remuneraciones', 'titulo' => 'REMUNERACIONES',
                    'total' => $gRemu['total'], 'grupos' => [$gRemu]];

        // ── 4. GASTOS FINANCIEROS ─────────────────────────────────────
        $gFin = $grupo(null, [
            $l('Gastos Financieros',   $cmpMap, 'Gastos Financieros'),
            $l('Comisiones Pagadas',   $cmpMap, 'Comisiones Pagadas'),
        ]);
        $secFin = ['key' => 'financiero', 'titulo' => 'GASTOS FINANCIEROS',
                   'total' => $gFin['total'], 'grupos' => [$gFin]];

        return [$secCV, $secGO, $secRemu, $secFin];
    }

    // ── Ventas: documents.json ────────────────────────────────────────────────

    private function bsaleVentas(int $inicio, int $fin): array
    {
        $totalBruto = 0.0;
        $totalNeto  = 0.0;
        $cantidad   = 0;
        $offset     = 0;
        $limit      = 50;

        do {
            $res = Http::timeout(15)
                ->withHeaders(['access_token' => $this->token])
                ->get($this->baseUrl . 'documents.json', [
                    'emissiondaterange' => "[$inicio,$fin]",
                    'limit'             => $limit,
                    'offset'            => $offset,
                ]);

            if ($res->failed()) break;

            $items = $res->json()['items'] ?? [];

            foreach ($items as $doc) {
                $tipo = (int) ($doc['document_type']['id'] ?? 0);
                if (!in_array($tipo, [1, 2, 3, 4, 5])) continue;

                $sign  = ($tipo === 2) ? -1 : 1; // tipo 2 = nota de crédito
                $bruto = (float) ($doc['totalAmount'] ?? 0) * $sign;
                // netAmount si viene, sino calculamos neto = bruto / 1.19
                $neto  = isset($doc['netAmount'])
                    ? (float) $doc['netAmount'] * $sign
                    : round($bruto / 1.19);

                $totalBruto += $bruto;
                $totalNeto  += $neto;
                $cantidad++;
            }

            $offset += $limit;
        } while (count($items) === $limit);

        return ['neto' => round($totalNeto), 'bruto' => $totalBruto, 'cantidad' => $cantidad];
    }

    // ── Compras: third_party_documents.json con netAmount ────────────────────

    private function bsaleCompras(int $mes, int $anio): array
    {
        $totalBruto = 0.0;
        $totalNeto  = 0.0;
        $cantidad   = 0;
        $offset     = 0;
        $limit      = 50;

        do {
            $res = Http::timeout(15)
                ->withHeaders(['access_token' => $this->token])
                ->get($this->baseUrl . 'third_party_documents.json', [
                    'year'   => $anio,
                    'month'  => $mes,
                    'limit'  => $limit,
                    'offset' => $offset,
                ]);

            if ($res->failed()) break;

            $items = $res->json()['items'] ?? [];

            foreach ($items as $doc) {
                $code = (int) ($doc['codeSii'] ?? 0);
                if (!in_array($code, [33, 61])) continue;

                $sign  = ($code === 61) ? -1 : 1; // 61 = nota crédito
                // netAmount viene en third_party_documents (lo usa CompraController)
                $neto  = (float) ($doc['netAmount']   ?? 0) * $sign;
                $bruto = (float) ($doc['totalAmount'] ?? 0) * $sign;

                $totalNeto  += $neto;
                $totalBruto += $bruto;
                $cantidad++;
            }

            $offset += $limit;
        } while (count($items) === $limit);

        return ['neto' => round($totalNeto), 'bruto' => $totalBruto, 'cantidad' => $cantidad];
    }

    // ── Histórico 6 meses: DB local (sin llamadas a Bsale para no ser lento) ──

    private function historico(): array
    {
        $inicio = now()->subMonths(5)->startOfMonth()->toDateString();

        $ingresos = DB::table('documentos_facturacion')
            ->where('estado', 'emitido')
            ->where('fecha_emision', '>=', $inicio)
            ->selectRaw("DATE_FORMAT(fecha_emision, '%Y-%m') as mes, SUM(monto) as total")
            ->groupBy('mes')
            ->pluck('total', 'mes');

        $ingresosManuales = DB::table('ingresos_manuales')
            ->where('fecha', '>=', $inicio)
            ->selectRaw("DATE_FORMAT(fecha, '%Y-%m') as mes, SUM(monto) as total")
            ->groupBy('mes')
            ->pluck('total', 'mes');

        $compras = DB::table('compras')
            ->where('fecha_emision', '>=', $inicio)
            ->selectRaw("DATE_FORMAT(fecha_emision, '%Y-%m') as mes, SUM(neto) as total")
            ->groupBy('mes')
            ->pluck('total', 'mes');

        $gastos = DB::table('gastos')
            ->where('fecha', '>=', $inicio)
            ->where(function ($q) { $q->whereNull('chipax_tipo')->orWhereNotIn('chipax_tipo', ['impuesto', 'previred']); })
            ->selectRaw("DATE_FORMAT(fecha, '%Y-%m') as mes, SUM(monto) as total")
            ->groupBy('mes')
            ->pluck('total', 'mes');

        $previred = DB::table('gastos')
            ->where('fecha', '>=', $inicio)
            ->where('chipax_tipo', 'previred')
            ->selectRaw("DATE_FORMAT(fecha, '%Y-%m') as mes, SUM(monto) as total")
            ->groupBy('mes')
            ->pluck('total', 'mes');

        $remu = DB::table('pagos_empleado')
            ->where('periodo', '>=', $inicio)
            ->selectRaw("DATE_FORMAT(periodo, '%Y-%m') as mes, SUM(monto) as total")
            ->groupBy('mes')
            ->pluck('total', 'mes');

        return collect(range(5, 0))->map(function ($i) use ($ingresos, $ingresosManuales, $compras, $gastos, $previred, $remu) {
            $mes = now()->subMonths($i)->format('Y-m');
            $ing = (float) ($ingresos[$mes] ?? 0)
                 + (float) ($ingresosManuales[$mes] ?? 0);
            $egr = (float) ($compras[$mes] ?? 0)
                 + (float) ($gastos[$mes] ?? 0)
                 + (float) ($remu[$mes] ?? 0)
                 + (float) ($previred[$mes] ?? 0);
            return ['periodo' => $mes, 'ingresos' => $ing, 'egresos' => $egr, 'utilidad' => $ing - $egr];
        })->values()->all();
    }

    // ── Ventas desde BD local (documentos_facturacion sincronizados de Bsale) ─

    private function ventasDB(string $desde, string $hasta): array
    {
        $r = DB::table('documentos_facturacion')
            ->where('estado', 'emitido')
            ->whereBetween('fecha_emision', [$desde, $hasta])
            ->selectRaw('
                COUNT(*) as cantidad,
                COALESCE(SUM(COALESCE(neto, monto)), 0) as neto,
                COALESCE(SUM(monto), 0) as bruto
            ')
            ->first();

        return [
            'neto'     => (int) round((float) ($r->neto ?? 0)),
            'bruto'    => (int) round((float) ($r->bruto ?? 0)),
            'cantidad' => (int) ($r->cantidad ?? 0),
        ];
    }

    // ── Compras desde BD local (tabla compras sincronizada de Bsale) ─────────

    private function comprasDB(string $desde, string $hasta): array
    {
        // tipo_dte 61 = nota de crédito (signo negativo)
        $r = DB::table('compras')
            ->whereBetween('fecha_emision', [$desde, $hasta])
            ->selectRaw('
                COUNT(*) as cantidad,
                SUM(CASE WHEN tipo_dte = 61 THEN -COALESCE(neto,0) ELSE COALESCE(neto,0) END) as neto,
                SUM(CASE WHEN tipo_dte = 61 THEN -COALESCE(total,0) ELSE COALESCE(total,0) END) as bruto
            ')
            ->first();

        return [
            'neto'     => (int) round((float) ($r->neto ?? 0)),
            'bruto'    => (int) round((float) ($r->bruto ?? 0)),
            'cantidad' => (int) ($r->cantidad ?? 0),
        ];
    }

    // ── Histórico de los 12 meses del año solicitado ─────────────────────────

    private function historicoAnual(int $anio): array
    {
        $inicio = Carbon::create($anio, 1, 1)->toDateString();
        $fin    = Carbon::create($anio, 12, 31)->toDateString();

        $ingresos = DB::table('documentos_facturacion')
            ->where('estado', 'emitido')
            ->whereBetween('fecha_emision', [$inicio, $fin])
            ->selectRaw("DATE_FORMAT(fecha_emision, '%Y-%m') as mes, SUM(monto) as total")
            ->groupBy('mes')
            ->pluck('total', 'mes');

        $ingresosManuales = DB::table('ingresos_manuales')
            ->whereBetween('fecha', [$inicio, $fin])
            ->selectRaw("DATE_FORMAT(fecha, '%Y-%m') as mes, SUM(monto) as total")
            ->groupBy('mes')
            ->pluck('total', 'mes');

        $compras = DB::table('compras')
            ->whereBetween('fecha_emision', [$inicio, $fin])
            ->selectRaw("DATE_FORMAT(fecha_emision, '%Y-%m') as mes, SUM(neto) as total")
            ->groupBy('mes')
            ->pluck('total', 'mes');

        $gastos = DB::table('gastos')
            ->whereBetween('fecha', [$inicio, $fin])
            ->where(function ($q) { $q->whereNull('chipax_tipo')->orWhereNotIn('chipax_tipo', ['impuesto', 'previred']); })
            ->selectRaw("DATE_FORMAT(fecha, '%Y-%m') as mes, SUM(monto) as total")
            ->groupBy('mes')
            ->pluck('total', 'mes');

        $previred = DB::table('gastos')
            ->whereBetween('fecha', [$inicio, $fin])
            ->where('chipax_tipo', 'previred')
            ->selectRaw("DATE_FORMAT(fecha, '%Y-%m') as mes, SUM(monto) as total")
            ->groupBy('mes')
            ->pluck('total', 'mes');

        $remu = DB::table('pagos_empleado')
            ->whereBetween('periodo', [$inicio, $fin])
            ->selectRaw("DATE_FORMAT(periodo, '%Y-%m') as mes, SUM(monto) as total")
            ->groupBy('mes')
            ->pluck('total', 'mes');

        return collect(range(1, 12))->map(function ($m) use ($anio, $ingresos, $ingresosManuales, $compras, $gastos, $previred, $remu) {
            $mes = sprintf('%d-%02d', $anio, $m);
            $ing = (float) ($ingresos[$mes] ?? 0) + (float) ($ingresosManuales[$mes] ?? 0);
            $egr = (float) ($compras[$mes] ?? 0)
                 + (float) ($gastos[$mes] ?? 0)
                 + (float) ($remu[$mes] ?? 0)
                 + (float) ($previred[$mes] ?? 0);
            return ['periodo' => $mes, 'ingresos' => $ing, 'egresos' => $egr, 'utilidad' => $ing - $egr];
        })->values()->all();
    }

    private function parsePeriodo(Request $request): array
    {
        $modo = $request->get('modo', 'mensual');

        // Modo anual: recibe solo anio
        if ($modo === 'anual' && $request->filled('anio')) {
            $anio  = (int) $request->anio;
            $desde = Carbon::create($anio, 1, 1)->startOfDay();
            $hasta = Carbon::create($anio, 12, 31)->endOfDay();
            return [$desde, $hasta, 'anual'];
        }

        // Modo mensual
        if ($request->filled(['mes', 'anio'])) {
            $desde = Carbon::create((int) $request->anio, (int) $request->mes, 1)->startOfMonth();
        } elseif ($request->filled(['desde'])) {
            $desde = Carbon::parse($request->desde)->startOfMonth();
        } else {
            $desde = now()->startOfMonth();
        }
        return [$desde, $desde->copy()->endOfMonth(), 'mensual'];
    }
}
