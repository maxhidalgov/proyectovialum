<?php

namespace App\Support;

/**
 * RUT chileno: formato canónico "12345678-9" (sin puntos, con guion, K en mayúscula).
 * Si se guarda sin guion, Bsale toma todo como número y deja el dígito verificador vacío en el documento.
 */
class Rut
{
    /** Limpia puntos/espacios y pone el guion antes del último carácter. null si no es plausible. */
    public static function normalizar(?string $rut): ?string
    {
        $limpio = strtoupper(preg_replace('/[^0-9Kk]/', '', (string) $rut));
        if (!preg_match('/^[0-9]{7,8}[0-9K]$/', $limpio)) {
            return null;
        }
        return substr($limpio, 0, -1) . '-' . substr($limpio, -1);
    }

    /** Dígito verificador (módulo 11) de la parte numérica. */
    public static function digitoVerificador(string $cuerpo): string
    {
        $suma = 0;
        $mult = 2;
        for ($i = strlen($cuerpo) - 1; $i >= 0; $i--) {
            $suma += (int) $cuerpo[$i] * $mult;
            $mult = $mult === 7 ? 2 : $mult + 1;
        }
        $dv = 11 - ($suma % 11);
        return $dv === 11 ? '0' : ($dv === 10 ? 'K' : (string) $dv);
    }

    /** RUT normalizado si es válido (formato + dígito verificador), si no null. */
    public static function validar(?string $rut): ?string
    {
        $norm = self::normalizar($rut);
        if ($norm === null) {
            return null;
        }
        [$cuerpo, $dv] = explode('-', $norm);
        return self::digitoVerificador($cuerpo) === $dv ? $norm : null;
    }
}
