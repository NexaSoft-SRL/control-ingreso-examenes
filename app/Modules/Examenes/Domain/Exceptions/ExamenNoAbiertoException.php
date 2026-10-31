<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Exceptions;

use RuntimeException;

/**
 * Solo se finaliza un examen abierto: que ya abrio su puerta, que sigue en
 * su dia y que nadie finalizo todavia.
 */
final class ExamenNoAbiertoException extends RuntimeException {}
