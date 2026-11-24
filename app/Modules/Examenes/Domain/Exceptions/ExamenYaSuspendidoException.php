<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Exceptions;

use RuntimeException;

/**
 * Un examen suspendido no se vuelve a suspender: primero se reanuda.
 */
final class ExamenYaSuspendidoException extends RuntimeException {}
