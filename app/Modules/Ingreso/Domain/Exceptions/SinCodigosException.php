<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Exceptions;

use RuntimeException;

/**
 * No hay a quien emitir (`SIN_HABILITADOS`), hay habilitados sin aula
 * (`SIN_AULA`) o no hay codigos que enviar (`SIN_EMITIR`).
 */
final class SinCodigosException extends RuntimeException
{
    private function __construct(
        string $mensaje,
        public readonly string $codigo,
    ) {
        parent::__construct($mensaje);
    }

    public static function sinHabilitados(): self
    {
        return new self('El examen no tiene estudiantes habilitados.', 'SIN_HABILITADOS');
    }

    /**
     * La credencial lleva el aula: sin aula no hay nada que imprimir.
     */
    public static function sinAula(int $cuantos): self
    {
        return new self(
            sprintf('%d %s sin aula.', $cuantos, $cuantos === 1 ? 'habilitado' : 'habilitados'),
            'SIN_AULA',
        );
    }

    public static function sinEmitir(): self
    {
        return new self('No hay códigos emitidos que enviar.', 'SIN_EMITIR');
    }
}
