<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\PagoEmpleado;
use App\Models\MovimientoBancario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmpleadoController extends Controller
{
    // ── CRUD Empleados ───────────────────────────────────────────────────────

    public function index()
    {
        $empleados = Empleado::orderBy('nombre')
            ->withCount('pagos')
            ->get();

        return response()->json($empleados);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'        => 'required|string|max:120',
            'rut'           => 'required|string|max:12|unique:empleados',
            'sueldo_base'   => 'required|numeric|min:0',
            'fecha_ingreso' => 'required|date',
        ]);

        $empleado = Empleado::create($request->only([
            'nombre', 'rut', 'cargo', 'sueldo_base', 'fecha_ingreso',
            'fecha_egreso', 'activo', 'banco', 'cuenta_bancaria', 'tipo_cuenta', 'notas',
        ]));

        return response()->json($empleado, 201);
    }

    public function update(Request $request, int $id)
    {
        $empleado = Empleado::findOrFail($id);
        $empleado->update($request->only([
            'nombre', 'rut', 'cargo', 'sueldo_base', 'fecha_ingreso',
            'fecha_egreso', 'activo', 'banco', 'cuenta_bancaria', 'tipo_cuenta', 'notas',
        ]));
        return response()->json($empleado);
    }

    public function destroy(int $id)
    {
        Empleado::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    // ── Pagos ────────────────────────────────────────────────────────────────

    public function pagos(int $id)
    {
        $empleado = Empleado::findOrFail($id);
        $pagos = $empleado->pagos()
            ->with('movimiento:id,fecha_contable,monto,descripcion')
            ->orderByDesc('periodo')
            ->get();

        return response()->json($pagos);
    }

    public function storePago(Request $request, int $id)
    {
        $empleado = Empleado::findOrFail($id);

        $request->validate([
            'periodo' => 'required|date',
            'monto'   => 'required|numeric|min:0',
            'tipo'    => 'in:sueldo,bono,finiquito',
        ]);

        $pago = PagoEmpleado::create([
            'empleado_id'  => $empleado->id,
            'periodo'      => $request->periodo,
            'monto'        => $request->monto,
            'tipo'         => $request->tipo ?? 'sueldo',
            'pagado'       => $request->boolean('pagado'),
            'fecha_pago'   => $request->fecha_pago,
            'movimiento_id' => $request->movimiento_id,
            'notas'        => $request->notas,
        ]);

        return response()->json($pago->load('movimiento:id,fecha_contable,monto'), 201);
    }

    public function updatePago(Request $request, int $pagoId)
    {
        $pago = PagoEmpleado::findOrFail($pagoId);
        $pago->update($request->only([
            'monto', 'pagado', 'fecha_pago', 'movimiento_id', 'notas', 'tipo',
        ]));
        return response()->json($pago->load('movimiento:id,fecha_contable,monto'));
    }

    public function destroyPago(int $pagoId)
    {
        PagoEmpleado::findOrFail($pagoId)->delete();
        return response()->json(null, 204);
    }

    // ── Generar sueldos del mes ──────────────────────────────────────────────

    /** Crea registros de sueldo para todos los empleados activos de un período. */
    public function generarSueldos(Request $request)
    {
        $request->validate(['periodo' => 'required|date_format:Y-m']);
        $periodo = $request->periodo . '-01';

        $empleados = Empleado::where('activo', true)->get();
        $creados   = 0;

        foreach ($empleados as $emp) {
            PagoEmpleado::firstOrCreate(
                ['empleado_id' => $emp->id, 'periodo' => $periodo, 'tipo' => 'sueldo'],
                ['monto' => $emp->sueldo_base, 'pagado' => false]
            );
            $creados++;
        }

        return response()->json(['creados' => $creados, 'periodo' => $periodo]);
    }

    // ── Pagos por período (vista libro de sueldos) ───────────────────────────

    public function pagosPorPeriodo(Request $request)
    {
        $periodo = ($request->get('periodo', now()->format('Y-m'))) . '-01';

        $pagos = DB::table('pagos_empleado')
            ->join('empleados', 'empleados.id', '=', 'pagos_empleado.empleado_id')
            ->leftJoin('movimientos_bancarios', 'movimientos_bancarios.id', '=', 'pagos_empleado.movimiento_id')
            ->where('pagos_empleado.periodo', $periodo)
            ->select(
                'pagos_empleado.id',
                'pagos_empleado.monto',
                'pagos_empleado.tipo',
                'pagos_empleado.pagado',
                'pagos_empleado.fecha_pago',
                'pagos_empleado.notas',
                'empleados.id as empleado_id',
                'empleados.nombre',
                'empleados.rut',
                'empleados.cargo',
                'empleados.sueldo_base',
                'movimientos_bancarios.id as movimiento_id',
                'movimientos_bancarios.fecha_contable as mov_fecha',
                'movimientos_bancarios.descripcion as mov_descripcion',
            )
            ->orderBy('empleados.nombre')
            ->get();

        $totales = [
            'total'     => $pagos->sum('monto'),
            'pagado'    => $pagos->where('pagado', 1)->sum('monto'),
            'pendiente' => $pagos->where('pagado', 0)->sum('monto'),
            'cantidad'  => $pagos->count(),
        ];

        return response()->json(['pagos' => $pagos, 'totales' => $totales]);
    }

    // ── Resumen mensual ──────────────────────────────────────────────────────

    public function resumenMensual(Request $request)
    {
        $desde = $request->get('desde', now()->startOfYear()->format('Y-m-d'));
        $hasta = $request->get('hasta', now()->format('Y-m-d'));

        $rows = DB::table('pagos_empleado')
            ->join('empleados', 'empleados.id', '=', 'pagos_empleado.empleado_id')
            ->whereBetween('pagos_empleado.periodo', [$desde, $hasta])
            ->selectRaw("
                DATE_FORMAT(pagos_empleado.periodo, '%Y-%m') as mes,
                SUM(pagos_empleado.monto) as total,
                SUM(CASE WHEN pagos_empleado.pagado = 1 THEN pagos_empleado.monto ELSE 0 END) as pagado,
                SUM(CASE WHEN pagos_empleado.pagado = 0 THEN pagos_empleado.monto ELSE 0 END) as pendiente,
                COUNT(DISTINCT pagos_empleado.empleado_id) as cantidad
            ")
            ->groupBy('mes')
            ->orderBy('mes')
            ->get();

        return response()->json($rows);
    }
}
