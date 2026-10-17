<?php

namespace App\Services;

/**
 * Acomodo de piezas (cortes) en barras de largo fijo.
 *
 * Winperfil entrega cuántas barras hay que comprar de cada perfil (pedbarra) pero no el
 * despiece. Con las piezas de lismonta este servicio busca una distribución que use
 * EXACTAMENTE esa cantidad de barras: un First-Fit simple no alcanza (Winperfil llena las
 * barras casi al 100 %), así que se hace una búsqueda exacta por barra ("bin completion")
 * con memoria y límite de tiempo. Si no logra el objetivo devuelve la mejor distribución
 * heurística y lo avisa, para que el dibujo se marque como referencial.
 */
class OptimizadorBarrasService
{
    private int $cap = 0;            // largo de barra + kerf (la última pieza no necesita kerf)
    private int $kerf = 5;
    /** @var int[] peso de cada tipo de pieza (largo + kerf) */
    private array $peso = [];
    /** @var int[] largo de cada tipo de pieza */
    private array $largoTipo = [];
    /** @var array<string,bool> estados ya probados sin solución */
    private array $falla = [];
    private float $limite = 0.0;
    private bool $agotado = false;     // se acabó el tiempo total
    private bool $corte = false;       // se alcanzó el tope de nodos del intento actual
    private int $nodos = 0;
    private int $maxNodos = 0;
    private int $ruido = 0;            // mm de aleatoriedad al ordenar patrones (0 = sin ruido)

    /**
     * @param  float[] $largos      largos de pieza en mm
     * @param  int     $largoBarra  mm
     * @param  int     $kerf        mm perdidos por corte
     * @param  int|null $objetivo   barras que usó Winperfil (null = buscar el mínimo)
     * @return array{barras: array<int,int[]>, alcanzo_objetivo: bool, exacto: bool, exceden: int}
     *         barras = lista de barras, cada una con los largos (mm) de sus piezas
     */
    public function empaquetar(array $largos, int $largoBarra, int $kerf = 5, ?int $objetivo = null, float $segundos = 3.0): array
    {
        $this->kerf   = $kerf;
        $this->cap    = $largoBarra + $kerf;
        $this->falla  = [];
        $this->agotado = false;
        $this->limite = microtime(true) + $segundos;

        // Piezas más largas que la barra: no caben nunca; van solas.
        $normales = [];
        $exceden  = [];
        foreach ($largos as $l) {
            $l = (int) round($l);
            if ($l <= 0) {
                continue;
            }
            if ($l + $kerf > $this->cap) {
                $exceden[] = [$l];
            } else {
                $normales[] = $l;
            }
        }
        if (empty($normales)) {
            return ['barras' => $exceden, 'alcanzo_objetivo' => $objetivo === null || count($exceden) === $objetivo,
                    'exacto' => true, 'exceden' => count($exceden)];
        }

        // Tipos de pieza (largo → cantidad), de mayor a menor
        $cuenta = array_count_values($normales);
        krsort($cuenta);
        $this->largoTipo = array_keys($cuenta);
        $this->peso      = array_map(fn ($l) => $l + $kerf, $this->largoTipo);
        $cnt             = array_values($cuenta);

        $heuristica = $this->ffd($cnt);                      // cota superior
        $pesoTotal  = 0;
        foreach ($cnt as $i => $c) {
            $pesoTotal += $c * $this->peso[$i];
        }
        $cotaInferior = (int) ceil($pesoTotal / $this->cap);

        $mejor = $heuristica;
        $meta  = $objetivo;

        if ($meta !== null && count($heuristica) <= $meta) {
            // Ya cabe en lo que usó Winperfil (o menos).
        } elseif ($meta !== null) {
            $sol = $meta >= $cotaInferior ? $this->resolverConReinicios($cnt, $meta) : null;
            if ($sol !== null) {
                $mejor = $sol;
            }
        } else {
            // Sin objetivo: bajar desde la heurística hasta el mínimo posible.
            for ($n = max($cotaInferior, 1); $n < count($heuristica); $n++) {
                $this->falla = [];
                $sol = $this->resolverConReinicios($cnt, $n);
                if ($sol !== null) {
                    $mejor = $sol;
                    break;
                }
                if ($this->agotado) {
                    break;
                }
            }
        }

        $barras = array_merge($this->aLargos($mejor), $exceden);

        return [
            'barras'           => $barras,
            'alcanzo_objetivo' => $objetivo === null ? true : count($barras) === $objetivo,
            'exacto'           => !$this->agotado,
            'exceden'          => count($exceden),
        ];
    }

    /** First-Fit Decreasing sobre tipos → lista de patrones (vector de cantidades por tipo). */
    private function ffd(array $cnt): array
    {
        $barras = [];   // cada una: ['uso' => peso usado, 'pat' => vector]
        $n = count($cnt);
        for ($i = 0; $i < $n; $i++) {
            for ($k = 0; $k < $cnt[$i]; $k++) {
                $colocada = false;
                foreach ($barras as &$b) {
                    if ($b['uso'] + $this->peso[$i] <= $this->cap) {
                        $b['uso'] += $this->peso[$i];
                        $b['pat'][$i]++;
                        $colocada = true;
                        break;
                    }
                }
                unset($b);
                if (!$colocada) {
                    $pat = array_fill(0, $n, 0);
                    $pat[$i] = 1;
                    $barras[] = ['uso' => $this->peso[$i], 'pat' => $pat];
                }
            }
        }
        return array_map(fn ($b) => $b['pat'], $barras);
    }

