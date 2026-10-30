<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Domain\Exceptions;

use RuntimeException;

/**
 * El examen todavia no llego a su fecha: no tiene asistencia ni ausentes.
 */
final class ExamenSinRendirException extends RuntimeException {}
