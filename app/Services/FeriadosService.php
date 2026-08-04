<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Feriados de Chile. Fuente principal: date.nager.at (incluye móviles como
 * Viernes Santo). Cacheado 30 días por año. Fallback offline con los feriados
 * fijos si la API no responde.
 */
class FeriadosService
{
    /** Devuelve ['Y-m-d' => nombre] de feriados que caen dentro de [$desde, $hasta]. */
    public function set(string $desde, string $hasta): array
    {
        $y1 = (int) substr($desde, 0, 4);
        $y2 = (int) substr($hasta, 0, 4);
        $out = [];
        for ($y = $y1; $y <= max($y1, $y2); $y++) {
            foreach ($this->porAnio($y) as $fecha => $nombre) {
                if ($fecha >= $desde && $fecha <= $hasta) {
                    $out[$fecha] = $nombre;
                }
            }
        }
        return $out;
    }

    /** ['Y-m-d' => nombre] de todos los feriados de un año. */
    public function porAnio(int $anio): array
    {
        return Cache::remember("feriados_cl_{$anio}", now()->addDays(30), function () use ($anio) {
            try {
                $resp = Http::timeout(8)->get("https://date.nager.at/api/v3/PublicHolidays/{$anio}/CL");
                if ($resp->successful()) {
                    $map = [];
                    foreach ($resp->json() as $h) {
                        if (!empty($h['date'])) {
                            $map[$h['date']] = $h['localName'] ?? $h['name'] ?? 'Feriado';
                        }
                    }
                    if ($map) {
                        return $map;
                    }
                }
            } catch (\Throwable $e) {
                // cae al fallback
            }
            return $this->fallback($anio);
        });
    }

    /** Fallback offline: feriados fijos (no incluye los móviles de Semana Santa). */
    private function fallback(int $anio): array
    {
        $fijos = [
            '01-01' => 'Año Nuevo',
            '05-01' => 'Día del Trabajo',
            '05-21' => 'Glorias Navales',
            '06-29' => 'San Pedro y San Pablo',
            '07-16' => 'Virgen del Carmen',
            '08-15' => 'Asunción de la Virgen',
            '09-18' => 'Independencia',
            '09-19' => 'Glorias del Ejército',
            '10-12' => 'Encuentro de Dos Mundos',
            '10-31' => 'Iglesias Evangélicas',
            '11-01' => 'Todos los Santos',
            '12-08' => 'Inmaculada Concepción',
            '12-25' => 'Navidad',
        ];
        $map = [];
        foreach ($fijos as $md => $nom) {
            $map["{$anio}-{$md}"] = $nom;
        }
        return $map;
    }
}
