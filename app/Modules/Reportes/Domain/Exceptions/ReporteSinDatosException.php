<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Domain\Exceptions;

use RuntimeException;

/**
 * El alcance pedido no tiene ningun examen: no hay archivo que armar.
 */
final class ReporteSinDatosException extends RuntimeException {}
