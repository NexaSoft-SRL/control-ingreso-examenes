<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Rules;

/**
 * Como se guarda y como se compara el texto de una plantilla de normas.
 */
final class TextoDeNorma
{
    public const MINIMO = 3;

    public const MAXIMO = 300;

    private const SIN_TILDE = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
    ];

    /**
     * Sin espacios a los lados ni repetidos.
     */
    public static function limpiar(string $texto): string
    {
        return trim(preg_replace('/\s+/u', ' ', $texto) ?? $texto);
    }

    /**
     * La forma con que se decide si dos textos son el mismo: sin mayusculas
     * ni tildes.
     */
    public static function clave(string $texto): string
    {
        return strtr(mb_strtolower(self::limpiar($texto)), self::SIN_TILDE);
    }

    public static function sonIguales(string $uno, string $otro): bool
    {
        return self::clave($uno) === self::clave($otro);
    }
}
