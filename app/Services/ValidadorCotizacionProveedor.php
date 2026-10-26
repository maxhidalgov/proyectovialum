<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revisa la cotización de un proveedor contra lo que se le pidió (una orden de compra).
 *
 * Qué comprueba:
 *  - Cada ítem pedido aparece cotizado (por código, por equivalencia guardada o, si no, por parecido de
 *    descripción) y con la cantidad correcta. Una cantidad MAYOR que sea múltiplo de caja (10, 100…) se da por
 *    redondeo del proveedor; los perfiles (barras) tienen que coincidir exacto.
 *  - Lo que cotizaron y no se pidió, y las posibles sustituciones (misma cosa con otra medida).
 *  - El descuento de cada línea según el tipo de código (acuerdo con el proveedor).
 *  - La aritmética: cada línea, la suma de líneas contra el neto, el IVA y el total.
 *  - Saltos en la numeración de ítems (una línea que desapareció).
 */
class ValidadorCotizacionProveedor
{
    /**
     * Descuento acordado con Haustek según el tipo de código: perfiles de PVC (PI-) 30 %, perfiles de aluminio
     * (PL-) 30 % salvo los refuerzos de acero (PL-…-TC-…) 35 %, accesorios (HI-) 28 %, herrajes sin descuento (HL-) 0 %.
     */
    private const DESCUENTOS = ['PI' => 30.0, 'PL' => 30.0, 'HI' => 28.0, 'HL' => 0.0];
    private const DESCUENTO_REFUERZO = 35.0;
    private const CAJAS = [10, 20, 25, 50, 100, 200, 250, 500, 1000];
    private const PALABRAS_VACIAS = ['de', 'del', 'la', 'el', 'con', 'para', 'por', 'un', 'una', 'new', 'mm', 'x', 'slk', 'siltek', 'natural', 'marron', 'roble', 'gh', 'ma', 'm', 'g', 'fb', 'plg', 'un'];

