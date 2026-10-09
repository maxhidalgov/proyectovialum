<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\ClienteContacto;
use Illuminate\Http\Request;

/**
 * Contactos de un cliente (personas de una empresa a las que se dirige una cotización).
 */
class ClienteContactoController extends Controller
{
    // GET /api/clientes/{cliente}/contactos
    public function index($clienteId)
    {
        Cliente::findOrFail($clienteId);

        return response()->json(
            ClienteContacto::where('cliente_id', $clienteId)
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'cliente_id', 'nombre', 'cargo', 'telefono', 'email'])
        );
    }

    // POST /api/clientes/{cliente}/contactos
    public function store(Request $request, $clienteId)
    {
        Cliente::findOrFail($clienteId);

        $data = $request->validate([
            'nombre'   => 'required|string|max:150',
            'cargo'    => 'nullable|string|max:100',
            'telefono' => 'nullable|string|max:50',
            'email'    => 'nullable|email|max:150',
        ]);

        // Evita duplicar el mismo contacto si se envía dos veces (doble clic)
        $contacto = ClienteContacto::firstOrCreate(
            ['cliente_id' => $clienteId, 'nombre' => trim($data['nombre']), 'activo' => true],
            [
                'cargo'    => $data['cargo'] ?? null,
                'telefono' => $data['telefono'] ?? null,
                'email'    => $data['email'] ?? null,
            ]
        );

        return response()->json($contacto, 201);
    }

    // PUT /api/contactos/{id}
    public function update(Request $request, $id)
    {
        $contacto = ClienteContacto::findOrFail($id);

        $data = $request->validate([
            'nombre'   => 'sometimes|required|string|max:150',
            'cargo'    => 'sometimes|nullable|string|max:100',
            'telefono' => 'sometimes|nullable|string|max:50',
            'email'    => 'sometimes|nullable|email|max:150',
        ]);

        $contacto->update($data);

        return response()->json($contacto);
    }

    // DELETE /api/contactos/{id}  → se desactiva (las cotizaciones ya hechas conservan su nombre)
    public function destroy($id)
    {
        ClienteContacto::findOrFail($id)->update(['activo' => false]);

        return response()->json(['ok' => true]);
    }
}
