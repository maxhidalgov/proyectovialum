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
        // Modo anual: DB local (sync Bsale ya realizado, evita 12 llamadas API)
        // Modo mensual: API Bsale en tiempo real (solo 1 llamada)
        if ($modo === 'anual') {
            ['neto' => $totalIngresos, 'bruto' => $ingBruto, 'cantidad' => $ingCantidad] =
                $this->ventasDB($desdeStr, $hastaStr);
        } else {
            ['neto' => $totalIngresos, 'bruto' => $ingBruto, 'cantidad' => $ingCantidad] =
                $this->bsaleVentas($desde->timestamp, $hasta->timestamp);
        }

        // ── Ingresos manuales (sin doc SII) ──────────────────────────
        $ingManualesCat = DB::table('ingresos_manuales')
            ->whereBetween('fecha', [$desdeStr, $hastaStr])
            ->selectRaw("COALESCE(categoria,'Sin categoría') as categoria, COUNT(*) as cantidad, SUM(monto) as total")
            ->groupBy('categoria')
            ->orderByDesc('total')
            ->get();

        $totalIngManuales    = (float) $ingManualesCat->sum('total');
        $cantidadIngManuales = (int)   $ingManualesCat->sum('cantidad');

        // Total ingresos combinado (SII + manuales)
        $totalIngresosCombinado = $totalIngresos + $totalIngManuales;

        // Cobrado en período (base caja, solo referencia)
        $cobradoPeriodo = (float) DB::table('venta_movimiento as vm')
            ->join('movimientos_bancarios as m', 'm.id', '=', 'vm.movimiento_id')
            ->whereBetween('m.fecha_contable', [$desdeStr, $hastaStr])
            ->sum('vm.monto');

        // ── Compras ───────────────────────────────────────────────────
        // Modo anual: DB local (evita 12 llamadas API que causan timeout)
        // Modo mensual: API Bsale (1 llamada, rápida)
        ['neto' => $totalCompras, 'bruto' => $comprasBruto, 'cantidad' => $comprasCantidad] =
            $modo === 'anual'
                ? $this->comprasDB($desdeStr, $hastaStr)
                : $this->bsaleCompras($mes, $anio);

        // Top proveedores desde DB local (sincronizada, más rápido)
        $topProveedores = DB::table('compras')
            ->whereBetween('fecha_emision', [$desdeStr, $hastaStr])
            ->selectRaw('nombre_emisor, COUNT(*) as cantidad, SUM(neto) as total_neto')
            ->groupBy('nombre_emisor')
            ->orderByDesc('total_neto')
            ->limit(6)
            ->get();

        // ── Gastos generales: DB local ────────────────────────────────
        // Excluidos del EERR:
        //   impuesto  → IVA/PPM/retenciones: obligaciones tributarias, no egresos
        //   previred  → cotizaciones previsionales: van en la sección Remuneraciones
        $gastosBase = DB::table('gastos')
            ->whereBetween('fecha', [$desdeStr, $hastaStr])
            ->where(function ($q) {
                $q->whereNull('chipax_tipo')
                  ->orWhereNotIn('chipax_tipo', ['impuesto', 'previred']);
            });

        $gastosTot = (clone $gastosBase)
            ->selectRaw('COUNT(*) as cantidad, SUM(monto) as total')
            ->first();

        $gastosCat = (clone $gastosBase)
            ->selectRaw("COALESCE(categoria, 'Sin categoría') as categoria, COUNT(*) as cantidad, SUM(monto) as total")
            ->groupBy('categoria')
            ->orderByDesc('total')
            ->get();

        $totalGastos = (float) ($gastosTot->total ?? 0);

        // ── Remuneraciones: sueldos + Previred (cotizaciones AFP/salud) ──
        $remuTot = DB::table('pagos_empleado')
            ->whereBetween('periodo', [$desdeStr, $hastaStr])
            ->selectRaw('COUNT(*) as cantidad, SUM(monto) as total')
            ->first();

        $remuTipo = DB::table('pagos_empleado')
            ->whereBetween('periodo', [$desdeStr, $hastaStr])
            ->selectRaw('tipo, COUNT(*) as cantidad, SUM(monto) as total')
            ->groupBy('tipo')
            ->get();

        // Previred: cotizaciones previsionales (AFP + salud empleador)
        $previredTot = DB::table('gastos')
            ->whereBetween('fecha', [$desdeStr, $hastaStr])
            ->where('chipax_tipo', 'previred')
            ->selectRaw('COUNT(*) as cantidad, SUM(monto) as total')
            ->first();

        $totalSueldos  = (float) ($remuTot->total ?? 0);
        $totalPrevired = (float) ($previredTot->total ?? 0);
        $totalRemu     = $totalSueldos + $totalPrevired;

        // ── Resultados (usando ingresos combinados) ───────────────────
        $utilidadBruta       = $totalIngresosCombinado - $totalCompras;
        $totalEgresos        = $totalCompras + $totalGastos + $totalRemu;
        $utilidadOperacional = $totalIngresosCombinado - $totalEgresos;
        $margenBruto         = $totalIngresosCombinado > 0 ? round($utilidadBruta / $totalIngresosCombinado * 100, 1) : 0;
        $margenOperacional   = $totalIngresosCombinado > 0 ? round($utilidadOperacional / $totalIngresosCombinado * 100, 1) : 0;

        return response()->json([
            'periodo' => ['desde' => $desdeStr, 'hasta' => $hastaStr],

            'ingresos' => [
                'total'    => $totalIngresosCombinado,
                'bsale'    => $totalIngresos,
                'bruto'    => round($ingBruto),
                'iva'      => round($ingBruto - $totalIngresos),
                'cantidad' => $ingCantidad,
                'cobrado'  => $cobradoPeriodo,
                // Ingresos manuales (sin doc SII)
                'manuales_total'    => $totalIngManuales,
                'manuales_cantidad' => $cantidadIngManuales,
                'manuales_por_categoria' => $ingManualesCat,
            ],

            'compras' => [
                'total_neto' => $totalCompras,
                'total_iva'  => round($comprasBruto - $totalCompras),
                'cantidad'   => $comprasCantidad,
                'proveedores' => $topProveedores,
            ],

            'gastos' => [
                'total'         => $totalGastos,
                'cantidad'      => (int) ($gastosTot->cantidad ?? 0),
                'por_categoria' => $gastosCat,
            ],

            'remuneraciones' => [
                'total'            => $totalRemu,
                'sueldos_total'    => $totalSueldos,
                'sueldos_cantidad' => (int) ($remuTot->cantidad ?? 0),
                'previred_total'   => $totalPrevired,
                'previred_cantidad'=> (int) ($previredTot->cantidad ?? 0),
                'por_tipo'         => $remuTipo,
            ],

            'resultados' => [
                'ingresos'             => $totalIngresosCombinado,
                'ingresos_bsale'       => $totalIngresos,
                'ingresos_manuales'    => $totalIngManuales,
                'compras'              => $totalCompras,
                'gastos'               => $totalGastos,
                'remuneraciones'       => $totalRemu,
                'total_egresos'        => $totalEgresos,
                'utilidad_bruta'       => $utilidadBruta,
                'margen_bruto'         => $margenBruto,
                'utilidad_operacional' => $utilidadOperacional,
                'margen_operacional'   => $margenOperacional,
            ],

            'historico' => $modo === 'anual'
                ? $this->historicoAnual($anio)
                : $this->historico(),
            'modo'    => $modo,
            'periodo' => ['desde' => $desdeStr, 'hasta' => $hastaStr, 'modo' => $modo],
        ]);
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
