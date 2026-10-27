<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Exceptions;

use DomainException;

final class IncidenciaYaAtendidaException extends DomainException
{
    public function __construct()
    {
        parent::__construct('La incidencia ya estaba atendida.');
    }
}