    /**
     * Búsqueda con reinicios: la búsqueda en profundidad pura se queda atrapada en una mala
     * decisión temprana. Se hacen intentos cortos con orden de patrones cada vez más aleatorio
     * (y más nodos permitidos) hasta encontrar solución, probar que no existe o agotar el tiempo.
     */
    private function resolverConReinicios(array $cnt, int $barras): ?array
    {
        for ($intento = 0; microtime(true) < $this->limite; $intento++) {
            mt_srand(1000 + $intento);
            $this->ruido    = $intento === 0 ? 0 : min(400, 25 * $intento);
            $this->maxNodos = 1500 + 500 * $intento;
            $this->nodos    = 0;
            $this->corte    = false;

            $sol = $this->resolver($cnt, $barras);
            if ($sol !== null) {
                return $sol;
            }
            if (!$this->corte) {
                return null;            // búsqueda completa sin éxito: no hay solución
            }
        }
        $this->agotado = true;
        return null;
    }

    /**
     * ¿Se pueden acomodar todas las piezas en $barras barras? Devuelve los patrones o null.
     * La barra que se arma siempre incluye la pieza más larga que queda (sin pérdida de
     * generalidad) y solo se prueban patrones "maximales" (a los que no les cabe nada más).
     */
    private function resolver(array $cnt, int $barras): ?array
    {
        $total = 0;
        foreach ($cnt as $i => $c) {
            $total += $c * $this->peso[$i];
        }
        if ($total === 0) {
            return [];
        }
        if ($barras <= 0 || $total > $barras * $this->cap) {
            return null;
        }
        $clave = implode(',', $cnt) . '|' . $barras;
        if (isset($this->falla[$clave])) {
            return null;
        }
        if (microtime(true) > $this->limite) {
            $this->agotado = true;
            return null;
        }
        if (++$this->nodos > $this->maxNodos) {
            $this->corte = true;
            return null;
        }

        $i0 = 0;
        while ($cnt[$i0] === 0) {
            $i0++;
        }

        // Holgura total: lo que sobra si todas las barras se llenaran perfecto. Un patrón que
        // desperdicia más que eso no puede formar parte de ninguna solución.
        $holgura  = $barras * $this->cap - $total;
        $patrones = $this->patronesMaximales($cnt, $i0, $holgura);
        foreach ($patrones as $fila) {
            $pat = $fila[1];
            $resto = $cnt;
            foreach ($pat as $i => $k) {
                $resto[$i] -= $k;
            }
            $r = $this->resolver($resto, $barras - 1);
            if ($r !== null) {
                array_unshift($r, $pat);
                return $r;
            }
            if ($this->agotado || $this->corte) {
                return null;
            }
        }

        if (count($this->falla) < 200000) {
            $this->falla[$clave] = true;
        }
        return null;
    }

    /** Patrones maximales que contienen al menos una pieza del tipo $i0, de más a menos llenos. */
    private function patronesMaximales(array $cnt, int $i0, int $holgura): array
    {
        $n   = count($cnt);
        $out = [];
        $pat = array_fill(0, $n, 0);
        $tope = 4000;

        // suma de pesos disponibles desde el tipo i en adelante (cota de cuánto se puede llenar)
        $suf = array_fill(0, $n + 1, 0);
        for ($j = $n - 1; $j >= 0; $j--) {
            $suf[$j] = $suf[$j + 1] + $cnt[$j] * $this->peso[$j];
        }

        $gen = function (int $i, int $resto, int $uso) use (&$gen, &$out, &$pat, $cnt, $n, $i0, $tope, $holgura, $suf) {
            if (count($out) >= $tope) {
                return;
            }
            // aunque se agregara todo lo que queda, el desperdicio superaría la holgura total
            if ($resto - min($resto, $suf[$i]) > $holgura) {
                return;
            }
            if ($i === $n) {
                // maximal: no cabe ninguna pieza más de las que quedan
                for ($j = 0; $j < $n; $j++) {
                    if ($cnt[$j] - $pat[$j] > 0 && $this->peso[$j] <= $resto) {
                        return;
                    }
                }
                $out[] = [$uso, $pat];
                return;
            }
            $max = min($cnt[$i], intdiv($resto, $this->peso[$i]));
            $min = ($i === $i0) ? 1 : 0;
            for ($k = $max; $k >= $min; $k--) {
                $pat[$i] = $k;
                $gen($i + 1, $resto - $k * $this->peso[$i], $uso + $k * $this->peso[$i]);
            }
            $pat[$i] = 0;
        };

        // los tipos anteriores a $i0 ya no tienen piezas: se saltan
        $gen($i0, $this->cap, 0);
        if ($this->ruido > 0) {
            // menos desperdicio primero, pero con aleatoriedad para salir de callejones
            foreach ($out as &$o) {
                $o[2] = $o[0] + mt_rand(0, $this->ruido);
            }
            unset($o);
            usort($out, fn ($a, $b) => $b[2] <=> $a[2]);
        } else {
            usort($out, fn ($a, $b) => $b[0] <=> $a[0]);
        }
        return $out;
    }

    /** Patrones (cantidades por tipo) → lista de largos por barra. */
    private function aLargos(array $patrones): array
    {
        $barras = [];
        foreach ($patrones as $pat) {
            $b = [];
            foreach ($pat as $i => $k) {
                for ($j = 0; $j < $k; $j++) {
                    $b[] = $this->largoTipo[$i];
                }
            }
            rsort($b);
            $barras[] = $b;
        }
        return $barras;
    }
}
