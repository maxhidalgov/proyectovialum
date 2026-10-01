<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Endpoints máquina-a-máquina para el bot de WhatsApp.
 * Protegidos por un token estático (config services.bot.token), sin login JWT,
 * igual que el endpoint de cron. Devuelven texto listo para postear en el grupo.
 */
class BotController extends Controller
{
    // Verifica el token del bot; aborta con 403 si no calza.
    private function verificarToken(Request $r): void
    {
        $token = config('services.bot.token');
        if (empty($token) || !hash_equals($token, (string) $r->query('token'))) {
            abort(403, 'Token inválido');
        }
    }

    /**
     * GET /api/bot/ausentes-hoy?token=XXXX[&fecha=YYYY-MM-DD&tolerancia=5]
     * Devuelve quién no marcó asistencia hoy (y quién llegó atrasado),
     * reutilizando el reporte de AsistenciaController::diario (Workera).
     */
    public function ausentesHoy(Request $r)
    {
        $this->verificarToken($r);

        // La app corre en UTC: "hoy" debe ser el día en Chile, no el de UTC
        // (pasadas las 21:00 hora chilena UTC ya cambió de día).
        $fecha      = $r->query('fecha', now('America/Santiago')->toDateString());
        $tolerancia = (int) $r->query('tolerancia', 5);

        // Reutiliza el cálculo existente (horarios + marcaciones + permisos + feriados).
        $data = app(AsistenciaController::class)
            ->diario(new Request(['fecha' => $fecha, 'tolerancia' => $tolerancia]))
            ->getData(true);

        // Workera sin configurar u otro error → pasa el mensaje tal cual.
        if (isset($data['error'])) {
            return response()->json(['ok' => false, 'error' => $data['error']], 422);
        }

        $dias     = $data['dias'] ?? [];
        $ausentes = array_values(array_filter($dias, fn ($d) => ($d['estado'] ?? '') === 'Ausente'));
        $atrasos  = array_values(array_filter($dias, fn ($d) => ($d['estado'] ?? '') === 'Atraso'));

        return response()->json([
            'ok'       => true,
            'fecha'    => $fecha,
            'texto'    => $this->formatearTexto($fecha, $dias, $ausentes, $atrasos),
            'ausentes' => array_map(fn ($d) => ['nombre' => $d['nombre'], 'turno' => $d['turno'] ?? null], $ausentes),
            'atrasos'  => array_map(fn ($d) => [
                'nombre'     => $d['nombre'],
                'esperada'   => $d['esperada'] ?? null,
                'real'       => $d['real'] ?? null,
                'atraso_min' => $d['atraso_min'] ?? 0,
            ], $atrasos),
        ]);
    }

    /**
     * GET /api/bot/precio?token=XXXX&q=silicona negra
     * Busca en la lista de precios (cada palabra del texto debe estar en el nombre
     * del producto o su color) y por cada coincidencia devuelve precio de venta,
     * costo de compra y la última compra (fecha, proveedor, factura).
     */
    public function precio(Request $r)
    {
        $this->verificarToken($r);

        $q = trim((string) $r->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['ok' => false, 'error' => 'Texto de búsqueda muy corto'], 422);
        }

        $palabras = array_filter(explode(' ', preg_replace('/\s+/', ' ', $q)));

        $rows = DB::table('lista_precios as lp')
            ->join('productos as p', 'p.id', '=', 'lp.producto_id')
            ->leftJoin('colores as c', 'c.id', '=', 'lp.color_id')
            ->leftJoin('producto_color_proveedor as pcp', 'pcp.id', '=', 'lp.producto_color_proveedor_id')
            ->leftJoin('colores as c2', 'c2.id', '=', 'pcp.color_id')
            ->where('lp.activo', 1)
            ->where(function ($outer) use ($palabras) {
                foreach ($palabras as $pal) {
                    $outer->where(function ($w) use ($pal) {
                        $w->where('p.nombre', 'like', "%$pal%")
                          ->orWhere('c.nombre', 'like', "%$pal%")
                          ->orWhere('c2.nombre', 'like', "%$pal%");
                    });
                }
            })
            ->orderBy('p.nombre')
            ->limit(30)
            ->get(['p.id as producto_id', 'p.nombre as producto',
                   DB::raw('COALESCE(c.id, c2.id) as color_id'),
                   DB::raw('COALESCE(c.nombre, c2.nombre) as color'),
                   'lp.precio_venta', 'lp.precio_costo']);

        $total = $rows->count();
        $items = [];
        foreach ($rows->take(3) as $row) {
            $items[] = [
                'producto'      => $row->producto,
                'color'         => $row->color,
                'precio_venta'  => (int) round($row->precio_venta),
                'precio_costo'  => (int) round($row->precio_costo),
                'ultima_compra' => $this->ultimaCompra((int) $row->producto_id, $row->color_id ? (int) $row->color_id : null),
            ];
        }

