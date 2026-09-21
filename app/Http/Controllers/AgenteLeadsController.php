<?php

namespace App\Http\Controllers;

use App\Models\AgenteConversacion;
use App\Models\Lead;
use App\Services\AgenteLeadsService;
use Illuminate\Http\Request;

class AgenteLeadsController extends Controller
{
    public function __construct(private AgenteLeadsService $agente) {}

    /** Inicia una conversación de prueba (simulador) y devuelve el saludo. */
    public function iniciar(Request $request)
    {
        $conv = AgenteConversacion::create([
            'canal'         => $request->input('canal', 'simulador'),
            'identificador' => $request->input('telefono') ?: ('sim-' . uniqid()),
            'estado'        => 'activa',
            'bot_activo'    => true,
            'historial'     => [],
        ]);

        return response()->json([
            'conversacion_id' => $conv->id,
            'saludo'          => '¡Hola! 👋 Bienvenido/a a Vialum. Fabricamos a medida ventanas y puertas de PVC y aluminio, shower de baño en vidrio templado y divisiones de oficina. ¿En qué le puedo ayudar?',
        ]);
    }

    /** Procesa un mensaje del contacto y responde. */
    public function mensaje(Request $request)
    {
        $data = $request->validate([
            'conversacion_id' => 'required|integer|exists:agente_conversaciones,id',
            'texto'           => 'required|string|max:2000',
        ]);

        $conv = AgenteConversacion::findOrFail($data['conversacion_id']);

        if (!$conv->bot_activo) {
            return response()->json([
                'texto'  => 'Un asesor está atendiendo esta conversación.',
                'lead'   => null,
                'estado' => $conv->estado,
                'pausado' => true,
            ]);
        }

        $res = $this->agente->procesarMensaje($conv, $data['texto']);

        return response()->json($res);
    }

    /** Lista de leads capturados. */
    public function leads(Request $request)
    {
        $leads = Lead::orderByDesc('id')->limit(100)->get()->map(fn ($l) => [
            'id'            => $l->id,
            'categoria'     => $l->categoria,
            'nombre'        => $l->nombre,
            'telefono'      => $l->telefono,
            'email'         => $l->email,
            'comuna'        => $l->comuna,
            'tipo_producto' => $l->tipo_producto,
            'material'      => $l->material,
            'tipo_obra'     => $l->tipo_obra,
            'detalle'       => $l->detalle,
            'origen'        => $l->origen,
            'estado'        => $l->estado,
            'created_at'    => $l->created_at?->toDateTimeString(),
        ]);

        return response()->json(['leads' => $leads]);
    }

    /** Cambiar estado de un lead (nuevo/contactado/convertido/descartado). */
    public function actualizarLead(Request $request, $id)
    {
        $data = $request->validate([
            'estado' => 'required|in:nuevo,contactado,convertido,descartado',
        ]);
        $lead = Lead::findOrFail($id);
        $lead->update(['estado' => $data['estado']]);

        return response()->json(['ok' => true, 'estado' => $lead->estado]);
    }
}
