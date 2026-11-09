<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Domain\Exceptions;

use RuntimeException;

/**
 * Tiene el permiso de la pantalla, pero el examen o el aula no son suyos.
 */
final class SinAlcanceException extends RuntimeException {}
