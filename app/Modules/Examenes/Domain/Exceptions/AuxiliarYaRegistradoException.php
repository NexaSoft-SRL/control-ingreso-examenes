<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Exceptions;

use RuntimeException;

/**
 * Ya hay un auxiliar con ese codigo universitario. Lleva sus datos para
 * que la pantalla lo ofrezca en lugar de crear otro.
 */
final class AuxiliarYaRegistradoException extends RuntimeException
{
    public function __construct(
        public readonly int $cuentaId,
        public readonly string $nombre,
        public readonly string $usuario,
        public readonly bool $mio,
    ) {
        parent::__construct('Auxiliar ya registrado.');
    }
}
