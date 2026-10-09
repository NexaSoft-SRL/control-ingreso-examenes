<?php

declare(strict_types=1);

namespace App\Modules\Academico\Domain\Exceptions;

use RuntimeException;

/**
 * Un archivo de la oferta no existe, no se puede leer o no tiene la forma
 * esperada; o se pidio una facultad que el sistema no conoce.
 */
final class FuenteNoDisponibleException extends RuntimeException {}
