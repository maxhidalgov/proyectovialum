<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\MovimientoBancario;
use App\Models\ReglaConciliacion;

class BancochilePortalController extends Controller
{
    private const URL_CARTOLA = 'https://portalempresas.bancochile.cl/mibancochile/rest/empresa/movimientos/getcartola';

    // Datos de la cuenta (fijos para esta empresa)
    private const CUENTA = [
        'nombreEmpresa'  => 'HIDALGO E HIDALGO LIMITADA.',
        'rutEmpresa'     => '76096031-4',
        'numero'         => '2300584402',
        'mascara'        => '****4402',
        'alias'          => null,
        'selected'       => true,
        'codigoProducto' => 'CTD',
        'claseCuenta'    => 'CCNMN2',
        'moneda'         => 'CLP',
    ];

    public function importar(Request $request)
    {
        $request->validate([
            'cookies' => 'required|string|min:50',
            'desde'   => 'required|date_format:Y-m-d',
            'hasta'   => 'required|date_format:Y-m-d',
        ]);

        $cookieString = $request->input('cookies');
        $xsrf = $this->cookieValor($cookieString, 'XSRF-TOKEN');

        // Las fechas deben ir en UTC (el banco las maneja así)
        $fechaInicio = Carbon::parse($request->desde, 'America/Santiago')
            ->startOfDay()->utc()->toIso8601String();
        $fechaFin    = Carbon::parse($request->hasta, 'America/Santiago')
            ->endOfDay()->utc()->toIso8601String();

        $payload = [
            'cabecera'            => [
                'paginacionDesde' => (object) [],
                'fechaInicio'     => $fechaInicio,
                'fechaFin'        => $fechaFin,
            ],
            'cuentasSeleccionadas' => [self::CUENTA],
        ];

        $headers = [
            'Accept'             => 'application/json, text/plain, */*',
            'Accept-Language'    => 'es-ES,es;q=0.9,en;q=0.8',
            'Content-Type'       => 'application/json',
            'Cookie'             => $cookieString,
            'Origin'             => 'https://portalempresas.bancochile.cl',
            'Referer'            => 'https://portalempresas.bancochile.cl/mibancochile-web/front/empresa/index.html',
            'User-Agent'         => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'sec-ch-ua'          => '"Chromium";v="124", "Google Chrome";v="124", "Not-A.Brand";v="99"',
            'sec-ch-ua-mobile'   => '?0',
            'sec-ch-ua-platform' => '"Windows"',
            'sec-fetch-dest'     => 'empty',
            'sec-fetch-mode'     => 'cors',
            'sec-fetch-site'     => 'same-origin',
            'Connection'         => 'keep-alive',
        ];

        if ($xsrf) {
            $headers['X-XSRF-TOKEN'] = urldecode($xsrf);
        }

        try {
            $res = Http::timeout(30)
                ->withHeaders($headers)
                ->withoutRedirecting()
                ->post(self::URL_CARTOLA, $payload);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'No se pudo conectar al banco: ' . $e->getMessage()], 502);
        }

        // 3xx = banco redirige → sesión expirada o WAF bloqueó la request
        if ($res->redirect()) {
            $location = $res->header('Location') ?? '(sin Location)';
            return response()->json([
                'error' => 'El banco redirigió la solicitud (status ' . $res->status() . '). '
                    . 'Las cookies probablemente expiraron o el WAF del banco bloqueó el acceso desde el servidor. '
                    . 'Redirigido a: ' . $location,
            ], 422);
        }

        if ($res->status() === 401 || $res->status() === 403) {
            return response()->json(['error' => 'Sesión expirada o cookies inválidas. Vuelve a loguearte en el banco y copia las cookies nuevamente.'], 422);
        }

        if (!$res->ok()) {
            return response()->json(['error' => "El banco respondió con error {$res->status()}. Body: " . mb_substr($res->body(), 0, 300)], 502);
        }

        $data        = $res->json();
        $movimientos = $data['movimientos'] ?? [];

        if (empty($movimientos)) {
            return response()->json([
                'total'      => 0,
                'nuevos'     => 0,
                'duplicados' => 0,
                'errores'    => [],
                'message'    => 'El banco no devolvió movimientos para ese período (o las cookies no autenticaron correctamente)',
            ]);
        }

        return $this->procesarMovimientos($movimientos);
    }

    /**
     * Importa movimientos a partir del JSON crudo pegado por el usuario
     * (obtenido ejecutando el script de consola en el portal del banco).
     */
    public function importarJson(Request $request)
    {
        $request->validate([
            'json_data' => 'required|string|min:10',
        ]);

        $data = json_decode($request->input('json_data'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json(['error' => 'El JSON pegado no es válido: ' . json_last_error_msg()], 422);
        }

        // Acepta tanto el objeto completo de la API {"movimientos":[...]}
        // como un array directo de movimientos [...]
        $movimientos = $data['movimientos'] ?? (is_array($data) && isset($data[0]) ? $data : null);

        if ($movimientos === null) {
            return response()->json(['error' => 'No se encontró la clave "movimientos" en el JSON. Asegúrate de copiar la respuesta completa.'], 422);
        }

        if (empty($movimientos)) {
            return response()->json([
                'total'      => 0,
                'nuevos'     => 0,
                'duplicados' => 0,
                'errores'    => [],
                'message'    => 'El JSON no contiene movimientos para ese período.',
            ]);
        }

        return $this->procesarMovimientos($movimientos);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Persiste el array de movimientos del banco en movimientos_bancarios.
     * Reutilizado por importar() e importarJson().
     *
     * El JSON del banco llega con el movimiento MÁS RECIENTE primero (índice 0).
     * Invertimos el array para insertar del más antiguo al más reciente, de modo
     * que el movimiento más nuevo siempre quede con el id de DB más alto.
     * Esto garantiza que ORDER BY id DESC devuelva el movimiento correcto cuando
     * fecha_hora_mov no está disponible en registros importados antes de esta mejora.
     */
    private function procesarMovimientos(array $movimientos): \Illuminate\Http\JsonResponse
    {
        $nuevos     = 0;
        $duplicados = 0;
        $errores    = [];

        // Invertir: procesar del más antiguo al más reciente →
        // el más reciente obtiene el id de DB más alto
        $movimientos = array_reverse($movimientos);

        foreach ($movimientos as $m) {
            try {
                $tipo        = (($m['tipo'] ?? 'cargo') === 'abono') ? 'C' : 'D';
                $monto       = abs((float) ($m['monto'] ?? 0));
                $descripcion = mb_substr($m['descripcion'] ?? '', 0, 255);
                $fecha       = $this->parseFecha($m['fecha'] ?? '');
                $fechaHora   = $this->parseFechaHora($m['fecha'] ?? '');
                $saldo       = isset($m['saldoMovimiento']) ? (float) $m['saldoMovimiento'] : null;
                $nroDoc      = isset($m['numeroDocumento']) && $m['numeroDocumento'] !== '' ? (string) $m['numeroDocumento'] : null;
                $glosaRaw    = $this->construirGlosa($m['detalleGlosa'] ?? [], $tipo);
                $glosa       = $glosaRaw ? mb_substr($glosaRaw, 0, 2000) : null;
                $bchCodigo   = 'PORTAL-' . md5($m['id'] ?? ($descripcion . ($m['fecha'] ?? '') . $monto));

                if (!$fecha) {
                    $errores[] = "Fecha inválida en movimiento: " . ($m['fecha'] ?? 'null');
                    continue;
                }

                $existente = MovimientoBancario::where('bch_codigo', $bchCodigo)->first();

                if ($existente) {
                    // Si ya existe pero le falta el número de documento o la hora, los completamos
                    $updates = [];
                    if ($nroDoc && !$existente->numero_documento) {
                        $updates['numero_documento'] = $nroDoc;
                    }
                    if ($fechaHora && !$existente->fecha_hora_mov) {
                        $updates['fecha_hora_mov'] = $fechaHora;
                    }
                    if ($updates) {
                        $existente->update($updates);
                    }
                    $duplicados++;
                    continue;
                }

                $categoria = ReglaConciliacion::categorizar($descripcion, $tipo);

                MovimientoBancario::create([
                    'cuenta'            => self::CUENTA['numero'],
                    'fecha_contable'    => $fecha,
                    'fecha_hora_mov'    => $fechaHora,
                    'descripcion'       => $descripcion,
                    'glosa'             => $glosa,
                    'monto'             => $monto,
                    'tipo'              => $tipo,
                    'saldo_disponible'  => $saldo,
                    'numero_documento'  => $nroDoc,
                    'bch_codigo'        => $bchCodigo,
                    'categoria'         => $categoria,
                    'conciliado'        => false,
                ]);

                $nuevos++;
            } catch (\Throwable $e) {
                $errores[] = $e->getMessage();
            }
        }

        return response()->json([
            'total'      => count($movimientos),
            'nuevos'     => $nuevos,
            'duplicados' => $duplicados,
            'errores'    => $errores,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Construye la glosa completa a partir del detalleGlosa del banco.
     * Guarda TODOS los campos para preservar información (el frontend filtra
     * qué mostrar en línea vs. en tooltip).
     * - Omite líneas con valor vacío ("Campo: ").
     * - Normaliza los RUTs al formato estándar (12345678-9).
     */
    private function construirGlosa(array $detalleGlosa, string $tipo): ?string
    {
        if (empty($detalleGlosa)) return null;

        $lineas = [];
        foreach ($detalleGlosa as $linea) {
            $linea = trim($linea);
            if ($linea === '') continue;

            $colonPos = strpos($linea, ':');
            if ($colonPos !== false) {
                $clave = mb_strtolower(trim(substr($linea, 0, $colonPos)));
                $valor = trim(substr($linea, $colonPos + 1));

                // Omitir si el valor está completamente vacío
                if ($valor === '') continue;

                // Omitir banco — no aporta información útil
                if ($clave === 'banco') continue;

                // Normalizar RUTs: "Rut Origen: 0704380011" → "Rut Origen: 70438001-1"
                if (preg_match('/^rut\s+\w+$/i', $clave)) {
                    $valor = $this->normalizarRutBanco($valor);
                }

                $lineas[] = trim(substr($linea, 0, $colonPos)) . ': ' . $valor;
            } else {
                $lineas[] = $linea;
            }
        }

        return $lineas ? implode(' · ', $lineas) : null;
    }

    /**
     * Convierte el formato de RUT del banco (sin puntos, sin guion, con cero inicial)
     * al formato estándar: "0704380011" → "70438001-1"
     */
    private function normalizarRutBanco(string $raw): string
    {
        $clean  = ltrim(preg_replace('/\s+/', '', $raw), '0');
        if (strlen($clean) < 2) return $raw;
        $check  = substr($clean, -1);
        $digits = substr($clean, 0, -1);
        return $digits . '-' . strtoupper($check);
    }

    /**
     * Extrae el valor de una cookie del header Cookie completo.
     */
    private function cookieValor(string $cookieHeader, string $nombre): ?string
    {
        if (preg_match('/(?:^|;\s*)' . preg_quote($nombre, '/') . '=([^;]+)/', $cookieHeader, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Parsea fecha del banco: "20260508 16:27:31" → "2026-05-08"
     */
    private function parseFecha(string $fecha): ?string
    {
        if (preg_match('/^(\d{4})(\d{2})(\d{2})/', $fecha, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }
        return null;
    }

    /**
     * Parsea fecha+hora del banco: "20260508 16:27:31" → "2026-05-08 16:27:31"
     * Permite ordenar movimientos del mismo día por hora exacta.
     */
    private function parseFechaHora(string $fecha): ?string
    {
        // Formato: "20260508 16:27:31"
        if (preg_match('/^(\d{4})(\d{2})(\d{2})\s+(\d{2}:\d{2}:\d{2})/', $fecha, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]} {$m[4]}";
        }
        return null;
    }
}
