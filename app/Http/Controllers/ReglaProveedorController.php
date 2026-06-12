<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReglaProveedorController extends Controller
{
    // ── Listar reglas ─────────────────────────────────────────────────────────

    public function index()
    {
        $reglas = DB::table('reglas_categoria_proveedor')
            ->orderBy('categoria')
            ->orderBy('nombre_emisor')
            ->get();

        return response()->json($reglas);
    }

    // ── Crear o actualizar regla (upsert por rut_emisor) ──────────────────────

    public function store(Request $request)
    {
        $request->validate([
            'rut_emisor'    => 'required|string|max:20',
            'nombre_emisor' => 'nullable|string|max:255',
            'categoria'     => 'required|string|max:100',
        ]);

        $rut = mb_strtolower(trim($request->rut_emisor));

        $id = DB::table('reglas_categoria_proveedor')->updateOrInsert(
            ['rut_emisor' => $rut],
            [
                'nombre_emisor' => $request->nombre_emisor,
                'categoria'     => $request->categoria,
                'updated_at'    => now(),
                'created_at'    => now(),
            ]
        );

        $regla = DB::table('reglas_categoria_proveedor')->where('rut_emisor', $rut)->first();
        return response()->json($regla, 201);
    }

    // ── Actualizar regla ──────────────────────────────────────────────────────

    public function update(Request $request, int $id)
    {
        $request->validate([
            'nombre_emisor' => 'nullable|string|max:255',
            'categoria'     => 'required|string|max:100',
        ]);

        DB::table('reglas_categoria_proveedor')->where('id', $id)->update([
            'nombre_emisor' => $request->nombre_emisor,
            'categoria'     => $request->categoria,
            'updated_at'    => now(),
        ]);

        return response()->json(DB::table('reglas_categoria_proveedor')->find($id));
    }

    // ── Eliminar regla ────────────────────────────────────────────────────────

    public function destroy(int $id)
    {
        DB::table('reglas_categoria_proveedor')->delete($id);
        return response()->json(null, 204);
    }

    // ── Aplicar todas las reglas a compras existentes ─────────────────────────
    // Actualiza `categoria` en compras que tengan rut_emisor con regla definida

    public function aplicar()
    {
        $actualizadas = DB::update("
            UPDATE compras c
            JOIN reglas_categoria_proveedor r
              ON LOWER(c.rut_emisor) = LOWER(r.rut_emisor)
            SET c.categoria = r.categoria
        ");

        return response()->json(['actualizadas' => $actualizadas]);
    }

    // ── Categorías distintas (para el select del frontend) ────────────────────

    public function categorias()
    {
        $cats = DB::table('reglas_categoria_proveedor')
            ->distinct()
            ->orderBy('categoria')
            ->pluck('categoria');

        return response()->json($cats);
    }

    // ── Asignar categoría a una compra + opcionalmente crear/actualizar regla ──

    public function asignarCategoria(Request $request, int $compraId)
    {
        $request->validate([
            'categoria'    => 'required|string|max:100',
            'crear_regla'  => 'boolean',
        ]);

        $compra = DB::table('compras')->where('id', $compraId)->firstOrFail();

        DB::table('compras')->where('id', $compraId)->update([
            'categoria'  => $request->categoria,
            'updated_at' => now(),
        ]);

        if ($request->boolean('crear_regla') && $compra->rut_emisor) {
            DB::table('reglas_categoria_proveedor')->updateOrInsert(
                ['rut_emisor' => mb_strtolower($compra->rut_emisor)],
                [
                    'nombre_emisor' => $compra->nombre_emisor,
                    'categoria'     => $request->categoria,
                    'updated_at'    => now(),
                    'created_at'    => now(),
                ]
            );
        }

        return response()->json(['ok' => true]);
    }
}
