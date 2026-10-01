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
            ->get(['lp.id as lista_precio_id', 'p.id as producto_id', 'p.nombre as producto',
                   DB::raw('COALESCE(c.id, c2.id) as color_id'),
                   DB::raw('COALESCE(c.nombre, c2.nombre) as color'),
                   'lp.precio_venta', 'lp.precio_costo']);

        $total   = $rows->count();
        $n       = max(1, min(8, (int) $r->query('n', 3)));
        $compacto = filter_var($r->query('compacto', false), FILTER_VALIDATE_BOOLEAN);
        $items   = [];
        foreach ($rows->take($n) as $row) {
            // Modo compacto (para el agente de cotizaciones): solo lo necesario, sin costo ni compras
            if ($compacto) {
                $items[] = [
                    'lista_precio_id' => (int) $row->lista_precio_id,
                    'producto'        => $row->producto,
                    'color'           => $row->color,
                    'precio_neto'     => (int) round($row->precio_venta),
                ];
                continue;
            }
            $items[] = [
                'lista_precio_id' => (int) $row->lista_precio_id,
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
            'texto' => $compacto ? null : $this->formatearPrecios($q, $items, $total),
            'items' => $items,
        ]);
    }

    /**
     * GET /api/bot/factura?token=XXXX&q=457307[&lado=compra|venta]
     * Info de un documento por número de folio. Busca en compras (facturas de
     * proveedores) y/o ventas (facturas, boletas y NC emitidas). Los folios se
     * repiten entre proveedores y tipos, así que puede devolver varias coincidencias.
     */
    public function factura(Request $r)
    {
        $this->verificarToken($r);

        $folio = (int) preg_replace('/\D/', '', (string) $r->query('q', ''));
        $lado  = $r->query('lado'); // 'compra' | 'venta' | null = ambos
        if ($folio <= 0) {
            return response()->json(['ok' => false, 'error' => 'Indica el número de folio'], 422);
        }

        $compras = $lado === 'venta' ? [] : $this->buscarCompras($folio);
        $ventas  = $lado === 'compra' ? [] : $this->buscarVentas($folio);

        return response()->json([
            'ok'      => true,
            'folio'   => $folio,
            'texto'   => $this->formatearFacturas($folio, $compras, $ventas),
            'compras' => $compras,
            'ventas'  => $ventas,
        ]);
    }

    private const TIPOS_COMPRA = [33 => 'Factura', 34 => 'Factura exenta', 56 => 'Nota de débito', 61 => 'Nota de crédito'];

    private function buscarCompras(int $folio): array
    {
        $ef = app(CuentasPorPagarController::class)->efectivoPagadoSub();

        $rows = DB::table('compras')
            ->leftJoin($ef, 'ef.compra_id', '=', 'compras.id')
            ->where('compras.folio', $folio)
            ->orderByDesc('compras.fecha_emision')
            ->limit(3)
            ->get(['compras.id', 'compras.tipo_dte', 'compras.nombre_emisor', 'compras.rut_emisor',
                   'compras.fecha_emision', 'compras.neto', 'compras.iva', 'compras.total',
                   'compras.pagado_historico', 'compras.pdf_url',
                   DB::raw('COALESCE(ef.monto_pagado_efectivo, 0) as pagado')]);

        return $rows->map(function ($c) {
            $items = DB::table('compra_items')->where('compra_id', $c->id)->orderBy('id')->limit(6)
                ->get(['cantidad', 'unidad', 'nombre', 'precio_unitario', 'descuento']);
            $nItems = DB::table('compra_items')->where('compra_id', $c->id)->count();

            return [
                'tipo'      => self::TIPOS_COMPRA[(int) $c->tipo_dte] ?? "DTE {$c->tipo_dte}",
                'es_nc'     => (int) $c->tipo_dte === 61,
                'proveedor' => $c->nombre_emisor,
                'rut'       => $c->rut_emisor,
                'fecha'     => $c->fecha_emision ? \Carbon\Carbon::parse($c->fecha_emision)->format('d/m/Y') : null,
                'neto'      => (int) $c->neto,
                'iva'       => (int) $c->iva,
                'total'     => (int) $c->total,
                'pagado'    => (int) round($c->pagado),
                'historico' => (bool) $c->pagado_historico,
                'pdf_url'   => $c->pdf_url,
                'n_items'   => $nItems,
                'items'     => $items->map(fn ($i) => [
                    'cantidad' => (float) $i->cantidad,
                    'unidad'   => $i->unidad,
                    'nombre'   => $i->nombre,
                    'neto'     => (int) ($i->descuento > 0 ? round($i->precio_unitario * (1 - $i->descuento / 100)) : $i->precio_unitario),
                ])->all(),
            ];
        })->all();
    }

    private function buscarVentas(int $folio): array
    {
        // Facturas / NC: reutiliza el registro de ventas (trae cobrado y pendiente).
        $data = app(CuentasPorCobrarController::class)
            ->registroVentas(new Request(['buscar' => (string) $folio]))
            ->getData(true);

        $docs = collect($data['documentos'] ?? [])
            ->filter(fn ($d) => (string) ($d['numero_documento_bsale'] ?? '') === (string) $folio)
            ->take(3)
            ->map(fn ($d) => [
                'tipo'      => !empty($d['es_nc']) ? 'Nota de crédito' : 'Factura',
                'es_nc'     => (bool) ($d['es_nc'] ?? false),
                'cliente'   => $d['razon_social'] ?? null,
                'rut'       => $d['identification'] ?? null,
                'fecha'     => !empty($d['fecha_emision']) ? \Carbon\Carbon::parse($d['fecha_emision'])->format('d/m/Y') : null,
                'total'     => (int) round($d['monto'] ?? 0),
                'cobrado'   => (int) round($d['monto_cobrado'] ?? 0),
                'pendiente' => (int) round($d['pendiente'] ?? 0),
                'pdf_url'   => $d['url_pdf_bsale'] ?? null,
            ])->values()->all();

        // Boletas: no están en el registro de ventas (se concilian por resumen mensual).
        $boletas = DB::table('documentos_facturacion as df')
            ->leftJoin('clientes as cl', 'cl.id', '=', 'df.cliente_id')
            ->where('df.estado', 'emitido')
            ->where('df.tipo_documento_bsale_id', 1)
            ->where('df.numero_documento_bsale', $folio)
            ->limit(2)
            ->get(['df.fecha_emision', 'df.monto', 'df.forma_pago', 'df.url_pdf_bsale',
                   DB::raw('COALESCE(cl.razon_social, df.bsale_cliente_nombre) as cliente')])
            ->map(fn ($b) => [
                'tipo'       => 'Boleta',
                'es_nc'      => false,
                'cliente'    => $b->cliente,
                'rut'        => null,
                'fecha'      => $b->fecha_emision ? \Carbon\Carbon::parse($b->fecha_emision)->format('d/m/Y') : null,
                'total'      => (int) round($b->monto),
                'forma_pago' => $b->forma_pago,
                'pdf_url'    => $b->url_pdf_bsale,
            ])->all();

        return array_merge($docs, $boletas);
    }

    private function formatearFacturas(int $folio, array $compras, array $ventas): string
    {
        if (!$compras && !$ventas) {
            return "🔎 No encontré ningún documento con el folio {$folio}.";
        }

        $partes = [];

        foreach ($compras as $c) {
            $partes[] = ($partes ? '' : null);
            $partes[] = "🧾 *{$c['tipo']} de compra N° {$folio}*";
            $partes[] = "Proveedor: {$c['proveedor']}" . ($c['rut'] ? " ({$c['rut']})" : '');
            $partes[] = "Fecha: {$c['fecha']}";
            $partes[] = "Neto {$this->clp($c['neto'])} · IVA {$this->clp($c['iva'])} · *Total {$this->clp($c['total'])}*";

            if ($c['historico']) {
                $partes[] = 'Estado: ✅ pagada (histórica)';
            } elseif ($c['es_nc']) {
                $partes[] = 'Estado: nota de crédito';
            } else {
                $pend = $c['total'] - $c['pagado'];
                if ($pend <= 1)            $partes[] = 'Estado: ✅ pagada';
                elseif ($c['pagado'] > 0)  $partes[] = "Estado: ⚠️ pago parcial — pagado {$this->clp($c['pagado'])}, pendiente {$this->clp($pend)}";
                else                       $partes[] = "Estado: ⏳ pendiente de pago ({$this->clp($pend)})";
            }

            if ($c['items']) {
                $partes[] = "Detalle ({$c['n_items']} línea" . ($c['n_items'] === 1 ? '' : 's') . '):';
                foreach ($c['items'] as $i) {
                    $cant = rtrim(rtrim(number_format($i['cantidad'], 2, ',', ''), '0'), ',');
                    $partes[] = '• ' . trim("{$cant} {$i['unidad']}") . " {$i['nombre']} — {$this->clp($i['neto'])} neto c/u";
                }
                if ($c['n_items'] > count($c['items'])) {
                    $partes[] = '• … y ' . ($c['n_items'] - count($c['items'])) . ' más';
                }
            }
            if ($c['pdf_url']) $partes[] = "PDF: {$c['pdf_url']}";
        }

        foreach ($ventas as $v) {
            $partes[] = ($partes ? '' : null);
            $partes[] = "🧾 *{$v['tipo']} de venta N° {$folio}*";
            $partes[] = "Cliente: {$v['cliente']}" . (!empty($v['rut']) ? " ({$v['rut']})" : '');
            $partes[] = "Fecha: {$v['fecha']}";
            $partes[] = "*Total {$this->clp($v['total'])}*";

            if (isset($v['pendiente'])) {
                if ($v['es_nc'])                    $partes[] = 'Estado: nota de crédito';
                elseif (abs($v['pendiente']) <= 1)  $partes[] = '✅ Cobrada completa';
                else                                $partes[] = "Cobrado {$this->clp($v['cobrado'])} · ⏳ pendiente {$this->clp($v['pendiente'])}";
            } elseif (!empty($v['forma_pago'])) {
                $partes[] = "Forma de pago: {$v['forma_pago']}";
            }
            if (!empty($v['pdf_url'])) $partes[] = "PDF: {$v['pdf_url']}";
        }

        $partes = array_filter($partes, fn ($p) => $p !== null);
        if (count($compras) + count($ventas) > 1) {
            array_unshift($partes, "_Hay varios documentos con el folio {$folio}:_", '');
        }

        return implode("\n", $partes);
    }

    /**
     * GET /api/bot/facturas?token=XXXX&q=haustek[&n=5&lado=compra|venta]
     * Últimas N facturas de un proveedor (compras) y/o de un cliente (ventas),
     * buscando por nombre o RUT. Solo facturas (no notas de crédito ni boletas).
     */
    public function facturas(Request $r)
    {
        $this->verificarToken($r);

        $q    = trim((string) $r->query('q', ''));
        $n    = max(1, min(15, (int) $r->query('n', 5)));
        $lado = $r->query('lado');
        if (mb_strlen($q) < 2) {
            return response()->json(['ok' => false, 'error' => 'Indica el proveedor o cliente'], 422);
        }

        $palabras = array_filter(explode(' ', preg_replace('/\s+/', ' ', $q)));
        $compras  = $lado === 'venta'  ? [] : $this->listarCompras($palabras, $n);
        $ventas   = $lado === 'compra' ? [] : $this->listarVentas($q, $n);

        return response()->json([
            'ok'      => true,
            'q'       => $q,
            'texto'   => $this->formatearListado($q, $n, $compras, $ventas),
            'compras' => $compras,
            'ventas'  => $ventas,
        ]);
    }

    private function listarCompras(array $palabras, int $n): array
    {
        $ef = app(CuentasPorPagarController::class)->efectivoPagadoSub();

        return DB::table('compras')
            ->leftJoin($ef, 'ef.compra_id', '=', 'compras.id')
            ->whereIn('compras.tipo_dte', [33, 34])
            ->where(function ($o) use ($palabras) {
                foreach ($palabras as $p) {
                    $o->where(fn ($w) => $w->where('compras.nombre_emisor', 'like', "%$p%")
                                           ->orWhere('compras.rut_emisor', 'like', "%$p%"));
                }
            })
            ->orderByDesc('compras.fecha_emision')
            ->orderByDesc('compras.id')
            ->limit($n)
            ->get(['compras.folio', 'compras.nombre_emisor', 'compras.fecha_emision', 'compras.total',
                   'compras.pagado_historico', DB::raw('COALESCE(ef.monto_pagado_efectivo, 0) as pagado')])
            ->map(fn ($c) => [
                'folio'     => $c->folio,
                'quien'     => $c->nombre_emisor,
                'fecha'     => $c->fecha_emision ? \Carbon\Carbon::parse($c->fecha_emision)->format('d/m/Y') : null,
                'total'     => (int) $c->total,
                'pendiente' => $c->pagado_historico ? 0 : max(0, (int) $c->total - (int) round($c->pagado)),
                'pagado'    => (int) round($c->pagado),
            ])->all();
    }

    private function listarVentas(string $q, int $n): array
    {
        $data = app(CuentasPorCobrarController::class)
            ->registroVentas(new Request(['buscar' => $q]))
            ->getData(true);

        return collect($data['documentos'] ?? [])
            ->filter(fn ($d) => empty($d['es_nc']) && !empty($d['numero_documento_bsale']))
            ->take($n)
            ->map(fn ($d) => [
                'folio'     => $d['numero_documento_bsale'],
                'quien'     => $d['razon_social'] ?? null,
                'fecha'     => !empty($d['fecha_emision']) ? \Carbon\Carbon::parse($d['fecha_emision'])->format('d/m/Y') : null,
                'total'     => (int) round($d['monto'] ?? 0),
                'pendiente' => max(0, (int) round($d['pendiente'] ?? 0)),
                'pagado'    => (int) round($d['monto_cobrado'] ?? 0),
            ])->values()->all();
    }

    private function formatearListado(string $q, int $n, array $compras, array $ventas): string
    {
        if (!$compras && !$ventas) {
            return "🔎 No encontré facturas de \"{$q}\" (busco por nombre o RUT del proveedor o cliente).";
        }

        $bloque = function (string $titulo, array $filas, string $verbo) {
            $nombres = array_values(array_unique(array_filter(array_column($filas, 'quien'))));
            $unico   = count($nombres) === 1;
            $out     = ['', '🧾 *' . $titulo . ($unico ? " — {$nombres[0]}" : '') . '*'];
            $pend    = 0;
            foreach ($filas as $f) {
                $estado = $f['pendiente'] > 0
                    ? ($f['pagado'] > 0 ? "⚠️ parcial, {$verbo} {$this->clp($f['pendiente'])}" : "⏳ {$verbo} {$this->clp($f['pendiente'])}")
                    : '✅';
                $quien = $unico ? '' : " — {$f['quien']}";
                $out[] = "• N° {$f['folio']} — {$f['fecha']} — {$this->clp($f['total'])} — {$estado}{$quien}";
                $pend += $f['pendiente'];
            }
            if ($pend > 0) $out[] = "_Total {$verbo} en estas: {$this->clp($pend)}_";
            return $out;
        };

        $partes = [];
        if ($compras) $partes = array_merge($partes, $bloque('Últimas ' . count($compras) . ' facturas de compra', $compras, 'por pagar'));
        if ($ventas)  $partes = array_merge($partes, $bloque('Últimas ' . count($ventas) . ' facturas de venta', $ventas, 'por cobrar'));

        return ltrim(implode("\n", $partes), "\n");
    }

    /**
     * GET /api/bot/clientes?token=XXXX&q=juan perez
     * Busca clientes por nombre, razón social o RUT (hasta 5), con su % de descuento.
     */
    public function clientes(Request $r)
    {
        $this->verificarToken($r);

        $q = trim((string) $r->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['ok' => false, 'error' => 'Texto de búsqueda muy corto'], 422);
        }

        $encontrados = collect(app(ClienteController::class)->buscar(new Request(['q' => $q]))->getData(true))->take(5);
        $desc = DB::table('clientes')->whereIn('id', $encontrados->pluck('id'))->pluck('descuento_productos', 'id');

        return response()->json([
            'ok'       => true,
            'clientes' => $encontrados->map(fn ($c) => [
                'id'                  => $c['id'],
                'nombre'              => $c['razon_social'] ?: trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')),
                'rut'                 => $c['identification'] ?? null,
                'telefono'            => $c['phone'] ?? null,
                'descuento_productos' => (float) ($desc[$c['id']] ?? 0),
            ])->values(),
        ]);
    }

    /**
     * POST /api/bot/cotizacion?token=XXXX[&confirmar=1]
     * Body: { cliente_id, observaciones?, items: [
     *   { lista_precio_id?, nombre?, cantidad, precio?, incluye_iva?, descuento? } ] }
     *
     * - Ítem de lista: indica lista_precio_id (el precio sale de la lista, con el descuento
     *   del cliente). Si además trae `precio`, ese precio manual manda.
     * - Ítem libre: sin lista_precio_id; requiere nombre y precio manual.
     * - `precio` es NETO salvo que `incluye_iva` sea true (se convierte a neto).
     *
     * Sin `confirmar` solo devuelve la vista previa. Con `confirmar=1` crea la cotización
     * (misma que Cotización Rápida) y devuelve el link público del PDF.
     */
    public function cotizacion(Request $r)
    {
        $this->verificarToken($r);

        $cli = DB::table('clientes')->where('id', (int) $r->input('cliente_id'))->first();
        if (!$cli) {
            return response()->json(['ok' => false, 'error' => 'Cliente no encontrado. Búscalo primero con buscar_cliente.'], 422);
        }

        $items = $r->input('items');
        if (!is_array($items) || !$items) {
            return response()->json(['ok' => false, 'error' => 'La cotización no tiene ítems.'], 422);
        }
        if (count($items) > 30) {
            return response()->json(['ok' => false, 'error' => 'Máximo 30 ítems por cotización.'], 422);
        }

        [$norm, $errores] = $this->normalizarItemsCotizacion($items, (float) ($cli->descuento_productos ?? 0));
        if ($errores) {
            return response()->json(['ok' => false, 'error' => implode(' | ', $errores)], 422);
        }

        $neto    = (int) round(array_sum(array_column($norm, 'total')));
        $nombre  = $cli->razon_social ?: trim("{$cli->first_name} {$cli->last_name}");
        $texto   = $this->formatearCotizacion($nombre, $cli->identification ?? null, $norm, $neto, (float) ($cli->descuento_productos ?? 0));
        $out     = ['ok' => true, 'texto' => $texto, 'neto' => $neto, 'total_con_iva' => (int) round($neto * 1.19)];

        if (!filter_var($r->query('confirmar', false), FILTER_VALIDATE_BOOLEAN)) {
            return response()->json($out);
        }

        // Vendedor: configurable (BOT_VENDEDOR_ID); si no, el fallback habitual de guardarCotizacion.
        $vid = config('services.bot.vendedor_id');
        if ($vid && ($u = \App\Models\User::find((int) $vid))) {
            auth()->setUser($u);
        }

        $resp = app(VentaExpressController::class)->guardarCotizacion(new Request([
            'cliente_id'    => $cli->id,
            'observaciones' => $r->input('observaciones'),
            'items'         => array_map(fn ($i) => [
                'nombre'      => $i['nombre'],
                'cantidad'    => $i['cantidad'],
                'precio'      => $i['precio'],
                'descuento'   => $i['descuento'],
                'producto_id' => $i['producto_id'],
            ], $norm),
        ]))->getData(true);

        $cot = \App\Models\Cotizacion::find($resp['cotizacion_id'] ?? 0);
        if (!$cot) {
            return response()->json(['ok' => false, 'error' => 'No se pudo crear la cotización.'], 500);
        }

        return response()->json($out + [
            'cotizacion_id' => $cot->id,
            'url'           => url("/p/cotizacion/{$cot->publicToken()}"),
        ]);
    }

    // Deja cada ítem con nombre, cantidad, precio NETO, descuento y total de línea.
    private function normalizarItemsCotizacion(array $items, float $descCliente): array
    {
        $norm = [];
        $errores = [];

        foreach (array_values($items) as $idx => $it) {
            $n = $idx + 1;
            $cant = (float) ($it['cantidad'] ?? 0);
            if ($cant <= 0) { $errores[] = "Ítem {$n}: la cantidad debe ser mayor que 0"; continue; }

            $precioManual = isset($it['precio']) && $it['precio'] !== '' ? (float) $it['precio'] : null;
            if ($precioManual !== null && $precioManual < 0) { $errores[] = "Ítem {$n}: precio inválido"; continue; }
            if ($precioManual !== null && !empty($it['incluye_iva'])) {
                $precioManual = $precioManual / 1.19; // el usuario dio el precio con IVA
            }

            $productoId = null;
            $esLista    = !empty($it['lista_precio_id']);

            if ($esLista) {
                $lp = DB::table('lista_precios as lp')
                    ->join('productos as p', 'p.id', '=', 'lp.producto_id')
                    ->leftJoin('colores as c', 'c.id', '=', 'lp.color_id')
                    ->leftJoin('producto_color_proveedor as pcp', 'pcp.id', '=', 'lp.producto_color_proveedor_id')
                    ->leftJoin('colores as c2', 'c2.id', '=', 'pcp.color_id')
                    ->where('lp.id', (int) $it['lista_precio_id'])->where('lp.activo', 1)
                    ->first(['lp.producto_id', 'lp.precio_venta', 'p.nombre',
                             DB::raw('COALESCE(c.nombre, c2.nombre) as color')]);
                if (!$lp) { $errores[] = "Ítem {$n}: producto de lista no encontrado (búscalo de nuevo)"; continue; }

                $nombre     = $lp->nombre . ($lp->color ? " — {$lp->color}" : '');
                $precio     = $precioManual ?? (float) $lp->precio_venta;
                $productoId = (int) $lp->producto_id;
                // Descuento del cliente solo sobre productos de lista y si no se fijó precio manual
                $desc       = isset($it['descuento']) ? (float) $it['descuento'] : ($precioManual === null ? $descCliente : 0);
            } else {
                $nombre = trim((string) ($it['nombre'] ?? ''));
                if ($nombre === '')        { $errores[] = "Ítem {$n}: falta el nombre/descripción"; continue; }
                if ($precioManual === null) { $errores[] = "Ítem {$n} ({$nombre}): falta el precio"; continue; }
                $precio = $precioManual;
                $desc   = (float) ($it['descuento'] ?? 0);
            }

            $desc = max(0, min(100, $desc));
            $norm[] = [
                'nombre'      => $nombre,
                'cantidad'    => $cant,
                'precio'      => round($precio),
                'descuento'   => $desc,
                'producto_id' => $productoId,
                'es_libre'    => !$esLista,
                'total'       => round($precio) * $cant * (1 - $desc / 100),
            ];
        }

        return [$norm, $errores];
    }

    private function formatearCotizacion(string $cliente, ?string $rut, array $norm, int $neto, float $descCliente): string
    {
        $partes = ['📝 *Cotización para ' . $cliente . '*' . ($rut ? " ({$rut})" : '')];

        foreach ($norm as $i => $it) {
            $cant = rtrim(rtrim(number_format($it['cantidad'], 2, ',', ''), '0'), ',');
            $desc = $it['descuento'] > 0 ? " (-" . rtrim(rtrim(number_format($it['descuento'], 2, ',', ''), '0'), ',') . '%)' : '';
            $tag  = $it['es_libre'] ? ' _(libre)_' : '';
            $partes[] = ($i + 1) . ". {$cant} x {$it['nombre']}{$tag} — {$this->clp($it['precio'])} c/u{$desc} = {$this->clp($it['total'])}";
        }

        $iva = (int) round($neto * 0.19);
        $partes[] = '';
        $partes[] = "*Neto {$this->clp($neto)}* · IVA {$this->clp($iva)} · *Total {$this->clp($neto + $iva)}*";

        return implode("\n", $partes);
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
