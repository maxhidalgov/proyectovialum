<?php

namespace App\Http\Controllers;

use App\Models\MovimientoBancario;
use App\Models\ReglaConciliacion;
use App\Models\Compra;
use App\Services\BancochileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConciliacionController extends Controller
{
    // ── Test de conexión ─────────────────────────────────────────────────────

    public function testConexion()
    {
        try {
            $result = (new BancochileService())->getMovimientosPorCantidad(1);
            return response()->json([
                'ok'      => true,
                'titular' => $result['meta']['nombreTitular'] ?? null,
                'cuenta'  => $result['meta']['numeroCuenta'] ?? null,
                'saldo'   => $result['meta']['saldoDisponible'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 502);
        }
    }

    // ── Saldo (último saldo_disponible importado desde movimientos bancarios) ──

    public function saldo()
    {
        $ultimo = DB::table('movimientos_bancarios')
            ->whereNotNull('saldo_disponible')
            ->orderByDesc('fecha_contable')
            ->orderByRaw("COALESCE(fecha_hora_mov, '1900-01-01 00:00:00') DESC")
            ->orderByDesc('id')
            ->select('saldo_disponible', 'fecha_contable', 'fecha_hora_mov', 'cuenta')
            ->first();

        if (!$ultimo) {
            return response()->json([
                'saldoDisponible' => null,
                'fecha'           => null,
                'mensaje'         => 'Sin movimientos con saldo importados aún',
            ]);
        }

        return response()->json([
            'saldoDisponible' => (float) $ultimo->saldo_disponible,
            'fecha'           => $ultimo->fecha_contable,   // YYYY-MM-DD
            'cuenta'          => $ultimo->cuenta,
        ]);
    }

    // ── Importar movimientos desde BCH ───────────────────────────────────────

    public function importar(Request $request)
    {
        $request->validate([
            'desde' => 'required|date_format:Y-m-d',
            'hasta' => 'required|date_format:Y-m-d',
        ]);

        $svc = new BancochileService();

        try {
            $result = $svc->getMovimientosPorPeriodo($request->desde, $request->hasta);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        $movs       = $result['movimientos'];
        $nuevos     = 0;
        $duplicados = 0;
        $errores    = [];

        foreach ($movs as $m) {
            $secuencial    = $m['secuencial'] ?? null;
            $fechaContable = BancochileService::parseDate($m['fechaContable'] ?? null);
            $fechaValor    = BancochileService::parseDate($m['fechaValorTransaccion'] ?? null);
            $tipo          = $m['indicadorCreditoDebito'] ?? 'D';
            $monto         = abs((float) ($m['monto'] ?? 0));
            $nroDoc        = $m['numeroDocumento'] ?: null;
            $descripcion   = $m['descripcionLarga'] ?? $m['descripcionCorta'] ?? '';
            $glosa         = trim($m['campoComplementario8'] ?? '') ?: null;
            $saldo         = isset($m['saldoDisponibleAcumulado']) ? (float) $m['saldoDisponibleAcumulado'] : null;

            // Deduplicar por secuencial (UUID único por movimiento)
            if ($secuencial && MovimientoBancario::where('bch_codigo', $secuencial)->exists()) {
                $duplicados++;
                continue;
            }

            // Fallback sin secuencial: por cuenta+fecha+monto+tipo
            if (!$secuencial) {
                $existe = MovimientoBancario::where('cuenta', $svc->getCuenta())
                    ->where('fecha_contable', $fechaContable)
                    ->where('monto', $monto)
                    ->where('tipo', $tipo)
                    ->when($nroDoc, fn($q) => $q->where('numero_documento', $nroDoc))
                    ->exists();

                if ($existe) {
                    $duplicados++;
                    continue;
                }
            }

            if (!$fechaContable) {
                $errores[] = 'fecha inválida: ' . ($m['fechaContable'] ?? 'null');
                continue;
            }

            try {
                $descTrunc = mb_substr($descripcion, 0, 255);
                $categoria = ReglaConciliacion::categorizar($descTrunc, $tipo);

                MovimientoBancario::create([
                    'cuenta'           => $svc->getCuenta(),
                    'fecha_contable'   => $fechaContable,
                    'fecha_valor'      => $fechaValor,
                    'descripcion'      => $descTrunc,
                    'glosa'            => $glosa,
                    'monto'            => $monto,
                    'tipo'             => $tipo,
                    'numero_documento' => $nroDoc,
                    'saldo_disponible' => $saldo,
                    'bch_codigo'       => $secuencial,
                    'raw'              => $m,
                    'categoria'        => $categoria,
                    'conciliado'       => false,
                ]);
                $nuevos++;
            } catch (\Throwable $e) {
                $errores[] = $e->getMessage();
            }
        }

        return response()->json([
            'total'      => count($movs),
            'nuevos'     => $nuevos,
            'duplicados' => $duplicados,
            'errores'    => $errores,
        ]);
    }

    // ── Importar cartola CSV de Banco de Chile ───────────────────────────────

    public function importarCartola(Request $request)
    {
        $request->validate(['archivo' => 'required|file|max:10240']);

        $path      = $request->file('archivo')->getRealPath();
        $contenido = file_get_contents($path);

        // Banco de Chile exporta en Windows-1252
        if (!mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
        }

        $lineas = preg_split('/\r\n|\r|\n/', $contenido);

        // Línea 1: extraer número de cuenta
        $cabecera = trim($lineas[0] ?? '');
        preg_match('/cta:([\d\-]+)/i', $cabecera, $m);
        $raw    = $m[1] ?? config('services.bch.cuenta', '');
        // Normalizar: quitar guiones + ceros iniciales → mismo formato que portal BCH
        $cuenta = ltrim(preg_replace('/[^0-9]/', '', $raw), '0') ?: $raw;

        $nuevos     = 0;
        $duplicados = 0;
        $errores    = [];

        foreach (array_slice($lineas, 2) as $i => $linea) {
            $linea = trim($linea, " \t\r\n\"");
            if (!$linea) continue;

            $cols = explode(';', $linea);
            if (count($cols) < 4) continue;

            [$fecha, $detalle, $cargo, $abono, $saldo, $docto] = array_pad($cols, 6, '');

            // Fecha: DD/MM/YYYY → YYYY-MM-DD
            $partes = explode('/', trim($fecha));
            if (count($partes) !== 3) continue;
            $fechaContable = trim($partes[2]) . '-' . trim($partes[1]) . '-' . trim($partes[0]);
            if (!strtotime($fechaContable)) continue;

            // Montos: "+0000017400" → entero
            $montoCargoRaw = (int) ltrim(trim($cargo), '+');
            $montoAbonoRaw = (int) ltrim(trim($abono), '+');

            if ($montoCargoRaw > 0) {
                $tipo  = 'D';
                $monto = $montoCargoRaw;
            } elseif ($montoAbonoRaw > 0) {
                $tipo  = 'C';
                $monto = $montoAbonoRaw;
            } else {
                continue;
            }

            $saldoVal    = (int) ltrim(trim($saldo), '+');
            $detalleTrim = trim($detalle);
            $doctoNum    = (int) ltrim(trim($docto), '0+') ?: null;

            // ID determinístico: cuenta+fecha+detalle+cargo+abono+saldo
            $bchCodigo = 'CSV-' . md5($cuenta . $fechaContable . $detalleTrim . $cargo . $abono . $saldo);

            if (MovimientoBancario::where('bch_codigo', $bchCodigo)->exists()) {
                $duplicados++;
                continue;
            }

            // El BCH a veces exporta el mismo depósito con descripción distinta en el mismo CSV
            // (ej: "Dep.cheq.otros Bancos" y "Dep. Docto Otro Bco Autoservicio" para el mismo N°)
            // → deduplicar también por cuenta+fecha+tipo+numero_documento
            if ($doctoNum && MovimientoBancario::where('cuenta', $cuenta)
                ->where('fecha_contable', $fechaContable)
                ->where('tipo', $tipo)
                ->where('numero_documento', $doctoNum)
                ->exists()) {
                $duplicados++;
                continue;
            }

            $categoria = ReglaConciliacion::categorizar(mb_substr($detalleTrim, 0, 100), $tipo);

            try {
                MovimientoBancario::create([
                    'cuenta'           => $cuenta,
                    'fecha_contable'   => $fechaContable,
                    'descripcion'      => mb_substr($detalleTrim, 0, 255),
                    'monto'            => $monto,
                    'tipo'             => $tipo,
                    'numero_documento' => $doctoNum,
                    'saldo_disponible' => $saldoVal,
                    'bch_codigo'       => $bchCodigo,
                    'categoria'        => $categoria,
                    'conciliado'       => false,
                ]);
                $nuevos++;
            } catch (\Throwable $e) {
                $errores[] = 'Fila ' . ($i + 3) . ': ' . $e->getMessage();
            }
        }

        return response()->json([
            'total'      => $nuevos + $duplicados,
            'nuevos'     => $nuevos,
            'duplicados' => $duplicados,
            'errores'    => $errores,
        ]);
    }

    // ── Listar movimientos ───────────────────────────────────────────────────

    public function index(Request $request)
    {
        $q = MovimientoBancario::orderByDesc('fecha_contable')->orderByDesc('id');

        if ($request->filled('desde')) {
            $q->where('fecha_contable', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $q->where('fecha_contable', '<=', $request->hasta);
        }
        if ($request->filled('tipo')) {
            $q->where('tipo', $request->tipo);
        }
        if ($request->filled('conciliado')) {
            $q->where('conciliado', filter_var($request->conciliado, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('categoria')) {
            $q->where('categoria', $request->categoria);
        }
        if ($request->filled('buscar')) {
            $term = '%' . $request->buscar . '%';
            $q->where(function ($q2) use ($term) {
                $q2->where('descripcion', 'like', $term)
                   ->orWhere('glosa', 'like', $term);
            });
        }
        if ($request->filled('monto')) {
            $montoNum = (int) preg_replace('/[^0-9]/', '', $request->monto);
            if ($montoNum > 0) {
                $q->whereRaw('ROUND(ABS(monto)) = ?', [$montoNum]);
            }
        }
        if ($request->filled('cuenta')) {
            $q->where('cuenta', $request->cuenta);
        }

        $movs = $q->paginate(5000);

        // Enriquecer con montos asignados (compras + gastos + sueldos + ventas + ingresos + boletas)
        $ids = $movs->pluck('id');

        $asignadosCompra = DB::table('compra_movimiento')
            ->whereIn('movimiento_id', $ids)
            ->groupBy('movimiento_id')
            ->selectRaw('movimiento_id, SUM(monto) as asignado')
            ->pluck('asignado', 'movimiento_id');

        $asignadosGasto = DB::table('gasto_movimiento')
            ->whereIn('movimiento_id', $ids)
            ->groupBy('movimiento_id')
            ->selectRaw('movimiento_id, SUM(monto) as asignado')
            ->pluck('asignado', 'movimiento_id');

        $asignadosSueldo = DB::table('pagos_empleado')
            ->whereIn('movimiento_id', $ids)
            ->groupBy('movimiento_id')
            ->selectRaw('movimiento_id, SUM(monto) as asignado')
            ->pluck('asignado', 'movimiento_id');

        $asignadosVenta = DB::table('venta_movimiento')
            ->whereIn('movimiento_id', $ids)
            ->groupBy('movimiento_id')
            ->selectRaw('movimiento_id, SUM(monto) as asignado')
            ->pluck('asignado', 'movimiento_id');

        $asignadosIngreso = DB::table('ingreso_movimiento')
            ->whereIn('movimiento_id', $ids)
            ->groupBy('movimiento_id')
            ->selectRaw('movimiento_id, SUM(monto) as asignado')
            ->pluck('asignado', 'movimiento_id');

        // Boletas conciliadas por forma_pago (desde CPC) y por período (desde Conciliación)
        $asignadosBolResumen = DB::table('boleta_resumen_movimiento')
            ->whereIn('movimiento_id', $ids)
            ->groupBy('movimiento_id')
            ->selectRaw('movimiento_id, SUM(monto) as asignado')
            ->pluck('asignado', 'movimiento_id');

        $asignadosBolPeriodo = DB::table('boleta_periodo_movimiento')
            ->whereIn('movimiento_id', $ids)
            ->groupBy('movimiento_id')
            ->selectRaw('movimiento_id, SUM(monto) as asignado')
            ->pluck('asignado', 'movimiento_id');

        // Transbank: monto cubierto por abono vinculado (matchDeposito)
        $asignadosTransbank = DB::table('transbank_abonos')
            ->whereIn('movimiento_bancario_id', $ids)
            ->groupBy('movimiento_bancario_id')
            ->selectRaw('movimiento_bancario_id, SUM(total_abono) as asignado')
            ->pluck('asignado', 'movimiento_bancario_id');

        $movs->getCollection()->transform(function ($mov) use (
            $asignadosCompra, $asignadosGasto, $asignadosSueldo, $asignadosVenta,
            $asignadosIngreso, $asignadosBolResumen, $asignadosBolPeriodo, $asignadosTransbank
        ) {
            $asignado = (float) ($asignadosCompra[$mov->id]     ?? 0)
                      + (float) ($asignadosGasto[$mov->id]      ?? 0)
                      + (float) ($asignadosSueldo[$mov->id]     ?? 0)
                      + (float) ($asignadosVenta[$mov->id]      ?? 0)
                      + (float) ($asignadosIngreso[$mov->id]    ?? 0)
                      + (float) ($asignadosBolResumen[$mov->id] ?? 0)
                      + (float) ($asignadosBolPeriodo[$mov->id] ?? 0)
                      + (float) ($asignadosTransbank[$mov->id]  ?? 0);

            // Movimiento Transbank marcado como conciliado sin abono vinculado aún:
            // tratar como completamente cubierto para evitar mostrar saldo falso.
            if ($mov->conciliado && $mov->categoria === 'Transbank' && $asignado === 0.0) {
                $asignado = (float) $mov->monto;
            }

            $mov->monto_asignado    = $asignado;
            $mov->saldo_por_asignar = max(0, $mov->monto - $asignado);
            return $mov;
        });

        $totalesQ = MovimientoBancario::query();
        if ($request->filled('desde')) {
            $totalesQ->where('fecha_contable', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $totalesQ->where('fecha_contable', '<=', $request->hasta);
        }
        if ($request->filled('cuenta')) {
            $totalesQ->where('cuenta', $request->cuenta);
        }

        $totales = $totalesQ->selectRaw("
            SUM(CASE WHEN tipo='C' THEN monto ELSE 0 END)                      as total_creditos,
            SUM(CASE WHEN tipo='D' THEN monto ELSE 0 END)                      as total_debitos,
            COUNT(*)                                                            as total_movimientos,
            SUM(CASE WHEN tipo='C' THEN 1 ELSE 0 END)                         as total_creditos_count,
            SUM(CASE WHEN tipo='D' THEN 1 ELSE 0 END)                         as total_debitos_count,
            SUM(CASE WHEN conciliado=0 THEN 1 ELSE 0 END)                      as pendientes,
            SUM(CASE WHEN conciliado=1 AND tipo='C' THEN 1 ELSE 0 END)        as conciliados_creditos,
            SUM(CASE WHEN conciliado=1 AND tipo='D' THEN 1 ELSE 0 END)        as conciliados_debitos
        ")->first();

        return response()->json([
            'movimientos' => $movs,
            'totales'     => $totales,
        ]);
    }

    // ── Actualizar (categoría / conciliado / link) ───────────────────────────

    public function update(Request $request, int $id)
    {
        $mov = MovimientoBancario::findOrFail($id);
        $mov->update($request->only([
            'categoria', 'conciliado', 'compra_id', 'cotizacion_id', 'nota',
        ]));
        return response()->json($mov);
    }

    // ── Auto-conciliar débitos vs compras por monto ──────────────────────────

    public function autoConcilar()
    {
        $debitos = MovimientoBancario::where('tipo', 'D')
            ->where('conciliado', false)
            ->whereNull('compra_id')
            ->get();

        $matches = 0;

        foreach ($debitos as $mov) {
            $compra = Compra::whereRaw('ABS(neto - ?) < 500', [$mov->monto])
                ->whereNotExists(function ($q) {
                    $q->from('movimientos_bancarios')
                      ->whereColumn('movimientos_bancarios.compra_id', 'compras.id');
                })
                ->orderByRaw('ABS(neto - ?)', [$mov->monto])
                ->first();

            if ($compra) {
                $mov->update(['compra_id' => $compra->id, 'conciliado' => true]);
                $matches++;
            }
        }

        return response()->json(['matches' => $matches]);
    }

    // ── Sugerencias de conciliación ──────────────────────────────────────────
    //
    // Algoritmo: para cada movimiento no conciliado busca el mejor documento
    // candidato por prioridad: RUT+monto (90pts) > RUT (50pts) > monto (40pts) > descripción+monto (60pts)
    // Solo se sugiere si score >= 40 (mínimo monto exacto).

    public function sugerencias()
    {
        // Movimientos sin asignar (cargos → compras, abonos → ventas)
        $debitos = DB::table('movimientos_bancarios')
            ->where('conciliado', false)
            ->where('tipo', 'D')
            ->orderByDesc('fecha_contable')
            ->limit(300)
            ->get();

        $creditos = DB::table('movimientos_bancarios')
            ->where('conciliado', false)
            ->where('tipo', 'C')
            ->orderByDesc('fecha_contable')
            ->limit(300)
            ->get();

        // Compras con saldo pendiente
        $comprasPendientes = DB::table('compras')
            ->leftJoin(
                DB::raw('(SELECT compra_id, SUM(monto) as pagado FROM compra_movimiento GROUP BY compra_id) as cm'),
                'cm.compra_id', '=', 'compras.id'
            )
            ->where('compras.pagado_historico', false)
            ->selectRaw('compras.id, compras.folio, compras.rut_emisor, compras.nombre_emisor,
                         compras.fecha_emision, compras.total,
                         compras.total - COALESCE(cm.pagado, 0) as saldo_pendiente')
            ->get()
            ->filter(fn($c) => $c->saldo_pendiente > 0);

        // Ventas (documentos_facturacion) con saldo pendiente — excluye boletas
        // Las boletas se concilian por su propio módulo (boleta_periodo_movimiento)
        // y nunca via venta_movimiento para evitar doble conteo con los grupos BOL-*
        $ventasPendientes = DB::table('documentos_facturacion as df')
            ->leftJoin('cotizaciones as cot', 'cot.id', '=', 'df.cotizacion_id')
            ->leftJoin('clientes as cl_dir', 'cl_dir.id', '=', 'df.cliente_id')
            ->leftJoin('clientes as cl_cot', 'cl_cot.id', '=', 'cot.cliente_id')
            ->leftJoin(
                DB::raw('(SELECT venta_id, SUM(monto) as cobrado FROM venta_movimiento GROUP BY venta_id) as vm'),
                'vm.venta_id', '=', 'df.id'
            )
            ->where('df.estado', 'emitido')
            ->whereNotIn('df.tipo_documento_bsale_id', [1, 2]) // boletas (1) y NCs (2) nunca via venta_movimiento
            ->selectRaw("
                df.id, df.tipo, df.monto, df.fecha_emision, df.numero_documento_bsale,
                df.bsale_cliente_rut, df.bsale_cliente_nombre, df.cotizacion_id,
                df.monto - COALESCE(vm.cobrado, 0) as saldo_pendiente,
                COALESCE(cl_dir.razon_social, cl_cot.razon_social, df.bsale_cliente_nombre) as nombre_cliente,
                COALESCE(cl_dir.identification, cl_cot.identification, df.bsale_cliente_rut) as rut_cliente
            ")
            ->get()
            ->filter(fn($v) => $v->saldo_pendiente > 0);

        $sugerencias    = [];
        $usadosMovs     = [];
        $usadosDocs     = [];

        // ── Cargos → Compras ───────────────────────────────────────
        foreach ($debitos as $mov) {
            if (in_array($mov->id, $usadosMovs)) continue;

            $texto = ($mov->descripcion ?? '') . ' ' . ($mov->glosa ?? '');
            $rutMov = $this->extractRut($texto);

            $mejor = null; $razon = null; $score = 0;

            foreach ($comprasPendientes as $comp) {
                if (in_array($comp->id, $usadosDocs)) continue;

                $s = 0; $r = null;

                // RUT match
                if ($rutMov && $comp->rut_emisor &&
                    $this->normalizarRut($rutMov) === $this->normalizarRut($comp->rut_emisor)) {
                    $s += 50; $r = 'rut';
                }

                // Monto exacto
                if (abs($mov->monto - $comp->saldo_pendiente) < 1) {
                    $s += 40; $r = $r ?? 'monto';
                }

                // Nombre en descripción (primeros 8 chars del proveedor)
                if ($comp->nombre_emisor &&
                    mb_stripos($texto, mb_substr($comp->nombre_emisor, 0, 8)) !== false) {
                    $s += 20; $r = $r ?? 'descripcion';
                }

                if ($s > $score && $s >= 40) {
                    $score = $s; $mejor = $comp; $razon = $r;
                }
            }

            if ($mejor) {
                $montoExacto = abs($mov->monto - $mejor->saldo_pendiente) < 1;
                $diasDif     = abs(\Carbon\Carbon::parse($mov->fecha_contable)
                                   ->diffInDays(\Carbon\Carbon::parse($mejor->fecha_emision)));
                $sugerencias[] = [
                    'id'              => "D{$mov->id}_C{$mejor->id}",
                    'movimiento'      => $mov,
                    'documento'       => $mejor,
                    'tipo_documento'  => 'compra',
                    'razon'           => $razon,
                    'score'           => $score,
                    'monto_exacto'    => $montoExacto,
                    'dias_diferencia' => $diasDif,
                    'monto_sugerido'  => (float) min($mov->monto, $mejor->saldo_pendiente),
                ];
                $usadosMovs[] = $mov->id;
                $usadosDocs[] = $mejor->id;
            }
        }

        // ── Créditos → Ventas ──────────────────────────────────────
        foreach ($creditos as $mov) {
            if (in_array($mov->id, $usadosMovs)) continue;

            $texto = ($mov->descripcion ?? '') . ' ' . ($mov->glosa ?? '');
            $rutMov = $this->extractRut($texto);

            $mejor = null; $razon = null; $score = 0; $mejorDias = PHP_INT_MAX;

            foreach ($ventasPendientes as $venta) {
                if (in_array($venta->id, $usadosDocs)) continue;

                $s = 0; $r = null;

                // RUT match
                if ($rutMov && $venta->rut_cliente &&
                    $this->normalizarRut($rutMov) === $this->normalizarRut($venta->rut_cliente)) {
                    $s += 50; $r = 'rut';
                }

                // Monto exacto
                if (abs($mov->monto - $venta->saldo_pendiente) < 1) {
                    $s += 40; $r = $r ?? 'monto';
                }

                // Nombre cliente en descripción
                if ($venta->nombre_cliente &&
                    mb_stripos($texto, mb_substr($venta->nombre_cliente, 0, 8)) !== false) {
                    $s += 20; $r = $r ?? 'descripcion';
                }

                if ($s >= 40) {
                    $dias = abs(\Carbon\Carbon::parse($mov->fecha_contable)
                                ->diffInDays(\Carbon\Carbon::parse($venta->fecha_emision)));
                    // Prefiere mayor score; a igual score, fecha más cercana
                    if ($s > $score || ($s === $score && $dias < $mejorDias)) {
                        $score = $s; $mejor = $venta; $razon = $r; $mejorDias = $dias;
                    }
                }
            }

            if ($mejor) {
                $montoExacto = abs($mov->monto - $mejor->saldo_pendiente) < 1;
                $diasDif     = abs(\Carbon\Carbon::parse($mov->fecha_contable)
                                   ->diffInDays(\Carbon\Carbon::parse($mejor->fecha_emision)));
                $sugerencias[] = [
                    'id'              => "C{$mov->id}_V{$mejor->id}",
                    'movimiento'      => $mov,
                    'documento'       => $mejor,
                    'tipo_documento'  => 'venta',
                    'razon'           => $razon,
                    'score'           => $score,
                    'monto_exacto'    => $montoExacto,
                    'dias_diferencia' => $diasDif,
                    'monto_sugerido'  => (float) min($mov->monto, $mejor->saldo_pendiente),
                ];
                $usadosMovs[] = $mov->id;
                $usadosDocs[] = $mejor->id;
            }
        }

        // Ordenar: 1° monto exacto, 2° fecha más cercana, 3° score más alto
        usort($sugerencias, function ($a, $b) {
            if ($a['monto_exacto'] !== $b['monto_exacto']) {
                return $b['monto_exacto'] <=> $a['monto_exacto'];
            }
            if ($a['dias_diferencia'] !== $b['dias_diferencia']) {
                return $a['dias_diferencia'] <=> $b['dias_diferencia'];
            }
            return $b['score'] <=> $a['score'];
        });

        return response()->json($sugerencias);
    }

    // ── Helpers RUT ──────────────────────────────────────────────────────────

    private function extractRut(string $texto): ?string
    {
        // Sin puntos: 76967670-8 o 12345678-K
        if (preg_match('/\b(\d{7,8}-[\dkK])\b/i', $texto, $m)) {
            return strtoupper($m[1]);
        }
        // Con puntos: 76.967.670-8
        if (preg_match('/\b(\d{1,2}\.\d{3}\.\d{3}-[\dkK])\b/i', $texto, $m)) {
            return strtoupper(str_replace('.', '', $m[1]));
        }
        return null;
    }

    private function normalizarRut(string $rut): string
    {
        return strtoupper(preg_replace('/[^0-9kK-]/', '', $rut));
    }

    // ── Cuentas bancarias distintas ──────────────────────────────────────────

    public function cuentas()
    {
        $cuentas = DB::table('movimientos_bancarios')
            ->selectRaw('cuenta, COUNT(*) as total, MAX(fecha_contable) as ultimo_movimiento')
            ->whereNotNull('cuenta')
            ->groupBy('cuenta')
            ->orderByDesc('total')
            ->get();

        return response()->json($cuentas);
    }

    // ── Flujo de caja mensual ────────────────────────────────────────────────

    public function flujoCaja(Request $request)
    {
        $desde = $request->get('desde', now()->startOfYear()->toDateString());
        $hasta = $request->get('hasta', now()->toDateString());

        $rows = DB::table('movimientos_bancarios')
            ->whereBetween('fecha_contable', [$desde, $hasta])
            ->selectRaw("
                DATE_FORMAT(fecha_contable, '%Y-%m') as mes,
                SUM(CASE WHEN tipo='C' THEN monto ELSE 0 END) as ingresos,
                SUM(CASE WHEN tipo='D' THEN monto ELSE 0 END) as egresos
            ")
            ->groupBy('mes')
            ->orderBy('mes')
            ->get();

        return response()->json($rows);
    }
}
