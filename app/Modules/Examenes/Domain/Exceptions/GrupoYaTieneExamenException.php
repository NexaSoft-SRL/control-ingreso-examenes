<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Exceptions;

/**
 * Un grupo no puede estar en dos examenes del mismo tipo.
 */
final class GrupoYaTieneExamenException extends DatoInvalidoException
{
    public function __construct(string $codigoGrupo, string $tipoConArticulo)
    {
        parent::__construct(
            'grupos',
            "El grupo {$codigoGrupo} ya tiene {$tipoConArticulo}.",
        );
    }
}
