<?php

namespace App\Http\Controllers;

use App\Models\Cotizacion;
use App\Models\DocumentoFacturacion;
use App\Models\EstadoCotizacion;
use App\Models\IngresoManual;
use App\Models\VisitaCliente;
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
        $c = $this->resolverAprobada($r);
        if ($c instanceof \Illuminate\Http\JsonResponse) {
            return $c;
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

    /**
     * POST /api/bot/material-fecha  { q, fecha: "YYYY-MM-DD" | null }
     * Fija (o quita, con fecha null) el día límite para pedir el material de una obra; el bot recuerda en base a esto.
     */
    public function materialFecha(Request $r)
    {
        $this->verificarToken($r);
        $fecha = $r->input('fecha');
        if ($fecha !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $fecha)) {
            return $this->error('Fecha inválida.');
        }
        $c = $this->resolverAprobada($r);
        if ($c instanceof \Illuminate\Http\JsonResponse) {
            return $c;
        }
        $c->update(['fecha_pedir_material' => $fecha]);
        $cliente = $this->nombreCliente($c->cliente);
        $texto = $fecha
            ? "📌 Anotado: pedir el material de *{$cliente}* (#{$c->id}) antes del " . \Carbon\Carbon::parse($fecha)->locale('es')->isoFormat('dddd D [de] MMMM') . '.'
            : "Quité la fecha límite de material de {$cliente} (#{$c->id}).";
        if ($c->pedido_proveedor && $fecha) {
            $texto .= "\n_Ojo: esa obra ya figura con el material pedido, no habrá recordatorio._";
        }

        return response()->json(['ok' => true, 'cotizacion_id' => $c->id, 'texto' => $texto]);
    }

    /**
     * GET /api/bot/material-pendiente?modo=manana|tarde
     * Obras aprobadas con fecha límite de pedido de material y sin pedir. manana = vencidas + hoy + mañana;
     * tarde = vencidas + mañana (aviso de la tarde anterior). texto vacío si no hay nada que avisar.
     */
    public function materialPendiente(Request $r)
    {
        $this->verificarToken($r);
        $modo = $r->query('modo') === 'tarde' ? 'tarde' : 'manana';
        $hoy = now('America/Santiago')->startOfDay();
        $limite = $hoy->copy()->addDay()->toDateString();

        $filas = DB::table('cotizaciones as c')
            ->join('clientes as cl', 'cl.id', '=', 'c.cliente_id')
            ->join('estados_cotizacion as e', 'e.id', '=', 'c.estado_cotizacion_id')
            ->whereIn('e.nombre', self::ESTADOS_YA_APROBADOS)
            ->where('c.pedido_proveedor', false)
            ->whereNotNull('c.fecha_pedir_material')
            ->where('c.fecha_pedir_material', '<=', $limite)
            ->orderBy('c.fecha_pedir_material')
            ->get(['c.id', 'c.fecha_pedir_material as f', DB::raw("TRIM(CONCAT_WS(' ', cl.razon_social, cl.first_name, cl.last_name)) as cliente")]);

        $venc = [];
        $hoyL = [];
        $man = [];
        foreach ($filas as $x) {
            $d = \Carbon\Carbon::parse($x->f)->startOfDay();
            $linea = "• {$x->cliente} (#{$x->id})";
            if ($d->lt($hoy)) {
                $venc[] = $linea . ' — venció el ' . $d->format('d-m') . ' (' . (int) $d->diffInDays($hoy) . ' d de atraso)';
            } elseif ($d->eq($hoy)) {
                $hoyL[] = $linea;
            } else {
                $man[] = $linea;
            }
        }
        if ($modo === 'tarde') {
            $hoyL = [];
        }

        $t = [];
        if ($venc) {
            $t[] = "🔴 *Material sin pedir y vencido:*\n" . implode("\n", $venc);
        }
        if ($hoyL) {
            $t[] = "🟠 *Hay que pedir el material HOY:*\n" . implode("\n", $hoyL);
        }
        if ($man) {
            $t[] = "🟡 *Hay que pedir el material MAÑANA:*\n" . implode("\n", $man);
        }
        $texto = $t ? implode("\n\n", $t) . "\n\n_Cuando lo pidas escríbeme «pedido el material de X»._" : '';

        return response()->json(['ok' => true, 'modo' => $modo, 'cantidad' => count($filas), 'texto' => $texto]);
    }

    /**
     * POST /api/bot/visita  { q, tipo: medicion|instalacion|postventa|otro, fechas: ["YYYY-MM-DD",…], hora?, notas?, estado?: programada|realizada }
     * Agenda (o cierra) visitas de una obra en el calendario. Una visita por día (instalación del 12 al 14 = 3).
     * estado=realizada: cierra la última programada de ese tipo (si no hay, deja una realizada con la fecha dada).
     */
    public function visita(Request $r)
    {
        $this->verificarToken($r);
        $tipo = (string) $r->input('tipo');
        if (!in_array($tipo, ['medicion', 'instalacion', 'postventa', 'otro'], true)) {
            return $this->error('Tipo de visita inválido.');
        }
        $fechas = array_values(array_unique(array_filter((array) $r->input('fechas'), fn ($f) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $f))));
        if (!$fechas || count($fechas) > 14) {
            return $this->error('Indica la fecha (o un rango de hasta 14 días).');
        }
        $hora = $r->input('hora');
        if ($hora !== null && !preg_match('/^\d{2}:\d{2}$/', (string) $hora)) {
            return $this->error('Hora inválida.');
        }
        $notas = trim((string) $r->input('notas', '')) ?: null;
        $estado = $r->input('estado') === 'realizada' ? 'realizada' : 'programada';

        $c = $this->resolverAprobada($r);
        if ($c instanceof \Illuminate\Http\JsonResponse) {
            return $c;
        }
        $cliente = $this->nombreCliente($c->cliente);
        $nombreTipo = ['medicion' => 'medición', 'instalacion' => 'instalación', 'postventa' => 'postventa', 'otro' => 'visita'][$tipo];
        $fmt = fn ($f) => \Carbon\Carbon::parse($f)->locale('es')->isoFormat('ddd D [de] MMM');
        $sinPunto = fn (string $s) => rtrim($s, '.');

        if ($estado === 'realizada') {
            // La que se cierra es la más reciente que ya le toca (hoy o antes); si no hay, la próxima
            $prog = VisitaCliente::where('cotizacion_id', $c->id)->where('tipo', $tipo)->where('estado', 'programada');
            $v = (clone $prog)->whereDate('fecha', '<=', now('America/Santiago')->toDateString())->orderByDesc('fecha')->first()
                ?? (clone $prog)->orderBy('fecha')->first();
            if ($v) {
                $v->update(['estado' => 'realizada']);
            } else {
                $v = VisitaCliente::create(['cotizacion_id' => $c->id, 'cliente_id' => $c->cliente_id, 'tipo' => $tipo,
                    'fecha' => $fechas[0], 'hora' => $hora, 'estado' => 'realizada', 'notas' => $notas]);
            }

            return response()->json(['ok' => true, 'cotizacion_id' => $c->id,
                'texto' => "✅ Registré la {$nombreTipo} de *{$cliente}* (#{$c->id}) como realizada ({$sinPunto($fmt($v->fecha))})."]);
        }

        $creadas = 0;
        foreach ($fechas as $f) {
            $v = VisitaCliente::where('cotizacion_id', $c->id)->where('tipo', $tipo)->whereDate('fecha', $f)->where('estado', '!=', 'cancelada')->first();
            if ($v) {
                $v->update(array_filter(['hora' => $hora, 'notas' => $notas], fn ($x) => $x !== null));
            } else {
                VisitaCliente::create(['cotizacion_id' => $c->id, 'cliente_id' => $c->cliente_id, 'tipo' => $tipo,
                    'fecha' => $f, 'hora' => $hora, 'estado' => 'programada', 'notas' => $notas]);
                $creadas++;
            }
        }
        sort($fechas);
        $cuando = count($fechas) === 1 ? $sinPunto($fmt($fechas[0])) : 'del ' . $fmt($fechas[0]) . ' al ' . $sinPunto($fmt(end($fechas)));
        $texto = "📍 Agendé la {$nombreTipo} de *{$cliente}* (#{$c->id}): {$cuando}" . ($hora ? " a las {$hora}" : '') . ($notas ? " — {$notas}" : '') . '.';
        if (!$creadas) {
            $texto .= "\n_Ya estaba agendada; actualicé hora/notas._";
        }

        return response()->json(['ok' => true, 'cotizacion_id' => $c->id, 'texto' => $texto]);
    }

    /**
     * GET /api/bot/visitas-agenda
     * Visitas de hoy y de mañana, y las programadas que ya pasaron sin cerrar ("¿se hizo?"). texto vacío si no hay nada.
     */
    public function visitasAgenda(Request $r)
    {
        $this->verificarToken($r);
        $hoy = now('America/Santiago')->toDateString();
        $man = now('America/Santiago')->addDay()->toDateString();
        $tipos = ['medicion' => 'Medición', 'instalacion' => 'Instalación', 'postventa' => 'Postventa', 'otro' => 'Visita'];

        $visitas = VisitaCliente::with(['cliente', 'cotizacion.cliente'])
            ->where('estado', 'programada')->whereDate('fecha', '<=', $man)
            ->orderBy('fecha')->orderBy('hora')->get();

        $linea = function ($v) use ($tipos) {
            $cl = $this->nombreCliente($v->cliente ?? $v->cotizacion?->cliente);

            return '• ' . $tipos[$v->tipo] . ($cl ? " — {$cl}" : '') . ($v->cotizacion_id ? " (#{$v->cotizacion_id})" : '')
                . ($v->hora ? ' ' . substr((string) $v->hora, 0, 5) : '') . ($v->notas ? " · {$v->notas}" : '');
        };
        $pas = $visitas->filter(fn ($v) => $v->fecha->toDateString() < $hoy)
            ->map(fn ($v) => $linea($v) . ' — era el ' . $v->fecha->format('d-m'))->values();
        $h = $visitas->filter(fn ($v) => $v->fecha->toDateString() === $hoy)->map($linea)->values();
        $m = $visitas->filter(fn ($v) => $v->fecha->toDateString() === $man)->map($linea)->values();

        $t = [];
        if ($pas->isNotEmpty()) {
            $t[] = "❓ *¿Se hicieron?* (siguen como programadas)\n" . $pas->implode("\n");
        }
        if ($h->isNotEmpty()) {
            $t[] = "📍 *Visitas de hoy:*\n" . $h->implode("\n");
        }
        if ($m->isNotEmpty()) {
            $t[] = "🗓️ *Visitas de mañana:*\n" . $m->implode("\n");
        }
        $texto = $t ? implode("\n\n", $t) . ($pas->isNotEmpty() ? "\n\n_Dime «se midió / se instaló donde X» para cerrarlas._" : '') : '';

        return response()->json(['ok' => true, 'cantidad' => $visitas->count(), 'texto' => $texto]);
    }

    /** Cotización aprobada a la que se refiere el dueño (cliente o N°); o una respuesta de error/lista si no es unívoca. */
    private function resolverAprobada(Request $r): Cotizacion|\Illuminate\Http\JsonResponse
    {
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

        return $c;
    }

    private function error(string $msg, int $status = 422)
    {
        return response()->json(['ok' => false, 'error' => $msg], $status);
    }
}
