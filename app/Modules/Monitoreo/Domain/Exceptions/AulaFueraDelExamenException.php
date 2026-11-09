<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Domain\Exceptions;

use RuntimeException;

/**
 * El aula pedida no es una de las aulas del examen.
 */
final class AulaFueraDelExamenException extends RuntimeException {}
