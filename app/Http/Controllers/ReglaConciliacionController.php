<?php

namespace App\Http\Controllers;

use App\Models\ReglaConciliacion;
use App\Models\MovimientoBancario;
use Illuminate\Http\Request;

class ReglaConciliacionController extends Controller
{
    public function index()
    {
        return response()->json(
            ReglaConciliacion::orderBy('prioridad')->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'    => 'required|string|max:100',
            'patron'    => 'required|string|max:200',
            'categoria' => 'required|string|max:80',
            'tipo'      => 'in:C,D,A',
            'prioridad' => 'integer|min:1|max:9999',
        ]);

        $regla = ReglaConciliacion::create($request->only([
            'nombre', 'patron', 'categoria', 'tipo', 'prioridad', 'activa',
        ]));

        return response()->json($regla, 201);
    }

    public function update(Request $request, int $id)
    {
        $regla = ReglaConciliacion::findOrFail($id);
        $regla->update($request->only([
            'nombre', 'patron', 'categoria', 'tipo', 'prioridad', 'activa',
        ]));
        return response()->json($regla);
    }

    public function destroy(int $id)
    {
        ReglaConciliacion::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    /** Aplica todas las reglas a movimientos sin categoría. */
    public function aplicar()
    {
        $movs = MovimientoBancario::whereNull('categoria')->get();
        $aplicados = 0;

        foreach ($movs as $mov) {
            $cat = ReglaConciliacion::categorizar($mov->descripcion, $mov->tipo);
            if ($cat) {
                $mov->update(['categoria' => $cat]);
                $aplicados++;
            }
        }

        return response()->json(['aplicados' => $aplicados, 'total' => $movs->count()]);
    }
}