    public function validar(array $pedido, array $cot, string $proveedor = 'haustek'): array
    {
        $lineas = array_values($cot['lineas'] ?? []);
        $equiv = $this->equivalencias($proveedor);

        // Índice de líneas del proveedor por código normalizado
        $porCodigo = [];
        foreach ($lineas as $i => $l) {
            $porCodigo[$this->norm($l['codigo'])][] = $i;
        }

        // 1) Asignar cada ítem pedido a una línea cotizada
        $asignado = [];   // idxLinea => [ítems pedido]
        $sinMatch = [];
        $viaDescripcion = [];
        foreach ($pedido as $it) {
            $ref = $this->norm((string) ($it['referencia'] ?? ''));
            $candidatos = array_filter([$ref, $ref ? $ref . '-SLK' : null, isset($equiv[$ref]) ? $this->norm($equiv[$ref]) : null]);
            $idx = null;
            foreach ($candidatos as $c) {
                if (isset($porCodigo[$c])) {
                    $idx = $porCodigo[$c][0];
                    break;
                }
            }
            if ($idx === null) {
                $sinMatch[] = $it;
            } else {
                $asignado[$idx][] = $it;
            }
        }

        // 2) Lo que no calzó por código: parecido de descripción contra las líneas sin asignar
        $libres = array_values(array_diff(array_keys($lineas), array_keys($asignado)));
        $sustituciones = [];
        $faltan = [];
        foreach ($sinMatch as $it) {
            $mejor = null;
            $mejorScore = 0.0;
            foreach ($libres as $li) {
                $s = $this->parecido($this->descripcion($it), (string) $lineas[$li]['descripcion']);
                if ($s['palabras'] > $mejorScore) {
                    $mejorScore = $s['palabras'];
                    $mejor = ['idx' => $li, 'mismas_medidas' => $s['medidas']];
                }
            }
            if ($mejor && $mejorScore >= 0.6) {
                if ($mejor['mismas_medidas']) {
                    $asignado[$mejor['idx']][] = $it;
                    $viaDescripcion[$mejor['idx']] = true;
                    $libres = array_values(array_diff($libres, [$mejor['idx']]));
                } else {
                    $sustituciones[] = ['pedido' => $it, 'linea' => $lineas[$mejor['idx']]];
                    $libres = array_values(array_diff($libres, [$mejor['idx']]));
                }
            } else {
                $faltan[] = $it;
            }
        }
        $sobran = array_values(array_filter($libres, fn ($li) => !in_array($li, array_map(fn ($s) => array_search($s['linea'], $lineas, true), $sustituciones), true)));

        // 3) Hallazgos
        $h = [];
        $ok = 0;
        foreach ($asignado as $li => $items) {
            $l = $lineas[$li];
            $pedida = array_sum(array_map(fn ($i) => (float) ($i['cantidad'] ?? 0), $items));
            $q = (float) $l['cantidad'];
            $nombre = $l['codigo'] . ' ' . $l['descripcion'];
            $perfil = in_array(strtoupper(substr($l['codigo'], 0, 2)), ['PI', 'PL'], true);
            $problema = false;

            if (abs($q - $pedida) < 0.001) {
                // exacto
            } elseif ($q > $pedida) {
                $caja = in_array((int) $q, self::CAJAS, true) || ((int) $q % 10 === 0 && $q <= $pedida * 3);
                if ($perfil || !$caja) {
                    $h[] = $this->hallazgo('aviso', 'cantidad_mayor', "Cantidad mayor: pediste {$this->n($pedida)} y cotizó {$this->n($q)} — {$nombre}", $l);
                    $problema = true;
                } else {
                    $h[] = $this->hallazgo('info', 'caja', "Redondeo a caja: pediste {$this->n($pedida)} y cotizó {$this->n($q)} — {$nombre}", $l);
                }
            } else {
                $h[] = $this->hallazgo('error', 'cantidad_menor', "Cantidad menor: pediste {$this->n($pedida)} y cotizó {$this->n($q)} — {$nombre}", $l);
                $problema = true;
            }
            if (!empty($viaDescripcion[$li])) {
                $h[] = $this->hallazgo('info', 'por_descripcion', "Calzado por descripción (confírmalo y se guarda): {$items[0]['referencia']} → {$l['codigo']}", $l);
            }
            if (!$problema) {
                $ok++;
            }
        }
        foreach ($sustituciones as $s) {
            $h[] = $this->hallazgo('aviso', 'sustitucion', "Posible sustitución: pediste «{$this->descripcion($s['pedido'])}» ({$s['pedido']['referencia']} ×{$this->n($s['pedido']['cantidad'])}) y cotizó «{$s['linea']['descripcion']}» ({$s['linea']['codigo']} ×{$this->n($s['linea']['cantidad'])}): la medida es distinta", $s['linea']);
        }
        foreach ($faltan as $it) {
            $h[] = $this->hallazgo('error', 'falta', "No cotizó: {$this->descripcion($it)}" . (!empty($it['referencia']) ? " ({$it['referencia']})" : '') . " ×{$this->n($it['cantidad'])}");
        }
        foreach ($sobran as $li) {
            $l = $lineas[$li];
            $h[] = $this->hallazgo('aviso', 'sobra', "Cotizó algo que no pediste: {$l['codigo']} {$l['descripcion']} ×{$this->n($l['cantidad'])}", $l);
        }

        // 4) Descuentos y aritmética de cada línea
        foreach ($lineas as $l) {
            $esperado = $this->descuentoEsperado($l['codigo']);
            if ($esperado !== null && abs($l['pct_desc'] - $esperado) > 0.01) {
                $h[] = $this->hallazgo('aviso', 'descuento', "Descuento {$this->n($l['pct_desc'])}% (lo acordado es {$this->n($esperado)}%) — {$l['codigo']} {$l['descripcion']}", $l);
            }
            if (!$l['cuadra'] || !$l['desc_coherente']) {
                $h[] = $this->hallazgo('aviso', 'aritmetica', "La línea no cuadra (cantidad × precio − descuento ≠ total) o se leyó mal — ítem {$l['item']} {$l['codigo']}", $l);
            }
        }

        // 5) Totales y numeración
        $suma = array_sum(array_column($lineas, 'total'));
        $t = $cot['totales'] ?? [];
        if (!empty($t['neto']) && abs($suma - $t['neto']) > 2) {
            $h[] = $this->hallazgo('error', 'suma', "La suma de las líneas ({$this->clp($suma)}) no es el neto declarado ({$this->clp($t['neto'])})");
        }
        if (!empty($t['neto']) && !empty($t['iva']) && abs(round($t['neto'] * 0.19) - $t['iva']) > 1) {
            $h[] = $this->hallazgo('error', 'iva', "El IVA ({$this->clp($t['iva'])}) no es el 19 % del neto ({$this->clp(round($t['neto'] * 0.19))})");
        }
        if (!empty($t['neto']) && !empty($t['iva']) && !empty($t['total']) && abs($t['neto'] + $t['iva'] - $t['total']) > 1) {
            $h[] = $this->hallazgo('error', 'total', "El total ({$this->clp($t['total'])}) no es neto + IVA ({$this->clp($t['neto'] + $t['iva'])})");
        }
        $items = array_column($lineas, 'item');
        sort($items);
        for ($i = 1; $i < count($items); $i++) {
            if ($items[$i] - $items[$i - 1] > 1) {
                $falt = range($items[$i - 1] + 1, $items[$i] - 1);
                $h[] = $this->hallazgo('aviso', 'numeracion', 'La numeración salta del ' . $items[$i - 1] . ' al ' . $items[$i] . ': falta el ítem ' . implode(', ', $falt) . ' (¿se borró una línea?)');
            }
        }

        $orden = ['error' => 0, 'aviso' => 1, 'info' => 2];
        usort($h, fn ($a, $b) => $orden[$a['severidad']] <=> $orden[$b['severidad']]);
        $resumen = [
            'lineas_pedido'     => count($pedido),
            'lineas_cotizadas'  => count($lineas),
            'coinciden'         => $ok,
            'errores'           => count(array_filter($h, fn ($x) => $x['severidad'] === 'error')),
            'avisos'            => count(array_filter($h, fn ($x) => $x['severidad'] === 'aviso')),
            'neto'              => $t['neto'] ?? null,
            'total'             => $t['total'] ?? null,
        ];

        return ['resumen' => $resumen, 'hallazgos' => $h, 'texto' => $this->texto($cot, $resumen, $h)];
    }

