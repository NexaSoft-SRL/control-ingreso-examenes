<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Exceptions;

use RuntimeException;

/**
 * La cuenta tiene el permiso de la pantalla, pero el examen no es suyo.
 */
final class ExamenAjenoException extends RuntimeException {}
