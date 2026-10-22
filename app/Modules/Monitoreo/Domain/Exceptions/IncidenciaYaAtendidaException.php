<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Domain\Exceptions;

use RuntimeException;

/**
 * La incidencia ya tiene quien la atendio: no se atiende dos veces.
 */
final class IncidenciaYaAtendidaException extends RuntimeException {}
