<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Domain\Exceptions;

use RuntimeException;

/**
 * La cuenta tiene el permiso, pero el examen o el grupo no es suyo.
 */
final class RecursoAjenoException extends RuntimeException {}
