<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Domain\Exceptions;

use RuntimeException;

/**
 * El examen pedido no existe.
 */
final class ExamenNoEncontradoException extends RuntimeException {}
