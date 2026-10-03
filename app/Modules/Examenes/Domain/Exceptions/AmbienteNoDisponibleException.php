<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Exceptions;

use RuntimeException;

/**
 * El ambiente no puede asignarse: esta en mantenimiento, inactivo, o ya
 * tiene otro examen que se solapa en el horario (HU-10).
 */
final class AmbienteNoDisponibleException extends RuntimeException {}
