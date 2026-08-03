<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Unifica la convención de cotizaciones.total a NETO.
 *
 * Las cotizaciones importadas de Winperfil guardaban `total` en BRUTO (BASE × 1.19),
 * mientras que las cotizaciones de la app lo guardan en NETO. Eso hacía que la columna
 * "TOTAL NETO" mostrara bruto para Winperfil y que el PDF (que suma las líneas, en neto)
 * no cuadrara con la tabla.
 *
 * Esta migración:
 *   1) Convierte total BRUTO → NETO (÷1.19 = BASE) en las cotizaciones Winperfil.
 *   2) Escala las líneas para que sumen exactamente el nuevo total neto (el recargo por
 *      ventana —columna "Porcentaje"— no viene por línea en la API; se recupera aquí).
 *
 * Idempotente: solo toca filas donde total sigue en bruto, detectado por la razón
 * total/Σlíneas > 1.10 (bruto ≈ 1.19–1.23; neto ≈ 1.00). Al re-ejecutarse no reconvierte.
 */
return new class extends Migration
{
    public function up(): void
    {
        $cots = DB::table('cotizaciones')
            ->whereNotNull('winperfil_numero')
            ->get(['id', 'total']);

        foreach ($cots as $cot) {
            $sumDet = (float) DB::table('cotizacion_detalles')
                ->where('cotizacion_id', $cot->id)
                ->where('tipo_item', 'winperfil')
                ->sum('total');

            if ($sumDet <= 0 || $cot->total <= 0) {
                continue;
            }

            // Guarda de idempotencia: si ya está en neto (total ≈ Σlíneas), no tocar.
            if ($cot->total / $sumDet <= 1.10) {
                continue;
            }

            $nuevoTotal = (int) round($cot->total / 1.19); // = BASE (neto)
            $factor     = $nuevoTotal / $sumDet;

            $lineas   = DB::table('cotizacion_detalles')
                ->where('cotizacion_id', $cot->id)
                ->where('tipo_item', 'winperfil')
                ->orderBy('id')
                ->get(['id', 'cantidad']);

            $acumulado = 0;
            $ultimo    = count($lineas) - 1;
            foreach ($lineas as $i => $ln) {
                $cant = (float) $ln->cantidad ?: 1;
                if ($i === $ultimo) {
                    $subtotal = $nuevoTotal - $acumulado; // residual → cuadre exacto
                } else {
                    $orig     = (float) DB::table('cotizacion_detalles')->where('id', $ln->id)->value('total');
                    $subtotal = (int) round($orig * $factor);
                    $acumulado += $subtotal;
                }
                DB::table('cotizacion_detalles')->where('id', $ln->id)->update([
                    'total'           => $subtotal,
                    'precio_unitario' => $cant > 0 ? round($subtotal / $cant) : $subtotal,
                ]);
            }

            DB::table('cotizaciones')->where('id', $cot->id)->update(['total' => $nuevoTotal]);
        }
    }

    public function down(): void
    {
        // No reversible con seguridad (transformación de datos). No-op.
    }
};
