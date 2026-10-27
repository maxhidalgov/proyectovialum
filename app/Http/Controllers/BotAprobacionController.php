<?php

namespace App\Http\Controllers;

use App\Models\Cotizacion;
use App\Models\DocumentoFacturacion;
use App\Models\EstadoCotizacion;
use App\Models\IngresoManual;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Aprobar cotización" desde el bot de WhatsApp (solo el dueño, por chat privado).
 * Reutiliza lo que ya hace la app: importar de Winperfil (importarUno), venta parcial / precio /
 * material / EETT (confirmarImportWinperfil) y el vínculo de documentos. Protegido por el token del bot.
 *
 *   GET  /api/bot/aprobar/buscar?q=becker          → candidatas en la app y en Winperfil (sin importar)
 *   POST /api/bot/aprobar/importar                 → importa un presupuesto de Winperfil (como el botón Importar)
 *   GET  /api/bot/aprobar/cotizacion/{id}          → resumen de una cotización (ventanas numeradas, abonos)
 *   POST /api/bot/aprobar[?confirmar=1]            → vista previa (no escribe) o aprobación real
 *   GET  /api/bot/aprobar/graficos/{id}            → dibujos (SVG) de las ventanas que aún no tienen imagen
 *   POST /api/bot/aprobar/graficos/{id}            → guarda las imágenes que el bot generó desde esos SVG
 */
class BotAprobacionController extends Controller
{
    private const FORMAS = [
        'efectivo'        => 'Efectivo',
        'transferencia'   => 'Transferencia',
        'tarjeta_credito' => 'Tarjeta de crédito',
        'tarjeta_debito'  => 'Tarjeta de débito',
        'cheque'          => 'Cheque',
    ];
    private const TIPOS_DOC = ['boleta' => 1, 'factura' => 5];
    private const ESTADOS_YA_APROBADOS = ['Aprobada', 'En Producción', 'Entregada', 'Facturada'];

    private function verificarToken(Request $r): void
    {
        $token = config('services.bot.token');
        if (empty($token) || !hash_equals($token, (string) $r->query('token'))) {
            abort(403, 'Token inválido');
        }
    }

    private function clp($n): string
    {
        return '$' . number_format((float) $n, 0, ',', '.');
    }

