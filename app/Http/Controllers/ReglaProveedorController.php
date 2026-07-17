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

        $rut = mb_strtolower(str_replace(['.', ' '], '', trim($request->rut_emisor)));

        DB::table('reglas_categoria_proveedor')->updateOrInsert(
            ['rut_emisor' => $rut],
            [
                'nombre_emisor' => $request->nombre_emisor,
                'categoria'     => $request->categoria,
                'updated_at'    => now(),
                'created_at'    => now(),
            ]
        );

        // Aplicar de inmediato a las compras existentes de ese proveedor
        $afectadas = $this->aplicarACompras($rut, $request->categoria);

        $regla = DB::table('reglas_categoria_proveedor')->where('rut_emisor', $rut)->first();
        return response()->json(['regla' => $regla, 'compras_actualizadas' => $afectadas], 201);
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

        // Reaplicar a las compras existentes de ese proveedor
        $regla = DB::table('reglas_categoria_proveedor')->find($id);
        if ($regla) {
            $this->aplicarACompras($regla->rut_emisor, $request->categoria);
        }

        return response()->json($regla);
    }

    // ── Eliminar regla ────────────────────────────────────────────────────────

    public function destroy(int $id)
    {
        DB::table('reglas_categoria_proveedor')->delete($id);
        return response()->json(null, 204);
    }

    // ── Aplicar todas las reglas a compras existentes ─────────────────────────
    // Actualiza `categoria` en compras que tengan rut_emisor con regla definida.
    // Normaliza el RUT en ambos lados (quita puntos y espacios) para que matchee
    // aunque las compras vengan con RUT formateado (76.072.694-K) y las reglas sin puntos.

    public function aplicar()
    {
        $actualizadas = DB::update("
            UPDATE compras c
            JOIN reglas_categoria_proveedor r
              ON REPLACE(REPLACE(LOWER(c.rut_emisor), '.', ''), ' ', '')
               = REPLACE(REPLACE(LOWER(r.rut_emisor), '.', ''), ' ', '')
            SET c.categoria = r.categoria
        ");

        return response()->json(['actualizadas' => $actualizadas]);
    }

    // Aplica una categoría a todas las compras de un proveedor (RUT ya normalizado)
    private function aplicarACompras(string $rutNorm, string $categoria): int
    {
        return DB::update("
            UPDATE compras
            SET categoria = ?, updated_at = NOW()
            WHERE REPLACE(REPLACE(LOWER(rut_emisor), '.', ''), ' ', '') = ?
        ", [$categoria, $rutNorm]);
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

        $afectadas = 1;

        if ($request->boolean('crear_regla') && $compra->rut_emisor) {
            $rutNorm = mb_strtolower(str_replace(['.', ' '], '', $compra->rut_emisor));

            DB::table('reglas_categoria_proveedor')->updateOrInsert(
                ['rut_emisor' => $rutNorm],
                [
                    'nombre_emisor' => $compra->nombre_emisor,
                    'categoria'     => $request->categoria,
                    'updated_at'    => now(),
                    'created_at'    => now(),
                ]
            );

            // Aplicar la categoría a TODAS las compras de ese proveedor
            $afectadas = $this->aplicarACompras($rutNorm, $request->categoria);
        }

        return response()->json(['ok' => true, 'compras_actualizadas' => $afectadas]);
    }
}
