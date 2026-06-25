<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransbankController extends Controller
{
    // ── GET /api/transbank ────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $periodo = $request->get('periodo'); // YYYY-MM, opcional

        $q = DB::table('transbank_archivos as ta')
            ->select(
                'ta.*',
                DB::raw('(SELECT COUNT(*) FROM transbank_abonos WHERE archivo_id = ta.id) as total_abonos'),
                DB::raw('(SELECT COUNT(*) FROM transbank_abonos WHERE archivo_id = ta.id AND movimiento_bancario_id IS NOT NULL) as abonos_conciliados')
            )
            ->orderByDesc('ta.periodo')
            ->orderBy('ta.tipo');

        if ($periodo) {
            $q->where('ta.periodo', $periodo);
        }

        $archivos = $q->get();

        // Agrupa por periodo para el resumen
        $resumenPeriodos = DB::table('transbank_archivos')
            ->select('periodo')
            ->selectRaw('SUM(total_ventas) as total_ventas')
            ->selectRaw('SUM(total_comision + total_iva_comision + total_servicio + total_iva_servicio) as total_costos')
            ->selectRaw('SUM(total_abono) as total_abono')
            ->when($periodo, fn($q) => $q->where('periodo', $periodo))
            ->groupBy('periodo')
            ->orderByDesc('periodo')
            ->limit(12)
            ->get();

        return response()->json([
            'archivos'        => $archivos,
            'resumen_periodos' => $resumenPeriodos,
        ]);
    }

    // ── GET /api/transbank/resumen-sii?periodo=YYYY-MM ───────────────────────
    // Agrupa por periodo SII (fecha de venta, no de abono) y tipo de tarjeta.
    // Permite ver cuánto se vendió con tarjeta en cada mes contable,
    // independientemente de cuándo Transbank depositó el dinero.

    public function resumenSii(Request $request)
    {
        $periodo = $request->get('periodo', now()->format('Y-m'));

        $rows = DB::table('transbank_transacciones as tt')
            ->join('transbank_abonos as ta', 'ta.id', '=', 'tt.abono_id')
            ->join('transbank_archivos as tf', 'tf.id', '=', 'ta.archivo_id')
            ->where('tf.periodo', $periodo)
            ->where('tt.tipo', 'Venta')
            ->whereNotNull('tt.fecha_movimiento')
            ->selectRaw("
                DATE_FORMAT(tt.fecha_movimiento, '%Y-%m') as sii_periodo,
                tf.tipo as tipo_tarjeta,
                COUNT(*) as cantidad,
                SUM(tt.monto_original) as total_ventas,
                SUM(tt.monto_comision + tt.iva_comision) as total_comision,
                SUM(tt.monto_servicio + tt.iva_servicio) as total_servicio,
                SUM(tt.total_abono) as total_abono_neto
            ")
            ->groupBy(DB::raw("DATE_FORMAT(tt.fecha_movimiento, '%Y-%m')"), 'tf.tipo')
            ->orderBy('sii_periodo')
            ->orderBy('tf.tipo')
            ->get();

        // Incluir cargos por servicio (tipo Servicio, sin fecha_movimiento útil)
        $servicios = DB::table('transbank_transacciones as tt')
            ->join('transbank_abonos as ta', 'ta.id', '=', 'tt.abono_id')
            ->join('transbank_archivos as tf', 'tf.id', '=', 'ta.archivo_id')
            ->where('tf.periodo', $periodo)
            ->where('tt.tipo', 'Servicio')
            ->selectRaw("
                tf.tipo as tipo_tarjeta,
                SUM(tt.monto_servicio + tt.iva_servicio) as total_servicio
            ")
            ->groupBy('tf.tipo')
            ->get()
            ->keyBy('tipo_tarjeta');

        return response()->json([
            'detalle'   => $rows,
            'servicios' => $servicios,
        ]);
    }

    // ── GET /api/transbank/depositos?periodo=YYYY-MM ──────────────────────────
    // Vista consolidada: un depósito por fecha_abono sumando los 3 tipos de tarjeta

    public function depositos(Request $request)
    {
        $periodo = $request->get('periodo', now()->format('Y-m'));

        // Abonos por fecha, sumando credito + debito + prepago
        $depositos = DB::table('transbank_abonos as ta')
            ->join('transbank_archivos as tf', 'tf.id', '=', 'ta.archivo_id')
            ->where('tf.periodo', $periodo)
            ->selectRaw("
                ta.fecha_abono,
                SUM(CASE WHEN tf.tipo = 'credito'  THEN ta.net_abono ELSE 0 END) as credito_neto,
                SUM(CASE WHEN tf.tipo = 'debito'   THEN ta.net_abono ELSE 0 END) as debito_neto,
                SUM(CASE WHEN tf.tipo = 'prepago'  THEN ta.net_abono ELSE 0 END) as prepago_neto,
                SUM(ta.net_abono) as total_neto,
                SUM(ta.total_venta_bruta) as total_venta_bruta,
                SUM(ta.total_comision + ta.total_iva_comision) as total_comision,
                SUM(ta.total_servicio) as total_servicio,
                MAX(ta.movimiento_bancario_id) as movimiento_bancario_id
            ")
            ->groupBy('ta.fecha_abono')
            ->orderBy('ta.fecha_abono')
            ->get();

        // Enriquecer con datos del movimiento bancario
        $movIds = $depositos->pluck('movimiento_bancario_id')->filter()->unique()->values();
        $movimientos = $movIds->isNotEmpty()
            ? DB::table('movimientos_bancarios')
                  ->whereIn('id', $movIds)
                  ->select('id', 'descripcion', 'monto', 'fecha_contable')
                  ->get()
                  ->keyBy('id')
            : collect();

        foreach ($depositos as $dep) {
            $dep->movimiento = $dep->movimiento_bancario_id
                ? ($movimientos[$dep->movimiento_bancario_id] ?? null)
                : null;
        }

        return response()->json($depositos);
    }

    // ── GET /api/transbank/{archivoId}/abonos ─────────────────────────────────
    // Detalle de transacciones de un archivo específico

    public function abonos(int $id)
    {
        $abonos = DB::table('transbank_abonos')
            ->where('archivo_id', $id)
            ->orderBy('fecha_abono')
            ->get();

        $transacciones = DB::table('transbank_transacciones')
            ->whereIn('abono_id', $abonos->pluck('id'))
            ->orderBy('abono_id')
            ->orderBy('fecha_movimiento')
            ->get()
            ->groupBy('abono_id');

        foreach ($abonos as $abono) {
            $abono->transacciones = $transacciones->get($abono->id, collect())->values();
        }

        return response()->json($abonos);
    }

    // ── POST /api/transbank/subir ─────────────────────────────────────────────

    public function subir(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|max:10240',
            'tipo'    => 'required|in:credito,debito,prepago',
            'periodo' => 'required|regex:/^\d{4}-\d{2}$/',
        ]);

        $file     = $request->file('archivo');
        $tipo     = $request->input('tipo');
        $periodo  = $request->input('periodo');

        // Verificar que no exista ya este periodo+tipo
        if (DB::table('transbank_archivos')->where('periodo', $periodo)->where('tipo', $tipo)->exists()) {
            return response()->json([
                'error' => "Ya existe un archivo de tipo '$tipo' para el periodo $periodo. Elimínalo primero.",
            ], 422);
        }

        $contenido = file_get_contents($file->getRealPath());

        // Detectar encoding (Transbank suele usar ISO-8859-1 o Windows-1252)
        if (!mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
        }

        $lineas = preg_split('/\r?\n/', $contenido);

        try {
            [$header, $filas] = $this->parsearArchivo($lineas);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error parseando el archivo: ' . $e->getMessage()], 422);
        }

        DB::beginTransaction();
        try {
            $archivoId = DB::table('transbank_archivos')->insertGetId([
                'periodo'               => $periodo,
                'tipo'                  => $tipo,
                'nombre_archivo'        => $file->getClientOriginalName(),
                'rut_empresa'           => $header['rut'] ?? null,
                'total_ventas'          => $header['total_ventas'] ?? 0,
                'total_comision'        => $header['total_comision'] ?? 0,
                'total_iva_comision'    => $header['total_iva_comision'] ?? 0,
                'total_servicio'        => $header['total_servicio'] ?? 0,
                'total_iva_servicio'    => $header['total_iva_servicio'] ?? 0,
                'total_abono'           => $header['total_abono'] ?? 0,
                'cantidad_transacciones' => $header['cantidad_transacciones'] ?? 0,
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);

            // Agrupar filas por fecha_abono
            $grupos = [];
            foreach ($filas as $fila) {
                $fecha = $fila['fecha_abono'];
                if (!isset($grupos[$fecha])) {
                    $grupos[$fecha] = ['ventas' => [], 'servicios' => []];
                }
                if ($fila['tipo'] === 'Servicio') {
                    $grupos[$fecha]['servicios'][] = $fila;
                } else {
                    $grupos[$fecha]['ventas'][] = $fila;
                }
            }

            foreach ($grupos as $fechaAbono => $grupo) {
                $totalVentaBruta  = array_sum(array_column($grupo['ventas'], 'monto_original'));
                $totalComision    = array_sum(array_column($grupo['ventas'], 'monto_comision'));
                $totalIvaComision = array_sum(array_column($grupo['ventas'], 'iva_comision'));
                $totalVentaNeta   = array_sum(array_column($grupo['ventas'], 'total_abono'));
                $totalServicio    = array_sum(array_column($grupo['servicios'], 'monto_servicio'))
                                  + array_sum(array_column($grupo['servicios'], 'iva_servicio'));
                $netAbono         = $totalVentaNeta - $totalServicio;

                $abonoId = DB::table('transbank_abonos')->insertGetId([
                    'archivo_id'        => $archivoId,
                    'fecha_abono'       => $fechaAbono,
                    'total_venta_bruta' => $totalVentaBruta,
                    'total_comision'    => $totalComision,
                    'total_iva_comision' => $totalIvaComision,
                    'total_venta_neta'  => $totalVentaNeta,
                    'total_servicio'    => $totalServicio,
                    'net_abono'         => $netAbono,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);

                $txRows = [];
                foreach (array_merge($grupo['ventas'], $grupo['servicios']) as $tx) {
                    $txRows[] = [
                        'abono_id'           => $abonoId,
                        'tipo'               => $tx['tipo'],
                        'fecha_movimiento'   => $tx['fecha_movimiento'],
                        'tipo_tarjeta'       => $tx['tipo_tarjeta'],
                        'monto_original'     => $tx['monto_original'],
                        'monto_comision'     => $tx['monto_comision'],
                        'iva_comision'       => $tx['iva_comision'],
                        'total_abono'        => $tx['total_abono'],
                        'monto_servicio'     => $tx['monto_servicio'],
                        'iva_servicio'       => $tx['iva_servicio'],
                        'nro_voucher'        => $tx['nro_voucher'],
                        'codigo_autorizacion' => $tx['codigo_autorizacion'],
                        'tipo_documento'     => $tx['tipo_documento'],
                        'nro_tarjeta'        => $tx['nro_tarjeta'],
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ];
                }

                if ($txRows) {
                    DB::table('transbank_transacciones')->insert($txRows);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('TransbankController::subir error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Error guardando datos: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'success'         => true,
            'archivo_id'      => $archivoId,
            'abonos'          => count($grupos),
            'transacciones'   => count($filas),
        ]);
    }

    // ── DELETE /api/transbank/{id} ────────────────────────────────────────────

    public function destroy(int $id)
    {
        // Cascade borra abonos y transacciones por FK
        DB::table('transbank_archivos')->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }

    // ── POST /api/transbank/auto-match ────────────────────────────────────────
    // Agrupa abonos por fecha_abono (sumando crédito+débito+prepago) y busca
    // el movimiento bancario que coincida con ese total.

    public function autoMatch(Request $request)
    {
        $periodo = $request->get('periodo', now()->format('Y-m'));

        // Fechas pendientes: suma de los 3 tipos por fecha
        $fechasPendientes = DB::table('transbank_abonos as ta')
            ->join('transbank_archivos as tf', 'tf.id', '=', 'ta.archivo_id')
            ->where('tf.periodo', $periodo)
            ->whereNull('ta.movimiento_bancario_id')
            ->selectRaw('ta.fecha_abono, SUM(ta.net_abono) as total_neto')
            ->groupBy('ta.fecha_abono')
            ->get();

        $matched = 0;

        foreach ($fechasPendientes as $fecha) {
            // Tolerancia de ±100 para cubrir diferencias de redondeo entre tipos
            $movimiento = DB::table('movimientos_bancarios')
                ->where('tipo', 'C')
                ->where('fecha_contable', $fecha->fecha_abono)
                ->whereBetween('monto', [$fecha->total_neto - 100, $fecha->total_neto + 100])
                // No usar si ya está asignado a una fecha distinta
                ->whereNotExists(function ($q) use ($fecha) {
                    $q->select(DB::raw(1))
                      ->from('transbank_abonos')
                      ->whereColumn('transbank_abonos.movimiento_bancario_id', 'movimientos_bancarios.id')
                      ->where('transbank_abonos.fecha_abono', '<>', $fecha->fecha_abono);
                })
                ->first();

            if ($movimiento) {
                // Asignar a TODOS los abonos de esa fecha (credito + debito + prepago)
                $affected = DB::table('transbank_abonos as ta')
                    ->join('transbank_archivos as tf', 'tf.id', '=', 'ta.archivo_id')
                    ->where('tf.periodo', $periodo)
                    ->where('ta.fecha_abono', $fecha->fecha_abono)
                    ->whereNull('ta.movimiento_bancario_id')
                    ->update([
                        'ta.movimiento_bancario_id' => $movimiento->id,
                        'ta.updated_at'             => now(),
                    ]);
                if ($affected > 0) {
                    // Marcar el movimiento bancario como conciliado
                    DB::table('movimientos_bancarios')
                        ->where('id', $movimiento->id)
                        ->update([
                            'conciliado' => true,
                            'categoria'  => 'Transbank',
                            'updated_at' => now(),
                        ]);
                    $matched++;
                }
            }
        }

        return response()->json([
            'success'   => true,
            'revisados' => $fechasPendientes->count(),
            'matched'   => $matched,
        ]);
    }

    // ── POST /api/transbank/deposito/match ────────────────────────────────────
    // Asigna (o quita) un movimiento bancario a TODOS los abonos de una fecha+periodo

    public function matchDeposito(Request $request)
    {
        $request->validate([
            'fecha_abono'           => 'required|date',
            'periodo'               => 'required|regex:/^\d{4}-\d{2}$/',
            'movimiento_bancario_id' => 'nullable|exists:movimientos_bancarios,id',
        ]);

        $fecha   = $request->input('fecha_abono');
        $periodo = $request->input('periodo');
        $movId   = $request->input('movimiento_bancario_id');

        // Obtener el movimiento anterior (para desmarcar si se quita el vínculo)
        $movIdAnterior = DB::table('transbank_abonos as ta')
            ->join('transbank_archivos as tf', 'tf.id', '=', 'ta.archivo_id')
            ->where('tf.periodo', $periodo)
            ->where('ta.fecha_abono', $fecha)
            ->whereNotNull('ta.movimiento_bancario_id')
            ->value('ta.movimiento_bancario_id');

        DB::table('transbank_abonos as ta')
            ->join('transbank_archivos as tf', 'tf.id', '=', 'ta.archivo_id')
            ->where('tf.periodo', $periodo)
            ->where('ta.fecha_abono', $fecha)
            ->update([
                'ta.movimiento_bancario_id' => $movId,
                'ta.updated_at'             => now(),
            ]);

        if ($movId) {
            // Vincular: marcar movimiento como conciliado - Transbank
            DB::table('movimientos_bancarios')
                ->where('id', $movId)
                ->update([
                    'conciliado' => true,
                    'categoria'  => 'Transbank',
                    'updated_at' => now(),
                ]);
        }

        if ($movIdAnterior && $movIdAnterior !== $movId) {
            // Desvincular anterior: verificar que no tenga otras conciliaciones
            $sigueUsado = DB::table('transbank_abonos')
                ->where('movimiento_bancario_id', $movIdAnterior)
                ->exists();
            if (!$sigueUsado) {
                DB::table('movimientos_bancarios')
                    ->where('id', $movIdAnterior)
                    ->update([
                        'conciliado' => false,
                        'categoria'  => null,
                        'updated_at' => now(),
                    ]);
            }
        }

        return response()->json(['success' => true]);
    }

    // ── Parser interno ────────────────────────────────────────────────────────

    // ── GET /api/transbank/resumen-documentos?periodo=YYYY-MM ────────────────
    // Vista mensual estilo Chipax: boletas tarjeta + facturas tarjeta + diferencia.

    public function resumenDocumentos(Request $request)
    {
        $periodo = $request->get('periodo', now()->format('Y-m'));
        [$y, $m] = explode('-', $periodo);
        $desde = "$periodo-01";
        $hasta = Carbon::create((int) $y, (int) $m, 1)->endOfMonth()->toDateString();

        // 1. Totales del período.
        //
        // ventas_brutas: suma por FECHA DE VENTA (fecha_movimiento de la transacción),
        //   no por período del archivo. Así una venta del 31-ene aparece en enero aunque
        //   su abono llegue en febrero. Permite comparar peras con peras vs facturas/boletas.
        //
        // comisiones / total_abonado: siguen por período del archivo (fecha de liquidación),
        //   ya que reflejan cuándo Transbank cobró/depositó — números de caja del mes.

        $ventasBrutas = (float) DB::table('transbank_transacciones as tt')
            ->join('transbank_abonos as ta', 'ta.id', '=', 'tt.abono_id')
            ->join('transbank_archivos as tf', 'tf.id', '=', 'ta.archivo_id')
            ->where('tt.tipo', 'Venta')
            ->whereNotNull('tt.fecha_movimiento')
            ->whereRaw("DATE_FORMAT(tt.fecha_movimiento, '%Y-%m') = ?", [$periodo])
            ->sum('tt.monto_original');

        $dat = DB::table('transbank_archivos')
            ->where('periodo', $periodo)
            ->selectRaw('
                SUM(total_comision + total_iva_comision + total_servicio + total_iva_servicio) as comisiones,
                SUM(total_abono) as total_abonado
            ')
            ->first();

        // 2. Boletas tarjeta (solo tarjeta_credito + tarjeta_debito)
        $boletasTarjeta = DB::table('boleta_resumenes')
            ->where('periodo', $periodo)
            ->whereIn('forma_pago', ['tarjeta_credito', 'tarjeta_debito'])
            ->select('id', 'forma_pago', 'total_boletas', 'monto_total', 'conciliado_transbank')
            ->orderBy('forma_pago')
            ->get();

        // 3. Facturas tarjeta del período — agrupadas por documento para evitar duplicados
        //    cuando un documento tiene múltiples transacciones Transbank vinculadas.
        $facturas = DB::table('documentos_facturacion as df')
            ->leftJoin('clientes as cl', 'cl.id', '=', 'df.cliente_id')
            ->where('df.estado', 'emitido')
            ->whereNotIn('df.tipo_documento_bsale_id', [1])
            ->where('df.pagado_con_tarjeta', 1)
            ->whereBetween('df.fecha_emision', [$desde, $hasta])
            ->select(
                'df.id',
                'df.numero_documento_bsale',
                'df.monto',
                'df.fecha_emision',
                'df.tipo_documento_bsale_id',
                'df.nro_comprobante_transbank',
                'df.url_pdf_bsale',
                DB::raw('COALESCE(cl.razon_social, df.bsale_cliente_nombre) as cliente'),
                DB::raw('COALESCE((
                    SELECT SUM(tt2.monto_original)
                    FROM transbank_factura tvf2
                    JOIN transbank_transacciones tt2 ON tt2.id = tvf2.transaccion_id
                    WHERE tvf2.documento_id = df.id
                ), 0) as monto_vinculado'),
                DB::raw('(
                    SELECT GROUP_CONCAT(tt2.nro_voucher ORDER BY tt2.id SEPARATOR ", ")
                    FROM transbank_factura tvf2
                    JOIN transbank_transacciones tt2 ON tt2.id = tvf2.transaccion_id
                    WHERE tvf2.documento_id = df.id
                ) as vouchers_vinculados')
            )
            ->orderByDesc('df.fecha_emision')
            ->get();

        // 4. Transacciones sin documento vinculado.
        //    Excluye: BOLETA (se concilian a nivel período), ajustes Transbank (cod_autorizacion = '000000'),
        //    y transacciones que ya tienen un ingreso manual registrado.
        $sinDocBase = DB::table('transbank_transacciones as tt')
            ->join('transbank_abonos as ta', 'ta.id', '=', 'tt.abono_id')
            ->join('transbank_archivos as tf', 'tf.id', '=', 'ta.archivo_id')
            ->leftJoin('transbank_factura as tvf', 'tvf.transaccion_id', '=', 'tt.id')
            ->leftJoin('ingresos_manuales as im', 'im.transbank_transaccion_id', '=', 'tt.id')
            ->where('tt.tipo', 'Venta')
            ->whereNotNull('tt.fecha_movimiento')
            ->whereRaw("DATE_FORMAT(tt.fecha_movimiento, '%Y-%m') = ?", [$periodo])
            ->where(fn($q) => $q->whereNull('tt.codigo_autorizacion')->orWhere('tt.codigo_autorizacion', '!=', '000000'))
            ->whereNull('tvf.transaccion_id')
            ->whereNull('im.id');

        $sinDocRows = (clone $sinDocBase)
            // Intenta inferir el tipo buscando un doc de Bsale con voucher coincidente (no vinculado aún)
            ->leftJoin('documentos_facturacion as df_inf', function ($j) {
                $j->whereRaw('CAST(df_inf.nro_comprobante_transbank AS UNSIGNED) = CAST(tt.nro_voucher AS UNSIGNED)')
                  ->whereRaw('tt.nro_voucher IS NOT NULL AND tt.nro_voucher != ""');
            })
            ->select(
                'tt.id',
                'tt.fecha_movimiento',
                'tt.monto_original',
                'tt.tipo_tarjeta',
                'tt.nro_voucher',
                'tf.tipo as medio_pago',
                DB::raw('df_inf.tipo_documento_bsale_id as bsale_tipo_inferido')
            )
            ->orderByDesc('tt.fecha_movimiento')
            ->get();

        $totalBoletas  = (float) $boletasTarjeta->sum('monto_total');
        $totalFacturas = (float) $facturas->sum('monto');
        $totalDocs     = $totalBoletas + $totalFacturas;

        return response()->json([
            'ventas_brutas'    => $ventasBrutas,
            'comisiones'       => (float) ($dat->comisiones ?? 0),
            'total_abonado'    => (float) ($dat->total_abonado ?? 0),
            'boletas_tarjeta'  => $boletasTarjeta,
            'facturas'         => $facturas,
            'sin_doc'          => [
                'count' => $sinDocRows->count(),
                'monto' => (float) $sinDocRows->sum('monto_original'),
                'rows'  => $sinDocRows,
            ],
            'total_documentos' => $totalDocs,
            'diferencia'       => $ventasBrutas - $totalDocs,
        ]);
    }

    // ── GET /api/transbank/transacciones-sin-doc?periodo=YYYY-MM&monto=X ────────
    // Lista transacciones Venta sin documento vinculado, ordenadas por proximidad a monto.

    public function transaccionesSinDoc(Request $request)
    {
        $periodo = $request->get('periodo', now()->format('Y-m'));
        $monto   = $request->get('monto');

        $base = DB::table('transbank_transacciones as tt')
            ->join('transbank_abonos as ta', 'ta.id', '=', 'tt.abono_id')
            ->join('transbank_archivos as tf', 'tf.id', '=', 'ta.archivo_id')
            ->leftJoin('transbank_factura as tvf', 'tvf.transaccion_id', '=', 'tt.id')
            ->leftJoin('ingresos_manuales as im', 'im.transbank_transaccion_id', '=', 'tt.id')
            ->whereNotNull('tt.fecha_movimiento')
            ->whereRaw("DATE_FORMAT(tt.fecha_movimiento, '%Y-%m') = ?", [$periodo])
            ->where('tt.tipo', 'Venta')
            ->whereNull('tvf.transaccion_id')
            ->whereNull('im.id')
            ->select(
                'tt.id',
                'tt.fecha_movimiento',
                'tt.monto_original',
                'tt.tipo_tarjeta',
                'tt.nro_voucher',
                'tf.tipo as medio_pago'
            );

        if ($monto) {
            // Primero intentar match exacto de monto
            $exactos = (clone $base)
                ->where('tt.monto_original', (int) $monto)
                ->orderByDesc('tt.fecha_movimiento')
                ->limit(20)
                ->get();

            if ($exactos->isNotEmpty()) {
                return response()->json($exactos);
            }

            // Si no hay exactos, mostrar los más cercanos en monto (máximo 10)
            return response()->json(
                (clone $base)
                    ->orderByRaw('ABS(tt.monto_original - ?) ASC', [(int) $monto])
                    ->limit(10)
                    ->get()
            );
        }

        return response()->json($base->orderByDesc('tt.fecha_movimiento')->limit(50)->get());
    }

    // ── GET /api/transbank/documentos?periodo=YYYY-MM ─────────────────────────
    // Devuelve las transacciones FACTURA/N/A (no BOLETA) con su documento vinculado,
    // más el resumen de boletas del periodo.

    public function documentos(Request $request)
    {
        $periodo = $request->get('periodo', now()->format('Y-m'));

        // Todas las transacciones de venta — el tipo_documento del .dat no es fiable
        // para distinguir boleta/factura; usamos el documento Bsale vinculado para eso.
        $facturas = DB::table('transbank_transacciones as tt')
            ->join('transbank_abonos as ta', 'ta.id', '=', 'tt.abono_id')
            ->join('transbank_archivos as tf', 'tf.id', '=', 'ta.archivo_id')
            ->leftJoin('transbank_factura as tvf', 'tvf.transaccion_id', '=', 'tt.id')
            ->leftJoin('documentos_facturacion as df', 'df.id', '=', 'tvf.documento_id')
            ->where('tf.periodo', $periodo)
            ->where('tt.tipo', 'Venta')
            ->select(
                'tt.id',
                'tt.fecha_movimiento',
                'tt.monto_original',
                'tt.total_abono',
                'tt.tipo_tarjeta',
                'tt.nro_voucher',
                'tt.tipo_documento',
                'tf.tipo as medio_pago',
                'ta.fecha_abono',
                'tvf.documento_id',
                DB::raw('df.numero_documento_bsale as doc_numero'),
                DB::raw('df.monto as doc_monto'),
                DB::raw('df.fecha_emision as doc_fecha'),
                DB::raw('df.tipo_documento_bsale_id as doc_tipo_bsale'),
                DB::raw('df.bsale_cliente_nombre as doc_cliente')
            )
            ->orderBy('tt.fecha_movimiento')
            ->get();

        // Resumen agregado de boletas del período (desde boleta_resumenes)
        $boletaRow = DB::table('boleta_resumenes')
            ->where('periodo', $periodo)
            ->selectRaw('SUM(total_boletas) as count, SUM(monto_total) as monto')
            ->first();

        $boletaCobrado = DB::table('boleta_periodo_movimiento')
            ->where('periodo', $periodo)
            ->sum('monto');

        $boletas = $boletaRow && $boletaRow->count
            ? [
                'count'      => (int)   $boletaRow->count,
                'monto'      => (float) $boletaRow->monto,
                'cobrado'    => (float) $boletaCobrado,
                'pendiente'  => max(0, (float) $boletaRow->monto - (float) $boletaCobrado),
                'conciliado' => (float) $boletaCobrado >= (float) $boletaRow->monto,
            ]
            : null;

        return response()->json([
            'facturas' => $facturas,
            'boletas'  => $boletas,
        ]);
    }

    // ── POST /api/transbank/auto-link?periodo=YYYY-MM ─────────────────────────
    // Auto-vincula transacciones FACTURA a documentos por monto exacto + fecha ±2 días.

    public function autoLink(Request $request)
    {
        $periodo = $request->get('periodo', now()->format('Y-m'));

        $pendientes = DB::table('transbank_transacciones as tt')
            ->join('transbank_abonos as ta', 'ta.id', '=', 'tt.abono_id')
            ->join('transbank_archivos as tf', 'tf.id', '=', 'ta.archivo_id')
            ->leftJoin('transbank_factura as tvf', 'tvf.transaccion_id', '=', 'tt.id')
            ->whereNotNull('tt.fecha_movimiento')
            ->whereRaw("DATE_FORMAT(tt.fecha_movimiento, '%Y-%m') = ?", [$periodo])
            ->where('tt.tipo', 'Venta')
            ->whereNull('tvf.transaccion_id')
            ->select('tt.id', 'tt.monto_original', 'tt.fecha_movimiento', 'tt.nro_voucher')
            ->get();

        $linked = 0;

        foreach ($pendientes as $tx) {
            $doc        = null;
            $fechaVenta = Carbon::parse($tx->fecha_movimiento);

            // 1) Match primario: Nº Comprobante exacto (solo facturas, no boletas)
            if (!empty($tx->nro_voucher)) {
                $doc = DB::table('documentos_facturacion as df')
                    ->leftJoin('transbank_factura as tvf', 'tvf.documento_id', '=', 'df.id')
                    ->whereNull('tvf.documento_id')
                    ->where('df.estado', 'emitido')
                    ->whereNotIn('df.tipo_documento_bsale_id', [1])
                    ->whereNotNull('df.nro_comprobante_transbank')
                    ->whereRaw(
                        'CAST(df.nro_comprobante_transbank AS UNSIGNED) = CAST(? AS UNSIGNED)',
                        [$tx->nro_voucher]
                    )
                    ->select('df.id')
                    ->first();
            }

            // 2) Fallback: monto exacto + fecha ±7 días, solo si hay match ÚNICO.
            //    Si hay más de un candidato con ese monto en esa ventana → no auto-vincular
            //    (ambigüedad, el operador debe hacerlo manualmente).
            if (!$doc) {
                $candidatos = DB::table('documentos_facturacion as df')
                    ->leftJoin('transbank_factura as tvf', 'tvf.documento_id', '=', 'df.id')
                    ->whereNull('tvf.documento_id')
                    ->where('df.estado', 'emitido')
                    ->whereNotIn('df.tipo_documento_bsale_id', [1])
                    ->where('df.pagado_con_tarjeta', 1)
                    ->whereNull('df.nro_comprobante_transbank')
                    ->where('df.monto', (int) $tx->monto_original)
                    ->whereBetween('df.fecha_emision', [
                        $fechaVenta->copy()->subDays(7)->toDateString(),
                        $fechaVenta->copy()->addDays(7)->toDateString(),
                    ])
                    ->select('df.id', 'df.fecha_emision')
                    ->get();

                // Solo vincular si hay exactamente un candidato (sin ambigüedad)
                if ($candidatos->count() === 1) {
                    $doc = $candidatos->first();
                }
            }

            if ($doc) {
                DB::table('transbank_factura')->insert([
                    'transaccion_id' => $tx->id,
                    'documento_id'   => $doc->id,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
                $linked++;
            }
        }

        return response()->json([
            'success'   => true,
            'revisadas' => $pendientes->count(),
            'linked'    => $linked,
        ]);
    }

    // ── POST /api/transbank/auto-link-boletas?periodo=YYYY-MM ─────────────────
    // Auto-vincula boletas pagadas con tarjeta a transacciones Transbank.
    // Estrategia 1: voucher exacto (nro_comprobante_transbank = nro_voucher).
    // Estrategia 2: monto exacto + fecha ±1 día, solo si el candidato es único.

    public function autoLinkBoletas(Request $request)
    {
        $periodo = $request->get('periodo', now()->format('Y-m'));

        $boletas = DB::table('documentos_facturacion as df')
            ->leftJoin('transbank_factura as tvf', 'tvf.documento_id', '=', 'df.id')
            ->whereNull('tvf.documento_id')
            ->where('df.estado', 'emitido')
            ->whereIn('df.tipo_documento_bsale_id', [1])
            ->where('df.pagado_con_tarjeta', 1)
            ->whereRaw("DATE_FORMAT(df.fecha_emision, '%Y-%m') = ?", [$periodo])
            ->select('df.id', 'df.monto', 'df.fecha_emision', 'df.nro_comprobante_transbank')
            ->get();

        $linked = 0;

        foreach ($boletas as $boleta) {
            $tx           = null;
            $fechaEmision = Carbon::parse($boleta->fecha_emision);

            // 1) Match por voucher
            if (!empty($boleta->nro_comprobante_transbank)) {
                $tx = DB::table('transbank_transacciones as tt')
                    ->join('transbank_abonos as ta', 'ta.id', '=', 'tt.abono_id')
                    ->leftJoin('transbank_factura as tvf2', 'tvf2.transaccion_id', '=', 'tt.id')
                    ->whereNull('tvf2.transaccion_id')
                    ->where('tt.tipo', 'Venta')
                    ->whereRaw("DATE_FORMAT(tt.fecha_movimiento, '%Y-%m') = ?", [$periodo])
                    ->whereRaw(
                        'CAST(tt.nro_voucher AS UNSIGNED) = CAST(? AS UNSIGNED)',
                        [$boleta->nro_comprobante_transbank]
                    )
                    ->select('tt.id')
                    ->first();
            }

            // 2) Fallback: monto exacto + fecha ±1 día, solo si candidato es ÚNICO
            if (!$tx) {
                $candidatos = DB::table('transbank_transacciones as tt')
                    ->join('transbank_abonos as ta', 'ta.id', '=', 'tt.abono_id')
                    ->leftJoin('transbank_factura as tvf2', 'tvf2.transaccion_id', '=', 'tt.id')
                    ->whereNull('tvf2.transaccion_id')
                    ->where('tt.tipo', 'Venta')
                    ->whereRaw("DATE_FORMAT(tt.fecha_movimiento, '%Y-%m') = ?", [$periodo])
                    ->where('tt.monto_original', (int) $boleta->monto)
                    ->whereBetween('tt.fecha_movimiento', [
                        $fechaEmision->copy()->subDay()->toDateString(),
                        $fechaEmision->copy()->addDays(2)->toDateString(),
                    ])
                    ->select('tt.id')
                    ->get();

                if ($candidatos->count() === 1) {
                    $tx = $candidatos->first();
                }
            }

            if ($tx) {
                DB::table('transbank_factura')->insertOrIgnore([
                    'transaccion_id' => $tx->id,
                    'documento_id'   => $boleta->id,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
                $linked++;
            }
        }

        return response()->json([
            'success'   => true,
            'revisadas' => $boletas->count(),
            'linked'    => $linked,
        ]);
    }

    // ── POST /api/transbank/transaccion/{id}/link ─────────────────────────────

    public function linkDocumento(Request $request, int $id)
    {
        $request->validate(['documento_id' => 'required|exists:documentos_facturacion,id']);

        $docId = $request->input('documento_id');

        // Si ya existe un vínculo para esta transacción, actualizarlo.
        // Si no, insertar uno nuevo (documento_id ya no es unique → permite multi-tx por doc).
        $existing = DB::table('transbank_factura')->where('transaccion_id', $id)->first();
        if ($existing) {
            DB::table('transbank_factura')
                ->where('transaccion_id', $id)
                ->update(['documento_id' => $docId, 'updated_at' => now()]);
        } else {
            DB::table('transbank_factura')->insert([
                'transaccion_id' => $id,
                'documento_id'   => $docId,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

        return response()->json(['success' => true]);
    }

    // ── DELETE /api/transbank/transaccion/{id}/link ───────────────────────────

    public function unlinkDocumento(int $id)
    {
        DB::table('transbank_factura')->where('transaccion_id', $id)->delete();
        return response()->json(['success' => true]);
    }

    // DELETE /api/transbank/documento/{id}/links — elimina TODOS los vínculos Transbank de un documento
    public function unlinkDocumentoLinks(int $id)
    {
        $deleted = DB::table('transbank_factura')->where('documento_id', $id)->delete();
        return response()->json(['success' => true, 'deleted' => $deleted]);
    }

    // ── GET /api/transbank/facturas-disponibles ───────────────────────────────
    // Documentos de facturación emitidos que aún no están vinculados a ninguna transacción Transbank.

    public function facturasDisponibles(Request $request)
    {
        $q       = $request->get('q', '');
        $monto   = $request->get('monto');
        $periodo = $request->get('periodo', now()->format('Y-m'));

        [$y, $m] = explode('-', $periodo);
        $desde = Carbon::create((int) $y, (int) $m, 1)->subMonths(2)->startOfMonth()->toDateString();
        $hasta = Carbon::create((int) $y, (int) $m, 1)->addMonths(2)->endOfMonth()->toDateString();

        $soloTarjeta = $request->get('solo_tarjeta', '1') === '1';

        $query = DB::table('documentos_facturacion as df')
            ->where('df.estado', 'emitido')
            ->whereNotNull('df.numero_documento_bsale')
            ->whereBetween('df.fecha_emision', [$desde, $hasta])
            ->when($soloTarjeta, fn($q2) => $q2->where('df.pagado_con_tarjeta', 1))
            // Solo mostrar documentos donde el monto Transbank ya vinculado < monto del documento
            // (permite multi-tx pero excluye los que ya tienen cobertura completa)
            ->whereRaw('df.monto > COALESCE((
                SELECT SUM(tt2.monto_original)
                FROM transbank_factura tvf2
                JOIN transbank_transacciones tt2 ON tt2.id = tvf2.transaccion_id
                WHERE tvf2.documento_id = df.id
            ), 0)')
            ->select(
                'df.id',
                'df.numero_documento_bsale',
                'df.bsale_cliente_nombre',
                'df.monto',
                'df.fecha_emision',
                'df.tipo_documento_bsale_id',
                'df.nro_comprobante_transbank',
                'df.pagado_con_tarjeta',
                DB::raw('(SELECT COUNT(*) FROM transbank_factura WHERE documento_id = df.id) as tx_linked_count'),
                DB::raw('COALESCE((
                    SELECT SUM(tt2.monto_original)
                    FROM transbank_factura tvf2
                    JOIN transbank_transacciones tt2 ON tt2.id = tvf2.transaccion_id
                    WHERE tvf2.documento_id = df.id
                ), 0) as monto_ya_vinculado')
            );

        if ($q) {
            $query->where(function ($sq) use ($q) {
                $sq->where('df.numero_documento_bsale', 'like', "%{$q}%")
                   ->orWhere('df.bsale_cliente_nombre', 'like', "%{$q}%");
            });
        }

        if ($monto) {
            $query->orderByRaw('ABS(df.monto - ?) ASC', [(int) $monto]);
        } else {
            $query->orderByDesc('df.fecha_emision');
        }

        return response()->json($query->limit(50)->get());
    }

    // ── Parser interno ────────────────────────────────────────────────────────

    private function parsearArchivo(array $lineas): array
    {
        $header       = [];
        $columnasIdx  = null;
        $filas        = [];
        $enDatos      = false;

        // Mapeo de columnas del header plano
        $headerMap = [
            'Rut'                                => 'rut',
            'Monto ventas validas para abono (+)' => 'total_ventas',
            'Comision Transbank (-)'              => 'total_comision',
            'IVA comision Transbank (-)'          => 'total_iva_comision',
            'Monto cobros por servicio (-)'       => 'total_servicio',
            'IVA de cobros por servicio (-)'      => 'total_iva_servicio',
            'Total abono'                         => 'total_abono',
            'Cantidad de transacciones abono'     => 'cantidad_transacciones',
        ];

        // Índices de columnas de datos (posición en la fila separada por ;)
        $COL = [
            'tipo'         => 0,
            'fecha_mov'    => 1,
            'fecha_abono'  => 3,
            'tipo_tarjeta' => 8,
            'monto_orig'   => 9,
            'monto_valido' => 16,
            'comision'     => 17,
            'iva_comision' => 18,
            'total_abono'  => 19,
            'monto_cobro'  => 27,
            'iva_cobro'    => 28,
            'nro_tarjeta'  => 33,
            'cod_autorizacion' => 34,
            'nro_voucher'  => 40,
            'tipo_doc'     => 41,
        ];

        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if ($linea === '' || $linea === ' ') continue;

            // Detectar fila de columnas de datos
            if (str_starts_with($linea, 'Tipo de movimiento;')) {
                $enDatos = true;
                continue;
            }

            if (!$enDatos) {
                // Parsear header key;value
                $parts = explode(';', $linea, 2);
                $key   = trim($parts[0]);
                $val   = isset($parts[1]) ? trim($parts[1]) : '';

                if (isset($headerMap[$key])) {
                    $field = $headerMap[$key];
                    $header[$field] = in_array($field, ['rut'])
                        ? $val
                        : $this->parseMonto($val);
                }
                continue;
            }

            // Fila de datos
            $cols = explode(';', $linea);
            if (count($cols) < 20) continue;

            $tipo = trim($cols[$COL['tipo']] ?? '');
            if (!in_array($tipo, ['Venta', 'Servicio', 'Anulacion'])) continue;

            $fechaMovStr = trim($cols[$COL['fecha_mov']] ?? '');
            $fechaMov    = null;
            if ($fechaMovStr && $fechaMovStr !== 'N/A') {
                try {
                    $fechaMov = Carbon::createFromFormat('d/m/Y H:i', $fechaMovStr)->toDateTimeString();
                } catch (\Throwable) {}
            }

            $fechaAbonoStr = trim($cols[$COL['fecha_abono']] ?? '');
            $fechaAbono    = null;
            if ($fechaAbonoStr && $fechaAbonoStr !== 'N/A') {
                try {
                    $fechaAbono = Carbon::createFromFormat('d/m/Y', $fechaAbonoStr)->toDateString();
                } catch (\Throwable) {}
            }

            if (!$fechaAbono) continue;

            $nroVoucher = trim($cols[$COL['nro_voucher']] ?? '');
            if ($nroVoucher === 'N/A' || $nroVoucher === '') {
                $nroVoucher = null;
            } else {
                // Normalizar: "0000003422" → "3422"
                $nroVoucher = ltrim($nroVoucher, '0') ?: null;
            }

            $tipoDoc = trim($cols[$COL['tipo_doc']] ?? '');
            if ($tipoDoc === 'N/A' || $tipoDoc === '') $tipoDoc = null;

            $nroTarjeta = trim($cols[$COL['nro_tarjeta']] ?? '');
            if ($nroTarjeta === 'N/A' || $nroTarjeta === '') $nroTarjeta = null;

            $codAut = trim($cols[$COL['cod_autorizacion']] ?? '');
            if ($codAut === 'N/A' || $codAut === '') $codAut = null;

            $tipoTarjeta = trim($cols[$COL['tipo_tarjeta']] ?? '');
            if ($tipoTarjeta === 'N/A' || $tipoTarjeta === '') $tipoTarjeta = null;

            $filas[] = [
                'tipo'               => $tipo,
                'fecha_movimiento'   => $fechaMov,
                'fecha_abono'        => $fechaAbono,
                'tipo_tarjeta'       => $tipoTarjeta,
                'monto_original'     => $this->parseMonto($cols[$COL['monto_orig']] ?? '0'),
                'monto_comision'     => $this->parseMonto($cols[$COL['comision']] ?? '0'),
                'iva_comision'       => $this->parseMonto($cols[$COL['iva_comision']] ?? '0'),
                'total_abono'        => $this->parseMonto($cols[$COL['total_abono']] ?? '0'),
                'monto_servicio'     => $this->parseMonto($cols[$COL['monto_cobro']] ?? '0'),
                'iva_servicio'       => $this->parseMonto($cols[$COL['iva_cobro']] ?? '0'),
                'nro_voucher'        => $nroVoucher,
                'codigo_autorizacion' => $codAut,
                'tipo_documento'     => $tipoDoc,
                'nro_tarjeta'        => $nroTarjeta,
            ];
        }

        if (empty($filas) && $enDatos) {
            // El archivo solo tiene header (sin transacciones, quizá mes sin movimientos)
        }

        return [$header, $filas];
    }

    private function parseMonto(string $s): int
    {
        // "9.342.820" → 9342820, "127.770" → 127770
        $clean = str_replace(['.', ',', ' '], '', trim($s));
        return (int) ($clean ?: 0);
    }

    // ── POST /api/transbank/chipax-csv ────────────────────────────────────────
    // Recibe filas del XLSX de conciliación avanzada de Chipax (parseado en el
    // browser con SheetJS) y crea venta_movimiento para cada factura vinculada.
    //
    // Flujo esperado:
    //  1. Subir .dat de Transbank → transbank_abonos.movimiento_bancario_id queda seteado
    //  2. Subir XLSX de Chipax → este endpoint busca el movimiento por periodo
    //     y crea venta_movimiento (factura ↔ movimiento_bancario)

    public function importarChipaxCsv(Request $request)
    {
        $dry  = filter_var($request->get('dry_run', false), FILTER_VALIDATE_BOOLEAN);
        $rows = $request->input('rows', []);

        if (empty($rows)) {
            return response()->json(['error' => 'No se recibieron filas'], 422);
        }

        $stats = [
            'movimientos_procesados' => 0,
            'facturas_vinculadas'    => 0,
            'ya_existian'            => 0,
            'mov_no_encontrado'      => 0,
            'factura_no_encontrada'  => 0,
            'skipped'                => 0,
            'conciliados'            => 0,
            'log'                    => [],
        ];

        $currentMovId  = null;
        $movimientoIds = [];

        foreach ($rows as $i => $cols) {
            if ($i === 0) continue;
            while (count($cols) < 29) $cols[] = '';

            $idCol = trim((string) ($cols[5] ?? ''));

            if (str_starts_with($idCol, 'cartola')) {
                // ── Fila padre: un abono mensual de Transbank ────────────────
                $descXlsx  = trim((string) ($cols[9] ?? ''));
                $abonoXlsx = (float) ($cols[8] ?? 0);

                $periodo = null;
                if (preg_match('/(\d{4}-\d{2})/', $descXlsx, $m)) {
                    $periodo = $m[1];
                }

                if ($periodo) {
                    $movId = $this->buscarMovimientoPorPeriodo($periodo, $abonoXlsx, $stats);
                    $currentMovId = $movId;
                    $stats['movimientos_procesados']++;
                } else {
                    $currentMovId = null;
                }

                // Inline doc en misma fila padre (raro, se procesa igual)
                if ($currentMovId !== null && trim((string) ($cols[22] ?? '')) !== '') {
                    $this->procesarDocCsv($cols, $currentMovId, $dry, $stats);
                    if (!$dry) $movimientoIds[$currentMovId] = true;
                }

            } elseif ($currentMovId !== null && trim((string) ($cols[21] ?? '')) !== '') {
                // ── Fila hija: factura/boleta vinculada al movimiento actual ─
                $this->procesarDocCsv($cols, $currentMovId, $dry, $stats);
                if (!$dry) $movimientoIds[$currentMovId] = true;
            }
        }

        // Recalcular conciliado para movimientos procesados
        if (!$dry) {
            foreach (array_keys($movimientoIds) as $movId) {
                $totalAsignado = DB::table('venta_movimiento')
                    ->where('movimiento_id', $movId)->sum('monto');
                $monto = DB::table('movimientos_bancarios')->where('id', $movId)->value('monto');
                if ((float) $totalAsignado >= (float) $monto) {
                    DB::table('movimientos_bancarios')
                        ->where('id', $movId)->update(['conciliado' => true]);
                    $stats['conciliados']++;
                }
            }
        }

        return response()->json(['ok' => true, 'dry_run' => $dry, 'stats' => $stats]);
    }

    /**
     * Encuentra el movimiento bancario mensual de Transbank para un periodo dado.
     * Estrategia 1: via transbank_abonos → movimiento_bancario_id (requiere .dat subidos)
     * Estrategia 2: por monto exacto + 'Transbank' en la descripción del mes
     */
    private function buscarMovimientoPorPeriodo(string $periodo, float $abono, array &$stats): ?int
    {
        // Estrategia 1: via .dat subidos → transbank_abonos ya tiene el movimiento_bancario_id
        $movId = DB::table('transbank_abonos as ta')
            ->join('transbank_archivos as tf', 'tf.id', '=', 'ta.archivo_id')
            ->where('tf.periodo', $periodo)
            ->whereNotNull('ta.movimiento_bancario_id')
            ->value('ta.movimiento_bancario_id');

        if ($movId) {
            $stats['log'][] = "MOV periodo=$periodo vía .dat → mov_id=$movId";
            return (int) $movId;
        }

        // Estrategia 2: por monto + descripción 'Transbank' dentro del mes
        if ($abono > 0) {
            [$y, $mo] = explode('-', $periodo);
            $inicio = Carbon::create((int)$y, (int)$mo, 1)->toDateString();
            $fin    = Carbon::create((int)$y, (int)$mo, 1)->endOfMonth()->addDays(5)->toDateString();

            $mov = DB::table('movimientos_bancarios')
                ->where('monto', $abono)
                ->where('tipo', 'C')
                ->where('descripcion', 'like', '%Transbank%')
                ->whereBetween('fecha_contable', [$inicio, $fin])
                ->first();

            if ($mov) {
                $stats['log'][] = "MOV periodo=$periodo vía monto=$abono → mov_id={$mov->id}";
                return $mov->id;
            }
        }

        $stats['mov_no_encontrado']++;
        $stats['log'][] = "SIN MOV: periodo=$periodo abono=$abono (¿falta subir el .dat de Transbank?)";
        return null;
    }

    private function procesarDocCsv(array $cols, int $movId, bool $dry, array &$stats): void
    {
        $tipoCodigo    = (int)   ($cols[22] ?? 0);
        $folio         = trim($cols[23] ?? '');
        $rut           = trim($cols[24] ?? '');
        $montoAsignado = (float) ($cols[28] ?? 0);

        // Skip: boleta resumen mensual (tipo 39, folio negativo) o monto <= 0
        if ($tipoCodigo === 39 || $montoAsignado <= 0 || $folio === '' || str_starts_with($folio, '-')) {
            $stats['skipped']++;
            return;
        }

        $df = DB::table('documentos_facturacion')
            ->where('numero_documento_bsale', $folio)
            ->first();

        if (!$df) {
            $stats['factura_no_encontrada']++;
            $stats['log'][] = "SIN FAC: folio=$folio tipo=$tipoCodigo RUT=$rut";
            return;
        }

        $existe = DB::table('venta_movimiento')
            ->where('venta_id', $df->id)
            ->where('movimiento_id', $movId)
            ->exists();

        if ($existe) {
            $stats['ya_existian']++;
            return;
        }

        if (!$dry) {
            DB::table('venta_movimiento')->insert([
                'venta_id'     => $df->id,
                'movimiento_id' => $movId,
                'monto'        => $montoAsignado,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }

        $stats['facturas_vinculadas']++;
        $stats['log'][] = ($dry ? '[DRY] ' : '') . "OK: folio=$folio monto=$montoAsignado → mov_id=$movId df_id={$df->id}";
    }
}
