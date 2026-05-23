<?php

namespace App\Http\Controllers;

use App\Models\MovimientoBancario;
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

    // ── Saldo (viene de los movimientos) ─────────────────────────────────────

    public function saldo()
    {
        try {
            $result = (new BancochileService())->getMovimientosPorCantidad(1);
            $meta   = $result['meta'];
            return response()->json([
                'saldoDisponible' => $meta['saldoDisponible'] ?? null,
                'saldoContable'   => $meta['saldoContable'] ?? null,
                'titular'         => $meta['nombreTitular'] ?? null,
                'cuenta'          => $meta['numeroCuenta'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
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
                MovimientoBancario::create([
                    'cuenta'           => $svc->getCuenta(),
                    'fecha_contable'   => $fechaContable,
                    'fecha_valor'      => $fechaValor,
                    'descripcion'      => mb_substr($descripcion, 0, 255),
                    'monto'            => $monto,
                    'tipo'             => $tipo,
                    'numero_documento' => $nroDoc,
                    'saldo_disponible' => $saldo,
                    'bch_codigo'       => $secuencial,
                    'raw'              => $m,
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
            $q->where('descripcion', 'like', '%' . $request->buscar . '%');
        }

        $movs = $q->paginate(150);

        $totalesQ = MovimientoBancario::query();
        if ($request->filled('desde')) {
            $totalesQ->where('fecha_contable', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $totalesQ->where('fecha_contable', '<=', $request->hasta);
        }

        $totales = $totalesQ->selectRaw("
            SUM(CASE WHEN tipo='C' THEN monto ELSE 0 END) as total_creditos,
            SUM(CASE WHEN tipo='D' THEN monto ELSE 0 END) as total_debitos,
            COUNT(*) as total_movimientos,
            SUM(CASE WHEN conciliado=0 THEN 1 ELSE 0 END) as pendientes
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
            'categoria', 'conciliado', 'compra_id', 'cotizacion_id',
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
