<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BancochileService
{
    private string $apiBase;
    private string $clientId;
    private string $clientSecret;
    private string $cuenta;
    private string $rutOrigen;
    private string $productoCuenta;
    private array  $rutApoderado;

    public function __construct()
    {
        $this->apiBase        = config('services.bch.api_base');
        $this->clientId       = config('services.bch.client_id');
        $this->clientSecret   = config('services.bch.client_secret');
        $this->cuenta         = config('services.bch.cuenta');
        $this->rutOrigen      = config('services.bch.rut_origen');
        $this->productoCuenta = config('services.bch.producto_cuenta', 'CTD');
        $rutAp = config('services.bch.rut_apoderado') ?: config('services.bch.rut_origen');
        $this->rutApoderado   = [['value' => $rutAp]];
    }

    // ── Headers (sandbox: client-id + client-secret directo) ─────────────────

    private function headers(): array
    {
        return [
            'client-id'     => $this->clientId,
            'client-secret' => $this->clientSecret,
            'Authorization' => 'Bearer ' . $this->clientId, // sandbox: usa client-id como bearer
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
    }

    // ── Movimientos por período ───────────────────────────────────────────────

    public function getMovimientosPorPeriodo(string $desde, string $hasta): array
    {
        $todos    = [];
        $pagDesde = 0;
        $meta     = [];

        $maxPaginas = 20;
        $pagina     = 0;

        do {
            $body = [
                'rutOrigen'       => $this->rutOrigen,
                'productoCuenta'  => $this->productoCuenta,
                'cuenta'          => $this->cuenta,
                'rutApoderado'    => $this->rutApoderado,
                'fechaDesde'      => $desde,
                'fechaHasta'      => $hasta,
                'paginacionDesde' => $pagDesde,
            ];

            $resp = Http::timeout(25)
                ->withHeaders($this->headers())
                ->post("{$this->apiBase}/obtener-periodo", $body);

            if (!$resp->successful()) {
                Log::error('BCH /obtener-periodo error', ['status' => $resp->status(), 'body' => $resp->body()]);
                throw new \RuntimeException('Banco Chile error ' . $resp->status() . ': ' . $resp->body());
            }

            $data     = $resp->json();
            $items    = $data['movimientos'] ?? [];
            $todos    = array_merge($todos, $items);
            $hayMas   = ($data['indicadorMasPaginas'] ?? 'N') === 'Y';
            $pagDesde = ($data['indiceTerminoRespuesta'] ?? $pagDesde) + 1;
            $pagina++;

            if (empty($meta)) {
                $meta = $data;
            }

        } while ($hayMas && count($items) > 0 && $pagina < $maxPaginas);

        unset($meta['movimientos']);

        return ['movimientos' => $todos, 'meta' => $meta];
    }

    // ── Últimos N movimientos ─────────────────────────────────────────────────

    public function getMovimientosPorCantidad(int $cantidad = 100): array
    {
        $body = [
            'rutOrigen'         => $this->rutOrigen,
            'productoCuenta'    => $this->productoCuenta,
            'cuenta'            => $this->cuenta,
            'rutApoderado'      => $this->rutApoderado,
            'cantidadRegistros' => (string) $cantidad,
            'paginacionDesde'   => 0,
        ];

        $resp = Http::timeout(30)
            ->withHeaders($this->headers())
            ->post("{$this->apiBase}/obtener", $body);

        if (!$resp->successful()) {
            Log::error('BCH /obtener error', ['status' => $resp->status(), 'body' => $resp->body()]);
            throw new \RuntimeException('Banco Chile error ' . $resp->status() . ': ' . $resp->body());
        }

        $data = $resp->json();
        $meta = $data;
        unset($meta['movimientos']);

        return ['movimientos' => $data['movimientos'] ?? [], 'meta' => $meta];
    }

    // ── Resumen último mes ────────────────────────────────────────────────────

    public function getResumen(): array
    {
        $body = [
            'rutOrigen'       => $this->rutOrigen,
            'productoCuenta'  => $this->productoCuenta,
            'cuenta'          => $this->cuenta,
            'rutApoderado'    => $this->rutApoderado,
            'paginacionDesde' => 0,
        ];

        $resp = Http::timeout(30)
            ->withHeaders($this->headers())
            ->post("{$this->apiBase}/obtener-resumen", $body);

        if (!$resp->successful()) {
            throw new \RuntimeException('Banco Chile error ' . $resp->status() . ': ' . $resp->body());
        }

        return $resp->json();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function getCuenta(): string
    {
        return $this->cuenta;
    }

    public static function parseDate(?string $raw): ?string
    {
        if (!$raw || strlen($raw) !== 8) return null;
        return substr($raw, 0, 4) . '-' . substr($raw, 4, 2) . '-' . substr($raw, 6, 2);
    }
}
