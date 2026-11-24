<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Exceptions;

use RuntimeException;

/**
 * Solo se reanuda un examen que esta suspendido.
 */
final class ExamenNoSuspendidoException extends RuntimeException {}
