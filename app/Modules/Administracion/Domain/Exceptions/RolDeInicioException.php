<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\Exceptions;

use RuntimeException;

/**
 * Los roles de inicio no se renombran ni se eliminan.
 */
final class RolDeInicioException extends RuntimeException {}
