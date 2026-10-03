<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Exceptions;

use RuntimeException;

/**
 * El ambiente ya tiene tantos estudiantes como su capacidad (HU-14).
 */
final class AmbienteSinCupoException extends RuntimeException {}
