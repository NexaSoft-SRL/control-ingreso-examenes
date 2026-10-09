<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Exceptions;

use RuntimeException;

/**
 * El documento de la carga ya es de otro estudiante del padron.
 */
final class DocumentoEnUsoException extends RuntimeException
{
    public function __construct(string $mensaje = 'Ese documento ya pertenece a otro estudiante.')
    {
        parent::__construct($mensaje);
    }
}
