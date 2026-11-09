<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\Rules;

/**
 * El usuario con que se inicia sesion: nombre y apellido separados por un
 * espacio, en minusculas y sin tildes («jose soruco»), facil de leer. Se
 * compara sin los espacios: «Jose Soruco», «jose  soruco» y «josesoruco»
 * son la misma cuenta, y por eso dos cuentas no pueden diferir solo en el
 * espacio.
 */
final class NombreDeUsuario
{
    /**
     * Letras y numeros, con un espacio, punto o guion entre las partes.
     */
    public const FORMATO = '/^[a-z0-9]+(?:[ ._-][a-z0-9]+)*$/';

    public const LARGO_MAXIMO = 60;

    private const SIN_TILDES = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n', 'ç' => 'c',
    ];

    /**
     * Como se guarda: minusculas, sin tildes y con un solo espacio entre
     * palabras. «  Letícia   BLANCO » → «leticia blanco».
     */
    public static function normalizar(string $usuario): string
    {
        $minusculas = strtr(mb_strtolower(trim($usuario)), self::SIN_TILDES);

        return (string) preg_replace('/\s+/u', ' ', $minusculas);
    }

    /**
     * Como se compara: sin espacios. «jose soruco» → «josesoruco».
     */
    public static function compacto(string $usuario): string
    {
        return str_replace(' ', '', self::normalizar($usuario));
    }

    /**
     * Una palabra lista para el usuario: sin tildes ni signos.
     */
    public static function limpiar(string $palabra): string
    {
        $minusculas = strtr(mb_strtolower($palabra), self::SIN_TILDES);

        return preg_replace('/[^a-z0-9]/', '', $minusculas) ?? '';
    }
}