    private function descuentoEsperado(string $codigo): ?float
    {
        $c = strtoupper($codigo);
        $pref = substr($c, 0, 2);
        if (!isset(self::DESCUENTOS[$pref])) {
            return null;
        }
        if ($pref === 'PL' && str_contains($c, '-TC-')) {
            return self::DESCUENTO_REFUERZO;
        }

        return self::DESCUENTOS[$pref];
    }

    private function texto(array $cot, array $r, array $h): string
    {
        $enc = $cot['encabezado'] ?? [];
        $L = ['*Cotización ' . ($enc['numero'] ?? '?') . '* · ' . ($enc['fecha'] ?? '') . ' · neto ' . ($r['neto'] ? $this->clp($r['neto']) : '?') . ' · total ' . ($r['total'] ? $this->clp($r['total']) : '?')];
        $L[] = "✅ {$r['coinciden']} de {$r['lineas_pedido']} líneas pedidas coinciden · {$r['lineas_cotizadas']} líneas cotizadas";
        foreach (['error' => '❌', 'aviso' => '⚠️', 'info' => 'ℹ️'] as $sev => $icono) {
            foreach ($h as $x) {
                if ($x['severidad'] === $sev) {
                    $L[] = $icono . ' ' . $x['texto'];
                }
            }
        }
        if ($r['errores'] === 0 && $r['avisos'] === 0) {
            $L[] = '👍 Sin observaciones.';
        }

        return implode("\n", $L);
    }

    private function hallazgo(string $sev, string $tipo, string $texto, ?array $linea = null): array
    {
        return ['severidad' => $sev, 'tipo' => $tipo, 'texto' => $texto, 'item' => $linea['item'] ?? null, 'codigo' => $linea['codigo'] ?? null];
    }

    private function equivalencias(string $proveedor): array
    {
        if (!Schema::hasTable('proveedor_equivalencias')) {
            return [];
        }
        $map = [];
        foreach (DB::table('proveedor_equivalencias')->where('proveedor', $proveedor)->get(['codigo_pedido', 'codigo_proveedor']) as $r) {
            $map[$this->norm($r->codigo_pedido)] = $r->codigo_proveedor;
        }

        return $map;
    }

    /** Código sin prefijo de proveedor de Winperfil ("115/"), sin espacios y sin la variante SILTEK. */
    private function norm(string $codigo): string
    {
        $c = strtoupper(preg_replace('/\s+/', '', trim($codigo)));
        $c = preg_replace('/^\d+\//', '', $c);

        return preg_replace('/-SLK$/', '', $c);
    }

    private function descripcion(array $it): string
    {
        return trim(($it['descripcion'] ?? '') . ' ' . ($it['detalle'] ?? ''));
    }

    private function tokens(string $texto): array
    {
        $t = mb_strtolower($texto);
        $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        $t = preg_replace('/(\d),(\d)/', '$1.$2', $t);
        preg_match_all('/\d+(?:\.\d+)?|[a-z]{2,}/', $t, $m);
        $palabras = [];
        $medidas = [];
        foreach ($m[0] as $tok) {
            if (is_numeric($tok)) {
                $medidas[] = rtrim(rtrim(number_format((float) $tok, 2, '.', ''), '0'), '.');
            } elseif (!in_array($tok, self::PALABRAS_VACIAS, true)) {
                $palabras[] = $tok;
            }
        }

        return [array_values(array_unique($palabras)), array_values(array_unique($medidas))];
    }

    /** Parecido de dos descripciones: proporción de palabras en común y si las medidas son las mismas. */
    private function parecido(string $a, string $b): array
    {
        [$pa, $ma] = $this->tokens($a);
        [$pb, $mb] = $this->tokens($b);
        $union = count(array_unique(array_merge($pa, $pb)));
        $comun = count(array_intersect($pa, $pb));
        sort($ma);
        sort($mb);

        return ['palabras' => $union ? $comun / $union : 0.0, 'medidas' => $ma === $mb];
    }

    private function n($v): string
    {
        $v = (float) $v;

        return rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
    }

    private function clp($v): string
    {
        return '$' . number_format((float) $v, 0, ',', '.');
    }
}
