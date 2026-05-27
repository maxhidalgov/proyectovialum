<?php

namespace App\Http\Controllers;

use App\Models\Gasto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GastoController extends Controller
{
    public function index(Request $request)
    {
        $desde   = $request->get('desde');
        $hasta   = $request->get('hasta');
        $cat     = $request->get('categoria');
        $buscar  = $request->get('buscar');

        $gastos = DB::table('gastos')
            ->leftJoin(
                DB::raw('(SELECT gasto_id, SUM(monto) as pagado FROM gasto_movimiento GROUP BY gasto_id) as pagos'),
                'gastos.id', '=', 'pagos.gasto_id'
            )
            ->when($desde,  fn($q) => $q->where('gastos.fecha', '>=', $desde))
            ->when($hasta,  fn($q) => $q->where('gastos.fecha', '<=', $hasta))
            ->when($cat,    fn($q) => $q->where('gastos.categoria', $cat))
            ->when($buscar, fn($q) => $q->where(function ($sq) use ($buscar) {
                $sq->where('gastos.descripcion', 'like', "%$buscar%")
                   ->orWhere('gastos.proveedor', 'like', "%$buscar%");
            }))
            ->select(
                'gastos.*',
                DB::raw('COALESCE(pagos.pagado, 0) as conciliado_monto'),
                DB::raw('gastos.monto - COALESCE(pagos.pagado, 0) as saldo_por_conciliar')
            )
            ->orderByDesc('gastos.fecha')
            ->paginate(100);

        $totales = DB::table('gastos')
            ->leftJoin(
                DB::raw('(SELECT gasto_id, SUM(monto) as pagado FROM gasto_movimiento GROUP BY gasto_id) as pagos'),
                'gastos.id', '=', 'pagos.gasto_id'
            )
            ->when($desde, fn($q) => $q->where('gastos.fecha', '>=', $desde))
            ->when($hasta, fn($q) => $q->where('gastos.fecha', '<=', $hasta))
            ->selectRaw('
                COUNT(gastos.id) as total_gastos,
                SUM(gastos.monto) as total_monto,
                COALESCE(SUM(pagos.pagado), 0) as total_conciliado,
                SUM(gastos.monto) - COALESCE(SUM(pagos.pagado), 0) as total_pendiente
            ')
            ->first();

        return response()->json([
            'gastos'  => $gastos,
            'totales' => $totales,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'fecha'       => 'required|date',
            'descripcion' => 'required|string|max:255',
            'monto'       => 'required|numeric|min:0.01',
        ]);

        $gasto = Gasto::create($request->only([
            'fecha', 'descripcion', 'categoria', 'monto',
            'proveedor', 'numero_documento', 'notas',
        ]));

        return response()->json($gasto, 201);
    }

    public function update(Request $request, int $id)
    {
        $gasto = Gasto::findOrFail($id);
        $gasto->update($request->only([
            'fecha', 'descripcion', 'categoria', 'monto',
            'proveedor', 'numero_documento', 'notas',
        ]));

        return response()->json($gasto);
    }

    public function destroy(int $id)
    {
        Gasto::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