        return response()->json([
            'ok'    => true,
            'q'     => $q,
            'total' => $total,
            'texto' => $this->formatearPrecios($q, $items, $total),
            'items' => $items,
        ]);
    }

    // Última línea de compra de un producto (+color), vía producto_color_proveedor.
    private function ultimaCompra(int $productoId, ?int $colorId): ?array
    {
        $buscar = fn (?int $color) => DB::table('compra_items as ci')
            ->join('compras as co', 'co.id', '=', 'ci.compra_id')
            ->join('producto_color_proveedor as pcp', 'pcp.id', '=', 'ci.pcp_id')
            ->leftJoin('colores as cc', 'cc.id', '=', 'pcp.color_id')
            ->where('pcp.producto_id', $productoId)
            ->when($color, fn ($q) => $q->where('pcp.color_id', $color))
            ->orderByDesc('co.fecha_emision')
            ->orderByDesc('co.id')
            ->first(['co.fecha_emision', 'co.nombre_emisor', 'co.folio', 'co.tipo_dte',
                     'ci.precio_unitario', 'ci.descuento', 'ci.cantidad', 'ci.unidad',
                     'cc.nombre as color_compra']);

        $fila = $buscar($colorId);
        $otroColor = null;
        // Los colores de la lista y de las compras no siempre coinciden: si no hay
        // compra del mismo color, se muestra la última del producto indicando el color.
        if (!$fila && $colorId) {
            $fila = $buscar(null);
            $otroColor = $fila?->color_compra;
        }

        if (!$fila) {
            return null;
        }

        $neto = $fila->descuento > 0
            ? round($fila->precio_unitario * (1 - $fila->descuento / 100))
            : $fila->precio_unitario;

        return [
            'fecha'     => \Carbon\Carbon::parse($fila->fecha_emision)->format('d/m/Y'),
            'proveedor' => $fila->nombre_emisor,
            'folio'     => $fila->folio,
            'precio'    => (int) $neto,
            'cantidad'  => (float) $fila->cantidad,
            'unidad'    => $fila->unidad,
            'color'     => $otroColor, // solo si no hubo compra del color de la lista
        ];
    }

    private function clp($n): string
    {
        return '$' . number_format((float) $n, 0, ',', '.');
    }

    private function formatearPrecios(string $q, array $items, int $total): string
    {
        if (empty($items)) {
            return "🔎 No encontré productos para \"{$q}\" en la lista de precios.";
        }

        $partes = ["🔎 *{$q}*"];
        foreach ($items as $it) {
            $nombre = $it['producto'] . ($it['color'] ? " — {$it['color']}" : '');
            $iva    = (int) round($it['precio_venta'] * 1.19);

            $partes[] = '';
            $partes[] = "*{$nombre}*";
            $partes[] = "• Venta: {$this->clp($it['precio_venta'])} neto ({$this->clp($iva)} c/IVA)";
            $partes[] = '• Costo lista: ' . ($it['precio_costo'] > 0 ? $this->clp($it['precio_costo']) . ' neto' : 's/d');

            $uc = $it['ultima_compra'];
            if ($uc) {
                $cant = rtrim(rtrim(number_format($uc['cantidad'], 2, ',', ''), '0'), ',');
                $aviso = $uc['color'] ? " _(color {$uc['color']})_" : '';
                $partes[] = "• Última compra: {$uc['fecha']} a {$uc['proveedor']} — {$this->clp($uc['precio'])} neto x " . trim("{$cant} {$uc['unidad']}") . " (factura {$uc['folio']}){$aviso}";
            } else {
                $partes[] = '• Última compra: sin registro';
            }
        }

        if ($total > count($items)) {
            $partes[] = '';
            $partes[] = "_Hay {$total} coincidencias, muestro " . count($items) . '. Sé más específico para acotar._';
        }

        return implode("\n", $partes);
    }

    // Arma el mensaje para WhatsApp (formato *negrita* con asteriscos simples).
    private function formatearTexto(string $fecha, array $dias, array $ausentes, array $atrasos): string
    {
        $dia = \Carbon\Carbon::parse($fecha)->locale('es')->isoFormat('dddd D [de] MMMM');

        // Sin jornada asignada hoy (domingo, feriado, o sin horarios cargados).
        if (empty($dias)) {
            return "📋 *Asistencia — {$dia}*\nHoy no hay jornada con horario asignado.";
        }

        $partes = ["📋 *Asistencia — {$dia}*"];

        if (empty($ausentes)) {
            $partes[] = '✅ Todos marcaron asistencia.';
        } else {
            $n = count($ausentes);
            $partes[] = '';
            $partes[] = "⚠️ *No marcaron ({$n}):*";
            foreach ($ausentes as $d) {
                $partes[] = "• {$d['nombre']}";
            }
        }

        if (!empty($atrasos)) {
            $partes[] = '';
            $partes[] = '🕐 *Atrasos:*';
            foreach ($atrasos as $d) {
                $partes[] = "• {$d['nombre']} — marcó {$d['real']} (esperado {$d['esperada']}, +{$d['atraso_min']} min)";
            }
        }

        return implode("\n", $partes);
    }
}