    private function palabras(string $q): array
    {
        $q = mb_strtolower($q);
        $q = strtr($q, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        return array_values(array_filter(explode(' ', trim(preg_replace('/[^a-z0-9]+/', ' ', $q)))));
    }

    private function nombreCliente($c): string
    {
        return trim((string) ($c->razon_social ?: trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? ''))));
    }

    private function urlCotizacion(Cotizacion $c): string
    {
        return url("/p/cotizacion/{$c->publicToken()}");
    }

    /** Ventanas de Winperfil de la cotización, numeradas en el mismo orden que ve el usuario. */
    private function ventanasDe(Cotizacion $c): array
    {
        $lineas = $c->detalles->where('tipo_item', 'winperfil')->sortBy('id')->values();

        return $lineas->map(fn ($l, $i) => [
            'n'           => $i + 1,
            'id'          => $l->id,
            'descripcion' => trim(explode('—', (string) $l->descripcion)[0]) ?: ('Ventana ' . ($i + 1)),
            'cantidad'    => (float) $l->cantidad,
            'neto'        => (float) $l->total,
        ])->all();
    }

    /** GET /api/bot/aprobar/buscar?q=marianela becker */
    public function buscar(Request $r)
    {
        $this->verificarToken($r);
        $palabras = $this->palabras((string) $r->query('q', ''));
        if (!$palabras) {
            return response()->json(['ok' => false, 'error' => 'Indica el nombre del cliente.'], 422);
        }

        $nombreSql = "LOWER(CONCAT_WS(' ', cl.razon_social, cl.first_name, cl.last_name))";
        $query = DB::table('cotizaciones as c')
            ->join('clientes as cl', 'cl.id', '=', 'c.cliente_id')
            ->leftJoin('estados_cotizacion as e', 'e.id', '=', 'c.estado_cotizacion_id');
        foreach ($palabras as $p) {
            $query->whereRaw("{$nombreSql} LIKE ?", ['%' . $p . '%']);
        }
        $locales = $query->orderByDesc('c.id')->limit(8)->get([
            'c.id', 'c.fecha', 'c.total', 'c.winperfil_numero', 'e.nombre as estado', 'c.estado_produccion',
            DB::raw("TRIM(CONCAT_WS(' ', cl.razon_social, cl.first_name, cl.last_name)) as cliente"),
            DB::raw("(SELECT COUNT(*) FROM cotizacion_detalles d WHERE d.cotizacion_id = c.id AND d.tipo_item = 'winperfil') as n_ventanas"),
        ])->map(fn ($c) => [
            'cotizacion_id' => $c->id, 'cliente' => $c->cliente, 'fecha' => substr((string) $c->fecha, 0, 10),
            'neto' => (int) round($c->total), 'estado' => $c->estado, 'winperfil_numero' => $c->winperfil_numero,
            'n_ventanas' => (int) $c->n_ventanas, 'ya_aprobada' => in_array($c->estado, self::ESTADOS_YA_APROBADOS, true),
        ])->all();

        // Presupuestos de Winperfil (últimos 120 días) que todavía no están en la app
        $winperfil = [];
        $errorWinperfil = null;
        try {
            $resp = app(WinperfilController::class)->getPresupuestos(new Request([
                'desde' => now()->subDays(120)->format('Y-m-d'), 'hasta' => now()->format('Y-m-d'),
            ]));
            $items = $resp->getData(true);
            if (isset($items['error'])) {
                $errorWinperfil = (string) $items['error'];
            } else {
                foreach ($items as $p) {
                    if (!empty($p['_synced'])) {
                        continue;
                    }
                    $nom = $this->palabras((string) ($p['NOMBRECLIENTE'] ?? ''));
                    $nomTxt = ' ' . implode(' ', $nom);
                    $ok = true;
                    foreach ($palabras as $pal) {
                        if (!str_contains($nomTxt, $pal)) {
                            $ok = false;
                            break;
                        }
                    }
                    if ($ok) {
                        $winperfil[] = [
                            'serie' => $p['PRESUPUESTO_SERIE'] ?? null, 'presupuesto' => $p['PRESUPUESTO_NUMERO'] ?? null,
                            'numfactura' => $p['NUMFACTURA'] ?? null, 'cliente' => trim((string) ($p['NOMBRECLIENTE'] ?? '')),
                            'fecha' => substr((string) ($p['FECHAFACTURA'] ?? ''), 0, 10), 'neto' => (int) round((float) ($p['BASE'] ?? 0)),
                            'aceptado' => $p['ACEPTADO'] ?? null,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            $errorWinperfil = 'Winperfil no respondió';
        }

        return response()->json([
            'ok' => true, 'en_app' => $locales, 'en_winperfil' => array_slice($winperfil, 0, 8),
            'winperfil_error' => $errorWinperfil,
        ]);
    }

    /** POST /api/bot/aprobar/importar  { serie?, presupuesto, numfactura } */
    public function importar(Request $r)
    {
        $this->verificarToken($r);
        $presupuesto = $r->input('presupuesto');
        if (!$presupuesto) {
            return response()->json(['ok' => false, 'error' => 'Falta el número de presupuesto.'], 422);
        }
        $req = new Request(array_filter([
            'serie' => $r->input('serie'), 'numero' => $presupuesto, 'numfactura' => $r->input('numfactura'),
        ], fn ($v) => $v !== null && $v !== ''));
        $resp = app(WinperfilController::class)->importarUno($req);
        $data = $resp->getData(true);
        if ($resp->getStatusCode() >= 400 || empty($data['cotizacion']['id'])) {
            return response()->json(['ok' => false, 'error' => $data['error'] ?? 'No se pudo importar el presupuesto.'], 422);
        }

        return $this->resumen((int) $data['cotizacion']['id'], $r, true);
    }

    /** GET /api/bot/aprobar/cotizacion/{id} */
    public function cotizacion(Request $r, $id)
    {
        $this->verificarToken($r);

        return $this->resumen((int) $id, $r);
    }

    private function resumen(int $id, Request $r, bool $importada = false)
    {
        $c = Cotizacion::with(['detalles', 'cliente', 'estado'])->find($id);
        if (!$c) {
            return response()->json(['ok' => false, 'error' => "No existe la cotización #{$id}."], 404);
        }
        $docs = DB::table('documentos_facturacion')->where('cotizacion_id', $id)->where('estado', 'emitido')
            ->get(['tipo_documento_bsale_id', 'numero_documento_bsale', 'monto']);
        $notas = (float) DB::table('ingresos_manuales')->where('cotizacion_id', $id)->sum('monto');

        return response()->json([
            'ok' => true, 'importada_ahora' => $importada, 'cotizacion_id' => $c->id,
            'cliente' => $c->cliente ? $this->nombreCliente($c->cliente) : null,
            'estado' => $c->estado->nombre ?? null, 'estado_produccion' => $c->estado_produccion,
            'neto' => (int) round($c->total), 'total_con_iva' => (int) round($c->total * 1.19),
            'material' => $c->material, 'eett' => $c->eett, 'winperfil_numero' => $c->winperfil_numero,
            'ventanas' => $this->ventanasDe($c),
            'otros_items' => $c->detalles->where('tipo_item', '!=', 'winperfil')->count(),
            'documentos' => $docs->map(fn ($d) => [
                'tipo' => (int) $d->tipo_documento_bsale_id === 1 ? 'boleta' : ((int) $d->tipo_documento_bsale_id === 5 ? 'factura' : 'otro'),
                'folio' => $d->numero_documento_bsale, 'bruto' => (int) $d->monto,
            ])->all(),
            'notas_de_venta' => (int) $notas,
            'url' => $this->urlCotizacion($c),
        ]);
    }

    /**
     * GET /api/bot/aprobar/graficos/{id}
     * Las imágenes de las ventanas las genera el NAVEGADOR cuando alguien importa desde la app; al importar
     * por el bot nadie las genera y el PDF sale "Sin imagen". El bot las renderiza y las sube aquí.
     */
    public function graficosPendientes(Request $r, $id)
    {
        $this->verificarToken($r);
        $det = \App\Models\CotizacionDetalle::where('cotizacion_id', (int) $id)->where('tipo_item', 'winperfil')
            ->whereNotNull('winperfil_grafico')->whereNull('winperfil_grafico_png')->get(['id', 'winperfil_grafico']);

        return response()->json(['ok' => true, 'graficos' => $det->map(fn ($d) => ['id' => $d->id, 'svg' => $d->winperfil_grafico])->all()]);
    }

    /** POST /api/bot/aprobar/graficos/{id}  { graficos: { detalleId: "data:image/jpeg;base64,..." } } */
    public function guardarGraficos(Request $r, $id)
    {
        $this->verificarToken($r);
        $resp = app(CotizacionController::class)->guardarGraficosPng((int) $id, new Request(['graficos' => (array) $r->input('graficos', [])]));

        return response()->json(['ok' => true] + $resp->getData(true));
    }

    /** POST /api/bot/aprobar[?confirmar=1] */
    public function aprobar(Request $r)
    {
        $this->verificarToken($r);
        $confirmar = filter_var($r->query('confirmar', false), FILTER_VALIDATE_BOOLEAN);

        $c = Cotizacion::with(['detalles', 'cliente', 'estado'])->find((int) $r->input('cotizacion_id'));
        if (!$c) {
            return $this->error('No encontré esa cotización. Búscala primero.');
        }
        $ventanas = $this->ventanasDe($c);
        $esWinperfil = count($ventanas) > 0;
        $yaAprobada = in_array($c->estado->nombre ?? '', self::ESTADOS_YA_APROBADOS, true);

        // ── Ventanas vendidas ────────────────────────────────────────────────────────
        $vend = $r->input('ventanas', 'todas');
        $excluidas = [];
        $vendidasTxt = null;
        if ($esWinperfil) {
            if (is_array($vend)) {
                $pos = array_values(array_unique(array_map('intval', $vend)));
                $validas = array_column($ventanas, 'n');
                $malas = array_diff($pos, $validas);
                if ($malas || !$pos) {
                    return $this->error('Las ventanas indicadas no existen. Esta cotización tiene las ventanas 1 a ' . count($ventanas) . '.');
                }
                foreach ($ventanas as $v) {
                    if (!in_array($v['n'], $pos, true)) {
                        $excluidas[] = $v['id'];
                    }
                }
                $vendidasTxt = count($pos) . ' de ' . count($ventanas) . ' (' . implode(', ', array_map(
                    fn ($v) => $v['descripcion'], array_filter($ventanas, fn ($v) => in_array($v['n'], $pos, true)))) . ')';
            } else {
                $vendidasTxt = 'todas (' . count($ventanas) . ')';
            }
        } elseif (is_array($vend) || $r->filled('total')) {
            return $this->error('Esta cotización no viene de Winperfil: no puedo elegir ventanas ni cambiar el precio desde aquí.');
        }

        // ── Precio ────────────────────────────────────────────────────────────────────
        $totalNuevo = null;
        $tipoTotal = null;
        if ($r->filled('total')) {
            $totalNuevo = (float) $r->input('total');
            $tipoTotal = $r->input('total_tipo') === 'bruto' ? 'bruto' : ($r->input('total_tipo') === 'neto' ? 'neto' : null);
            if ($totalNuevo <= 0 || !$tipoTotal) {
                return $this->error('Para cambiar el precio dime el monto y si es neto o bruto (con IVA).');
            }
        }

        $material = $r->input('material');
        if ($material !== null && !in_array($material, ['PVC', 'Aluminio', 'Otros'], true)) {
            return $this->error('El material debe ser PVC, Aluminio u Otros.');
        }
        $eett = $r->filled('eett') ? mb_substr(trim((string) $r->input('eett')), 0, 120) : null;

        // ── Documento ─────────────────────────────────────────────────────────────────
        $pagos = (array) $r->input('pagos', []);
        $docIn = (array) $r->input('documento', []);
        $docTipo = $docIn['tipo'] ?? null;
        $doc = null;
        if ($pagos && !in_array($docTipo, ['boleta', 'factura', 'ninguno'], true)) {
            return $this->error('Hay pagos: falta saber si se hizo boleta, factura o ningún documento.');
        }
        if (in_array($docTipo, ['boleta', 'factura'], true)) {
            $folio = (int) preg_replace('/\D/', '', (string) ($docIn['folio'] ?? ''));
            if ($folio <= 0) {
                return $this->error('Dime el número (folio) de la ' . $docTipo . '.');
            }
            $doc = DocumentoFacturacion::where('tipo_documento_bsale_id', self::TIPOS_DOC[$docTipo])
                ->where('numero_documento_bsale', $folio)->where('estado', 'emitido')->get();
            if ($doc->count() > 1) {
                return $this->error("Hay varias {$docTipo}s con el folio {$folio} en la app; no puedo elegir cuál.");
            }
            $doc = $doc->first();
            if (!$doc) {
                return $this->error("No encuentro la {$docTipo} N° {$folio} en la app. Se sincroniza con Bsale a las 7:00; si es de hoy, espera o revisa el folio.");
            }
            if ($doc->cotizacion_id && (int) $doc->cotizacion_id !== (int) $c->id) {
                return $this->error("La {$docTipo} N° {$folio} ya está vinculada a la cotización #{$doc->cotizacion_id}.");
            }
        }

        // ── Pagos ─────────────────────────────────────────────────────────────────────
        $pagosOk = [];
        foreach ($pagos as $i => $p) {
            $n = $i + 1;
            $forma = $p['forma'] ?? null;
            $monto = (float) ($p['monto'] ?? 0);
            if (!isset(self::FORMAS[$forma])) {
                return $this->error("Pago {$n}: la forma de pago debe ser efectivo, transferencia, tarjeta de crédito, tarjeta de débito o cheque.");
            }
            if ($monto <= 0) {
                return $this->error("Pago {$n}: falta el monto.");
            }
            try {
                $fecha = !empty($p['fecha']) ? \Carbon\Carbon::parse($p['fecha'])->toDateString() : now()->toDateString();
            } catch (\Throwable $e) {
                return $this->error("Pago {$n}: la fecha no es válida.");
            }
            $pagosOk[] = [
                'forma' => $forma, 'monto' => $monto, 'fecha' => $fecha,
                'nro' => isset($p['nro_transaccion']) && $p['nro_transaccion'] !== '' ? mb_substr(trim((string) $p['nro_transaccion']), 0, 60) : null,
            ];
        }

        // ── Vista previa ──────────────────────────────────────────────────────────────
        $netoBase = $esWinperfil && $excluidas
            ? array_sum(array_map(fn ($v) => $v['neto'], array_filter($ventanas, fn ($v) => !in_array($v['id'], $excluidas, true))))
            : (float) $c->total;
        $netoFinal = $totalNuevo ? ($tipoTotal === 'bruto' ? $totalNuevo / 1.19 : $totalNuevo) : $netoBase;
        $brutoFinal = $netoFinal * 1.19;

        $cliente = $c->cliente ? $this->nombreCliente($c->cliente) : 'Sin cliente';
        $soloPagos = $yaAprobada && $pagosOk && !$totalNuevo && !$excluidas && $material === null && $eett === null;
        $L = [($soloPagos ? "*Registrar pago en cotización #{$c->id}*" : "*Aprobar cotización #{$c->id}*") . " — {$cliente}"];
        if ($c->winperfil_numero && !$soloPagos) {
            $L[] = "Winperfil N° {$c->winperfil_numero}";
        }
        if ($esWinperfil && !$soloPagos) {
            $L[] = 'Ventanas vendidas: ' . $vendidasTxt;
            if ($excluidas) {
                $L[] = '_Queda anotado que falta crear en Winperfil la oferta con solo lo vendido._';
            }
        }
        $L[] = 'Total: *' . $this->clp($netoFinal) . ' neto* (' . $this->clp($brutoFinal) . ' con IVA)'
            . ($totalNuevo || $excluidas ? ' — antes ' . $this->clp($c->total) . ' neto' : '');
        if ($totalNuevo && !$excluidas && $c->total > 0 && abs($netoFinal - $c->total) / $c->total > 0.10) {
            $L[] = '⚠️ El precio cambia ' . round(($netoFinal - $c->total) / $c->total * 100) . '% respecto a la cotización. Si no es lo que querías, dime el monto correcto.';
        }
        if (($material || $c->material) && !$soloPagos) {
            $L[] = 'Material: ' . ($material ?: $c->material);
        }
        if (($eett || $c->eett) && !$soloPagos) {
            $L[] = 'EETT: ' . ($eett ?: $c->eett);
        }
        if (!$soloPagos) {
            $L[] = $yaAprobada
                ? "Estado: ya está *{$c->estado->nombre}* (no cambia)"
                : 'Estado: *Aprobada* (entra a Operaciones en "En Espera de Medidas")';
        }
        if ($doc) {
            $L[] = ucfirst($docTipo) . " N° {$doc->numero_documento_bsale} por " . $this->clp($doc->monto) . ' (se vincula a esta cotización)';
        } elseif ($docTipo === 'ninguno') {
            $L[] = 'Sin documento todavía: cada pago queda como *nota de venta*';
        }
        $totalPagos = 0;
        foreach ($pagosOk as $p) {
            $totalPagos += $p['monto'];
            $L[] = '• ' . $this->clp($p['monto']) . ' · ' . self::FORMAS[$p['forma']] . ' · ' . \Carbon\Carbon::parse($p['fecha'])->format('d/m')
                . ($p['nro'] ? ' · voucher ' . $p['nro'] : (in_array($p['forma'], ['tarjeta_credito', 'tarjeta_debito'], true) ? ' · ⚠️ sin N° de voucher' : ''));
        }
        if ($pagosOk && !$doc) {
            $yaNotas = (float) DB::table('ingresos_manuales')->where('cotizacion_id', $c->id)->sum('monto');
            if ($yaNotas > 0) {
                $L[] = 'Ya registrado como nota de venta: ' . $this->clp($yaNotas) . ' (esto se suma)';
                $totalPagos += $yaNotas;
            }
        }
        if ($pagosOk) {
            $L[] = 'Pagado: ' . $this->clp($totalPagos) . ' de ' . $this->clp($brutoFinal) . ' (' . round($totalPagos / max($brutoFinal, 1) * 100) . '%)'
                . ($doc ? ' — pagos informativos: el abono lo cuenta la ' . $docTipo : '');
        }
        $L[] = 'Ver cotización: ' . $this->urlCotizacion($c);
        if ($totalNuevo || $excluidas) {
            $L[] = '_El link muestra la cotización como está ahora; el precio y las ventanas nuevas se aplican al aprobar._';
        }
        $texto = implode("\n", $L);

        if (!$confirmar) {
            return response()->json(['ok' => true, 'texto' => $texto, 'cotizacion_id' => $c->id, 'url' => $this->urlCotizacion($c)]);
        }

        // ── Escritura ─────────────────────────────────────────────────────────────────
        $aprobadaId = EstadoCotizacion::where('nombre', 'Aprobada')->value('id');
        if (!$aprobadaId) {
            return $this->error('No existe el estado "Aprobada" en la app.', 500);
        }
        try {
            if ($esWinperfil) {
                $req = new Request(array_filter([
                    'estado_cotizacion_id' => $yaAprobada ? null : $aprobadaId,
                    'material' => $material, 'eett' => $eett,
                    'total' => $totalNuevo, 'tipo' => $tipoTotal,
                    'ventanas_excluidas' => $excluidas ?: null,
                ], fn ($v) => $v !== null));
                $resp = app(CotizacionController::class)->confirmarImportWinperfil($req, $c->id);
                if ($resp->getStatusCode() >= 400) {
                    return $this->error($resp->getData(true)['message'] ?? 'No se pudo aprobar la cotización.', 422);
                }
            } else {
                $upd = array_filter(['material' => $material, 'eett' => $eett], fn ($v) => $v !== null);
                if (!$yaAprobada) {
                    $upd['estado_cotizacion_id'] = $aprobadaId;
                    if (empty($c->estado_produccion)) {
                        $upd['estado_produccion'] = 'En Espera de Medidas';
                    }
                }
                if ($upd) {
                    $c->update($upd);
                }
            }

            DB::transaction(function () use ($c, $doc, $pagosOk, $docTipo) {
                if ($doc && !$doc->cotizacion_id) {
                    $c->refresh();
                    $pct = $c->total > 0 ? round(($doc->monto / ($c->total * 1.19)) * 100, 2) : 0;
                    $doc->update(['cotizacion_id' => $c->id, 'porcentaje' => $pct]);
                }
                foreach ($pagosOk as $p) {
                    $ingresoId = null;
                    if (!$doc) {
                        $ingreso = IngresoManual::create([
                            'cotizacion_id' => $c->id, 'fecha' => $p['fecha'], 'monto' => $p['monto'],
                            'descripcion' => 'Abono · ' . self::FORMAS[$p['forma']] . ($p['nro'] ? ' · voucher ' . $p['nro'] : ''),
                            'categoria' => 'Nota de venta', 'notas' => 'Pago informado por el bot (sin documento)',
                        ]);
                        $ingresoId = $ingreso->id;
                    }
                    DB::table('cotizacion_pagos')->insert([
                        'cotizacion_id' => $c->id, 'fecha' => $p['fecha'], 'monto' => $p['monto'], 'forma_pago' => $p['forma'],
                        'nro_transaccion' => $p['nro'], 'documento_id' => $doc?->id, 'ingreso_manual_id' => $ingresoId,
                        'cuenta_como_abono' => !$doc, 'origen' => 'bot', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            \Log::error('Bot aprobar', ['cotizacion' => $c->id, 'error' => $e->getMessage()]);

            return $this->error('Falló al guardar: ' . $e->getMessage() . '. Revisa la cotización en la app antes de repetirlo.', 500);
        }

        return response()->json([
            'ok' => true, 'cotizacion_id' => $c->id, 'url' => $this->urlCotizacion($c),
            'texto' => ($soloPagos ? "✅ *Pago registrado en la cotización #{$c->id}*" : "✅ *Cotización #{$c->id} aprobada*") . " — {$cliente}\n" . implode("\n", array_slice($L, 1)),
        ]);
    }

    /**
     * POST /api/bot/material-pedido  { q: "marianela becker" | "207", cotizacion_id? }
     * "Pedí el material de X": marca pedido_proveedor en la cotización aprobada del cliente y deja la nota.
     * Sin IA: si hay una sola candidata la marca; si hay varias, las lista para que el dueño indique el N°.
     */
    public function materialPedido(Request $r)
    {
        $this->verificarToken($r);
        $q = trim((string) $r->input('q', ''));
        $id = (int) $r->input('cotizacion_id');
        if (!$id && preg_match('/^#?(\d{1,7})$/', $q, $m)) {
            $id = (int) $m[1];
        }

        if ($id) {
            $c = Cotizacion::with(['cliente', 'estado'])->find($id);
            if (!$c) {
                return $this->error("No encontré la cotización #{$id}.");
            }
        } else {
            $palabras = $this->palabras($q);
            if (!$palabras) {
                return $this->error('Indica el cliente (o el N° de cotización).');
            }
            $nombreSql = "LOWER(CONCAT_WS(' ', cl.razon_social, cl.first_name, cl.last_name))";
            $query = DB::table('cotizaciones as c')
                ->join('clientes as cl', 'cl.id', '=', 'c.cliente_id')
                ->join('estados_cotizacion as e', 'e.id', '=', 'c.estado_cotizacion_id')
                ->whereIn('e.nombre', self::ESTADOS_YA_APROBADOS)
                ->where(fn ($w) => $w->whereNull('c.estado_produccion')->orWhere('c.estado_produccion', '!=', 'Instalada'));
            foreach ($palabras as $p) {
                $query->whereRaw("{$nombreSql} LIKE ?", ['%' . $p . '%']);
            }
            $cands = $query->orderByDesc('c.id')->limit(6)->get([
                'c.id', 'c.fecha', 'c.total', 'c.pedido_proveedor', 'c.estado_produccion',
                DB::raw("TRIM(CONCAT_WS(' ', cl.razon_social, cl.first_name, cl.last_name)) as cliente"),
            ]);
            if ($cands->isEmpty()) {
                return $this->error("No encontré una cotización aprobada de «{$q}» (si aún no está aprobada, apruébala primero).");
            }
            // Si hay varias, solo cuentan las que aún no tienen el material pedido
            $abiertas = $cands->where('pedido_proveedor', 0)->values();
            $pool = $abiertas->isNotEmpty() ? $abiertas : $cands;
            if ($pool->count() > 1) {
                $lista = $pool->map(fn ($x) => "• #{$x->id} — {$x->cliente}, " . substr((string) $x->fecha, 0, 10) . ', neto ' . $this->clp($x->total))->implode("\n");

                return $this->error("Encontré varias cotizaciones aprobadas:\n{$lista}\nDime cuál con el número (ej: «pedido el material de la #{$pool->first()->id}»).");
            }
            $c = Cotizacion::with(['cliente', 'estado'])->find($pool->first()->id);
        }

        $cliente = $this->nombreCliente($c->cliente);
        if ($c->pedido_proveedor) {
            return response()->json(['ok' => true, 'ya' => true, 'cotizacion_id' => $c->id,
                'texto' => "Ya estaba marcado como material pedido: #{$c->id} — {$cliente}."]);
        }

        $nota = 'Material pedido (' . now()->format('d-m-Y') . ', por WhatsApp)';
        $prev = trim((string) $c->notas_operaciones);
        $c->update([
            'pedido_proveedor'  => true,
            'notas_operaciones' => $prev ? ($prev . ' · ' . $nota) : $nota,
        ]);

        $extra = $c->estado_tecnico === 'definitiva' || in_array((string) $c->estado_produccion, ['Lista para Corte', 'En Fabricación', 'Fabricadas OK'], true)
            ? '' : "\n_Ojo: pidió con medidas provisorias; falta la pauta definitiva._";

        return response()->json(['ok' => true, 'ya' => false, 'cotizacion_id' => $c->id,
            'texto' => "✅ Material marcado como *pedido*: #{$c->id} — {$cliente}.{$extra}"]);
    }

    private function error(string $msg, int $status = 422)
    {
        return response()->json(['ok' => false, 'error' => $msg], $status);
    }
}
