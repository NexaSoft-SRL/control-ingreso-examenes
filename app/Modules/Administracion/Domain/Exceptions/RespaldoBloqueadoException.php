<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\Exceptions;

use RuntimeException;

/**
 * La restauración se bloquea por seguridad cuando existe un examen activo
 * o en curso en el sistema, para evitar la pérdida de asistencias e ingresos en vivo.
 */
final class RespaldoBloqueadoException extends RuntimeException {}
