<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Exceptions;

use RuntimeException;

/**
 * El codigo de aula no sirve ahora: no es de ningun aula, o su examen
 * todavia no abre o ya termino. En los dos ultimos casos el mensaje dice
 * cuando: un codigo que ya cerro no abre nada.
 */
final class CodigoDeAulaInvalidoException extends RuntimeException
{
    private const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    public function __construct(string $mensaje = 'Código no válido.')
    {
        parent::__construct($mensaje);
    }

    // '2026-10-23 09:12' (abre) → 'La puerta abre el 23 oct a las 09:12.'
    public static function todaviaNo(string $abre): self
    {
        return new self(sprintf('La puerta abre el %s.', self::cuando($abre)));
    }

    public static function yaTermino(string $cierra): self
    {
        return new self(sprintf('El examen ya terminó: cerró el %s.', self::cuando($cierra)));
    }

    private static function cuando(string $instante): string
    {
        if (preg_match('/^\d{4}-(\d{2})-(\d{2}) (\d{2}:\d{2})/', $instante, $partes) !== 1) {
            return $instante;
        }

        return sprintf('%d %s a las %s', (int) $partes[2], self::MESES[(int) $partes[1] - 1] ?? $partes[1], $partes[3]);
    }
}
