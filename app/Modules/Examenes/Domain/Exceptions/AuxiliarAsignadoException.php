<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Exceptions;

use RuntimeException;

/**
 * El auxiliar atiende un aula de un examen que todavia no termino: no se
 * quita de la lista hasta que deje esa aula.
 */
final class AuxiliarAsignadoException extends RuntimeException {}
