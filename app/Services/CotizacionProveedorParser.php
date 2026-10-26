<?php

namespace App\Services;

use Smalot\PdfParser\Parser;

/**
 * Lee el PDF de una cotización de proveedor (formato Haustek / Winhouse) y devuelve sus líneas ya
 * separadas por columna: Item, Código, Descripción, Cantidad, Unidad, Precio Un., % Desc., Desc. Total, Total.
 *
 * El texto "plano" de este PDF sale con las columnas pegadas (el total y el precio unitario quedan como
 * "54.86639.190"), así que se usan las COORDENADAS de cada fragmento de texto: cada línea es la fila de un
 * número de ítem y cada fragmento se asigna a la columna cuya zona horizontal le corresponde. Una descripción
 * que sigue en la línea de abajo se une a la fila del ítem de arriba.
 *
 * Cada línea trae `cuadra`: cantidad × precio − descuento = total (tolerancia $2). Si una línea no cuadra, el
 * validador la marca como "lectura dudosa" en vez de darla por buena.
 */
class CotizacionProveedorParser
{
    /** Zonas horizontales (x) de cada columna en el PDF de Haustek. */
    private const COLUMNAS = [
        'item'        => [0, 45],
        'codigo'      => [45, 112],
        'descripcion' => [112, 270],
        'cantidad'    => [270, 318],
        'unidad'      => [318, 350],
        'precio'      => [350, 405],
        'pct'         => [405, 460],
        'desc'        => [460, 540],
        'total'       => [540, 700],
    ];

    public function parse(string $ruta): array
    {
        $pdf = (new Parser())->parseFile($ruta);
        $lineas = [];
        $encabezado = ['numero' => null, 'fecha' => null, 'cliente' => null];
        $totales = ['neto' => null, 'iva' => null, 'total' => null];
        $textoPlano = '';

        foreach ($pdf->getPages() as $pagina) {
            $frag = [];
            foreach ($pagina->getDataTm() as $f) {
                $texto = (string) $f[1];
                if (trim($texto) === '') {
                    continue;
                }
                $frag[] = ['x' => (float) $f[0][4], 'y' => (float) $f[0][5], 't' => $texto];
            }
            $textoPlano .= implode(' ', array_column($frag, 't')) . "\n";

            // Alto de la tabla: bajo el encabezado "Código/Descripción…" y sobre "Observaciones:" / "Kilos"
            $yCabecera = null;
            $yFin = -INF;
            foreach ($frag as $f) {
                if (trim($f['t']) === 'Código') {
                    $yCabecera = $f['y'];
                }
                if (preg_match('/^(Observaciones:|- Kilos Totales)/', trim($f['t']))) {
                    $yFin = max($yFin, $f['y']);
                }
            }
            if ($yCabecera === null) {
                continue;
            }
            $yFinal = $yFin === -INF ? -INF : $yFin + 6;

            // Filas = fragmentos de la columna Item con un número
            $filas = [];
            foreach ($frag as $f) {
                if ($f['y'] < $yCabecera - 2 && $f['y'] > $yFinal && $f['x'] < self::COLUMNAS['item'][1] && preg_match('/^\s*\d+\s*$/', $f['t'])) {
                    $filas[] = ['y' => $f['y'], 'item' => (int) trim($f['t']), 'frag' => []];
                }
            }
            usort($filas, fn ($a, $b) => $b['y'] <=> $a['y']);
            foreach ($frag as $f) {
                if ($f['y'] >= $yCabecera - 2 || $f['y'] <= $yFinal) {
                    continue;
                }
                // fila = el ítem más cercano por encima (o a la misma altura)
                $destino = null;
                foreach ($filas as $i => $fila) {
                    if ($fila['y'] + 1 >= $f['y']) {
                        $destino = $i;
                    } else {
                        break;
                    }
                }
                if ($destino !== null) {
                    $filas[$destino]['frag'][] = $f;
                }
            }

            foreach ($filas as $fila) {
                $lineas[] = $this->construirLinea($fila);
            }
        }

        // Encabezado y totales con el texto plano
        if (preg_match('/COTIZACI[ÓO]N N°:\s*([\d.]+)/u', $textoPlano, $m)) {
            $encabezado['numero'] = str_replace('.', '', $m[1]);
        }
        if (preg_match('/Fecha Emisi[óo]n:\s*(\d{2}\/\d{2}\/\d{4})/u', $textoPlano, $m)) {
            $encabezado['fecha'] = $m[1];
        }
        if (preg_match('/Cliente:\s*(.+?)\s+Fecha/u', $textoPlano, $m)) {
            $encabezado['cliente'] = trim($m[1]);
        }
        $totales = $this->leerTotales($pdf);

        return ['encabezado' => $encabezado, 'lineas' => $lineas, 'totales' => $totales];
    }

