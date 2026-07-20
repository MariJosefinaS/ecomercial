<?php

namespace App\Support;

/**
 * Validación de CUIT/CUIL argentino (dígito verificador).
 * 11 dígitos: 2 de tipo + 8 del DNI/empresa + 1 verificador.
 */
class Cuit
{
    private const PESOS = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];

    /** ¿El CUIT/CUIL es válido? Acepta con o sin guiones/espacios. */
    public static function valida(?string $cuit): bool
    {
        $n = preg_replace('/\D/', '', (string) $cuit);

        if (strlen($n) !== 11) {
            return false;
        }

        $suma = 0;
        for ($i = 0; $i < 10; $i++) {
            $suma += (int) $n[$i] * self::PESOS[$i];
        }

        $verificador = 11 - ($suma % 11);
        if ($verificador === 11) {
            $verificador = 0;
        } elseif ($verificador === 10) {
            $verificador = 9;
        }

        return $verificador === (int) $n[10];
    }
}
