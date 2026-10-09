<?php

declare(strict_types=1);

namespace App\Modules\Academico\Domain\Rules;

/**
 * Como se escriben y se comparan los nombres que trae la oferta, que llega
 * entera en mayusculas y sin tildes.
 */
final class NombresDeOferta
{
    private const SIN_TILDE = [
        'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A', 'Ã' => 'A',
        'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I',
        'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O', 'Õ' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U',
        'Ñ' => 'N', 'Ç' => 'C',
    ];

    /**
     * Palabras que van en minuscula dentro del nombre de una carrera, una
     * asignatura o un edificio.
     */
    private const ENLACES = [
        'a', 'al', 'con', 'de', 'del', 'e', 'el', 'en', 'la', 'las', 'los',
        'o', 'para', 'por', 'u', 'y',
    ];

    /**
     * Valores con que la oferta dice que un grupo no tiene docente.
     */
    private const SIN_DOCENTE = [
        'POR DESIGNAR DOCENTE',
        'POR DESGINAR DOCENTE',
        'POR DESIGNAR',
        'POR DESGINAR',
    ];

    /**
     * La identidad de un nombre: mayusculas, sin tildes y con espacios
     * simples.
     */
    public static function normalizar(string $nombre): string
    {
        $mayusculas = strtr(mb_strtoupper($nombre, 'UTF-8'), self::SIN_TILDE);
        $simple = preg_replace('/\s+/u', ' ', $mayusculas);

        return trim($simple ?? $mayusculas);
    }

    /**
     * Un docente real tiene al menos cinco letras y no es uno de los
     * marcadores de «por designar» (la fuente trae uno con errata).
     */
    public static function esDocente(?string $nombre): bool
    {
        if ($nombre === null) {
            return false;
        }

        $normalizado = self::normalizar($nombre);

        if (in_array($normalizado, self::SIN_DOCENTE, true)) {
            return false;
        }

        return preg_match_all('/\p{L}/u', $normalizado) >= 5;
    }

    /**
     * «BLANCO COCA LETICIA» -> «Blanco Coca Leticia».
     */
    public static function persona(string $nombre): string
    {
        $limpio = self::espaciosSimples($nombre);

        return mb_convert_case($limpio, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * «INTRODUCCION A LA PROGRAMACION» -> «Introduccion a la Programacion»;
     * los numeros romanos se conservan («Calculo II»).
     */
    public static function titulo(string $nombre): string
    {
        $palabras = explode(' ', self::espaciosSimples($nombre));
        $resultado = [];

        foreach ($palabras as $posicion => $palabra) {
            $minuscula = mb_strtolower($palabra, 'UTF-8');

            if ($posicion > 0 && in_array($minuscula, self::ENLACES, true)) {
                $resultado[] = $minuscula;

                continue;
            }

            if (preg_match('/^[IVX]+$/', mb_strtoupper($palabra, 'UTF-8')) === 1) {
                $resultado[] = mb_strtoupper($palabra, 'UTF-8');

                continue;
            }

            $resultado[] = mb_convert_case($palabra, MB_CASE_TITLE, 'UTF-8');
        }

        return implode(' ', $resultado);
    }

    private static function espaciosSimples(string $texto): string
    {
        $simple = preg_replace('/\s+/u', ' ', $texto);

        return trim($simple ?? $texto);
    }
}
