<?php

namespace App\Http\Controllers;

use App\Models\InventarioMovimiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventarioController extends Controller
{
    /**
     * GET /api/inventario/stock?buscar=
     * Lista los SKU (producto + color) de productos con controla_stock, con su stock actual.
     */
    public function stock(Request $request)
    {
        $buscar = trim((string) $request->get('buscar', ''));
        $palabras = array_filter(preg_split('/\s+/', $buscar));

        $q = DB::table('lista_precios as lp')
            ->join('productos as p', 'p.id', '=', 'lp.producto_id')
            ->leftJoin('colores as c', 'c.id', '=', 'lp.color_id')
            ->leftJoin('producto_color_proveedor as pcp', 'pcp.id', '=', 'lp.producto_color_proveedor_id')
            ->leftJoin('colores as c2', 'c2.id', '=', 'pcp.color_id')
            ->where('p.controla_stock', 1)
            ->where('lp.activo', 1);

        foreach ($palabras as $pal) {
            $q->where(function ($w) use ($pal) {
                $w->where('p.nombre', 'like', "%$pal%")
                  ->orWhere('c.nombre', 'like', "%$pal%")
                  ->orWhere('c2.nombre', 'like', "%$pal%");
            });
        }

        $rows = $q->distinct()->get([
            'lp.producto_id',
            DB::raw('COALESCE(c.id, c2.id) as color_id'),
            'p.nombre as producto',
            DB::raw('COALESCE(c.nombre, c2.nombre) as color'),
        ]);

        $stockMap = $this->mapaStock();

        $items = $rows->map(function ($r) use ($stockMap) {
            $key = $r->producto_id . '-' . ($r->color_id ?? '0');
            return [
                'producto_id' => $r->producto_id,
                'color_id'    => $r->color_id,
                'producto'    => $r->producto,
                'color'       => $r->color,
                'nombre'      => trim($r->producto . ($r->color ? " - {$r->color}" : '')),
                'stock'       => $stockMap[$key] ?? 0,
            ];
        })->sortBy('nombre')->values();

        return response()->json($items);
    }

    /** Mapa [producto_id-color_id] => stock (suma de movimientos). */
    private function mapaStock(): array
    {
        $movs = DB::table('inventario_movimientos')
            ->select('producto_id', 'color_id', DB::raw('SUM(cantidad) as stock'))
            ->groupBy('producto_id', 'color_id')
            ->get();

        $map = [];
        foreach ($movs as $m) {
            $map[$m->producto_id . '-' . ($m->color_id ?? '0')] = (float) $m->stock;
        }
        return $map;
    }

    /** Stock actual de un SKU puntual. */
    private function stockDe(int $productoId, ?int $colorId): float
    {
        return (float) InventarioMovimiento::where('producto_id', $productoId)
            ->where(function ($q) use ($colorId) {
                is_null($colorId) ? $q->whereNull('color_id') : $q->where('color_id', $colorId);
            })
            ->sum('cantidad');
    }

    /**
     * POST /api/inventario/set-stock
     * Fija el stock a una cantidad (conteo). Genera el movimiento compensatorio.
     * body: producto_id, color_id?, cantidad, nota?
     */
    public function setStock(Request $request)
    {
        $data = $request->validate([
            'producto_id' => 'required|integer|exists:productos,id',
            'color_id'    => 'nullable|integer',
            'cantidad'    => 'required|numeric|min:0',
            'nota'        => 'nullable|string|max:255',
        ]);

        $colorId = $data['color_id'] ?? null;
        $actual  = $this->stockDe($data['producto_id'], $colorId);
        $delta   = round($data['cantidad'] - $actual, 2);

        if (abs($delta) < 0.001) {
            return response()->json(['ok' => true, 'sin_cambios' => true, 'stock' => $actual]);
        }

        $existePrevio = InventarioMovimiento::where('producto_id', $data['producto_id'])
            ->where(function ($q) use ($colorId) {
                is_null($colorId) ? $q->whereNull('color_id') : $q->where('color_id', $colorId);
            })->exists();

        InventarioMovimiento::create([
            'producto_id' => $data['producto_id'],
            'color_id'    => $colorId,
            'cantidad'    => $delta,
            'tipo'        => $existePrevio ? 'ajuste' : 'ajuste_inicial',
            'nota'        => $data['nota'] ?? ($existePrevio ? 'Ajuste de inventario' : 'Conteo inicial'),
            'user_id'     => optional($request->user())->id,
        ]);

        return response()->json(['ok' => true, 'stock' => $this->stockDe($data['producto_id'], $colorId)]);
    }

    /**
     * GET /api/inventario/movimientos?producto_id=&color_id=
     * Historial de movimientos de un SKU.
     */
    public function movimientos(Request $request)
    {
        $productoId = (int) $request->get('producto_id');
        $colorId    = $request->get('color_id');
        $colorId    = ($colorId === null || $colorId === '') ? null : (int) $colorId;

        $movs = InventarioMovimiento::where('producto_id', $productoId)
            ->where(function ($q) use ($colorId) {
                is_null($colorId) ? $q->whereNull('color_id') : $q->where('color_id', $colorId);
            })
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['id', 'cantidad', 'tipo', 'nota', 'referencia_tipo', 'referencia_id', 'created_at']);

        return response()->json($movs);
    }

    /**
     * GET /api/inventario/productos?buscar=
     * Lista productos con su flag controla_stock (para configurar cuáles llevan stock).
     */
    public function productos(Request $request)
    {
        $buscar = trim((string) $request->get('buscar', ''));

        $q = DB::table('productos')->select('id', 'nombre', 'controla_stock');
        if ($buscar !== '') {
            $q->where('nombre', 'like', "%$buscar%");
        }

        return response()->json($q->orderBy('nombre')->limit(300)->get());
    }

    /**
     * PATCH /api/inventario/productos/{id}
     * Activa/desactiva el control de stock de un producto.
     */
    public function toggleControla(Request $request, int $id)
    {
        $data = $request->validate(['controla_stock' => 'required|boolean']);
        DB::table('productos')->where('id', $id)->update(['controla_stock' => $data['controla_stock']]);
        return response()->json(['ok' => true]);
    }
}