    private function construirLinea(array $fila): array
    {
        $col = array_fill_keys(array_keys(self::COLUMNAS), []);
        // Orden de lectura: de arriba abajo y de izquierda a derecha, para unir descripciones partidas
        $frag = $fila['frag'];
        usort($frag, fn ($a, $b) => [round($b['y']), $a['x']] <=> [round($a['y']), $b['x']]);
        foreach ($frag as $f) {
            foreach (self::COLUMNAS as $nombre => [$x0, $x1]) {
                if ($f['x'] >= $x0 && $f['x'] < $x1) {
                    $col[$nombre][] = $f;
                    break;
                }
            }
        }
        $texto = function (string $c) use ($col): string {
            $t = '';
            $yPrev = null;
            foreach ($col[$c] as $f) {
                if ($yPrev !== null && abs($f['y'] - $yPrev) > 3) {
                    $t .= ' ';
                }
                $t .= $f['t'];
                $yPrev = $f['y'];
            }

            return trim(preg_replace('/\s+/', ' ', $t));
        };
        $num = fn (string $c): float => (float) str_replace(['.', ','], ['', '.'], $texto($c) ?: '0');

        $linea = [
            'item'            => $fila['item'],
            'codigo'          => $texto('codigo'),
            'descripcion'     => $texto('descripcion'),
            'cantidad'        => $num('cantidad'),
            'unidad'          => $texto('unidad'),
            'precio_unitario' => $num('precio'),
            'pct_desc'        => $num('pct'),
            'desc_total'      => $num('desc'),
            'total'           => $num('total'),
        ];
        $esperado = $linea['cantidad'] * $linea['precio_unitario'] - $linea['desc_total'];
        $linea['cuadra'] = abs($esperado - $linea['total']) <= 2;
        // El descuento declarado también tiene que ser coherente con el %
        $descEsperado = $linea['cantidad'] * $linea['precio_unitario'] * $linea['pct_desc'] / 100;
        $linea['desc_coherente'] = abs($descEsperado - $linea['desc_total']) <= max(2, $descEsperado * 0.01);

        return $linea;
    }

    private function leerTotales($pdf): array
    {
        $neto = $iva = $total = null;
        foreach ($pdf->getPages() as $pagina) {
            $frag = [];
            foreach ($pagina->getDataTm() as $f) {
                $t = trim((string) $f[1]);
                if ($t !== '') {
                    $frag[] = ['x' => (float) $f[0][4], 'y' => (float) $f[0][5], 't' => $t];
                }
            }
            // Las etiquetas vienen partidas ("NE" + "TO", "19% " + "I" + "VA" + ":"): se juntan por fila
            $etiquetas = [];
            foreach ($frag as $f) {
                if ($f['x'] >= 425 && $f['x'] < 520) {
                    $clave = (string) round($f['y']);
                    $etiquetas[$clave] = ($etiquetas[$clave] ?? '') . $f['t'];
                }
            }
            foreach ($etiquetas as $y => $et) {
                $et = strtoupper(preg_replace('/\s+/', '', $et));
                $campo = $et === 'NETO' ? 'neto' : ($et === 'TOTAL' ? 'total' : (str_starts_with($et, '19%IVA') ? 'iva' : null));
                if (!$campo) {
                    continue;
                }
                foreach ($frag as $v) {
                    if ($v['x'] >= 540 && abs($v['y'] - (float) $y) <= 3 && preg_match('/^\d{1,3}(?:\.\d{3})*$/', $v['t'])) {
                        $$campo = (float) str_replace('.', '', $v['t']);
                    }
                }
            }
        }

        return ['neto' => $neto, 'iva' => $iva, 'total' => $total];
    }
}
