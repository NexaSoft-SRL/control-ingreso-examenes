<?php

declare(strict_types=1);

namespace App\Modules\Academico\Domain\Exceptions;

use RuntimeException;

/**
 * El periodo ya cerró: su calendario de exámenes solo se consulta.
 */
final class PeriodoCerradoException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('El período está cerrado: su calendario solo se consulta.');
    }
}
