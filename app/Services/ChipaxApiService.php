<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Cliente para la API oficial de Chipax (api.chipax.com/v2).
 *
 * Autenticación: POST /v2/login con app_id + secret_key → JWT
 * Cabecera:      Authorization: JWT <token>
 *
 * Configura en .env:
 *   CHIPAX_APP_ID=tu_app_id
 *   CHIPAX_SECRET_KEY=tu_secret_key
 *   CHIPAX_API_BASE=https://api.chipax.com/v2   (por defecto)
 */
class ChipaxApiService
{
    private string $apiBase;

    public function __construct()
    {
        $this->apiBase = rtrim(config('services.chipax.api_base', 'https://api.chipax.com/v2'), '/');
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    /**
     * Obtiene el JWT de la API oficial.
     * Lo cachea 23 h (el token dura ~24 h).
     */
    public function getToken(): string
    {
        return Cache::remember('chipax_api_token', 82_800, function () {
            return $this->fetchToken();
        });
    }

    private function fetchToken(): string
    {
        $appId     = config('services.chipax.app_id');
        $secretKey = config('services.chipax.secret_key');

        if (!$appId || !$secretKey) {
            throw new \RuntimeException(
                'Faltan CHIPAX_APP_ID y/o CHIPAX_SECRET_KEY en .env'
            );
        }

        $resp = Http::timeout(15)->post("{$this->apiBase}/login", [
            'app_id'     => $appId,
            'secret_key' => $secretKey,
        ]);

        if (!$resp->ok()) {
            throw new \RuntimeException(
                "Chipax API login falló ({$resp->status()}): " . $resp->body()
            );
        }

        $json = $resp->json();

        // Distintas versiones del API usan claves distintas
        $token = $json['token'] ?? $json['jwt'] ?? $json['access_token'] ?? null;

        if (!$token) {
            throw new \RuntimeException(
                'No se encontró token en la respuesta del login Chipax: ' . json_encode($json)
            );
        }

        return $token;
    }

    // ── HTTP helpers ──────────────────────────────────────────────────────────

    /**
     * GET autenticado. En caso de 401 refresca el token y reintenta.
     */
    public function get(string $path, array $params = []): array
    {
        $resp = $this->request($path, $params);

        if ($resp->status() === 401) {
            Cache::forget('chipax_api_token');
            $resp = $this->request($path, $params);
        }

        if (!$resp->ok()) {
            throw new \RuntimeException(
                "Chipax API error {$resp->status()} en {$path}"
            );
        }

        return $resp->json() ?? [];
    }

    private function request(string $path, array $params): \Illuminate\Http\Client\Response
    {
        $url = $this->apiBase . '/' . ltrim($path, '/');

        return Http::timeout(30)
            ->withHeaders(['Authorization' => 'JWT ' . $this->getToken()])
            ->get($url, $params);
    }

    // ── Endpoints específicos ─────────────────────────────────────────────────

    /**
     * Prueba la conexión y devuelve info básica de la empresa.
     */
    public function testConexion(): array
    {
        // Endpoint de prueba: lista de cuentas corrientes
        return $this->get('/cuentas-corrientes', ['perPage' => 1]);
    }

    /**
     * Fetches a page of movements with linked documents.
     * Endpoint: GET /flujo-caja/cartolas
     *
     * @param string $desde Y-m-d
     * @param string $hasta Y-m-d
     * @param int    $page
     * @param int    $perPage
     * @param int|null $cuentaId  ID interno de Chipax de la cuenta corriente
     */
    /**
     * Cartolas con docs vinculados.
     *
     * Notas sobre la API:
     *  - Parámetros de fecha correctos: startDate / endDate (no fechaDesde/fechaHasta)
     *  - El parámetro perPage es IGNORADO: el endpoint devuelve ~500 items por página
     *  - pages y total sí funcionan correctamente
     */
    public function getCartolasConDocs(
        string $desde,
        string $hasta,
        int $page = 1,
        ?int $cuentaId = null
    ): array {
        $params = [
            'page'      => $page,
            'startDate' => $desde,
            'endDate'   => $hasta,
        ];

        if ($cuentaId) {
            $params['idCuentaCorriente'] = $cuentaId;
        }

        return $this->get('/flujo-caja/cartolas', $params);
    }
}
