<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Domain\Exceptions;

use RuntimeException;

/**
 * La incidencia no existe o no es de un aula del examen.
 */
final class IncidenciaNoEncontradaException extends RuntimeException {}
