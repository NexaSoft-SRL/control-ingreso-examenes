<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Exceptions;

use RuntimeException;

/**
 * La plantilla es una norma predefinida del sistema o es de otra cuenta:
 * solo se edita y se quita la propia.
 */
final class PlantillaAjenaException extends RuntimeException
{
    public function __construct(
        public readonly bool $predefinida,
    ) {
        parent::__construct(
            $predefinida
                ? 'Las normas predefinidas no se modifican.'
                : 'Esta plantilla no es tuya.',
        );
    }
}
