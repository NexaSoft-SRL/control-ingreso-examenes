<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\Exceptions;

use RuntimeException;

/**
 * Nadie le quita a su propio rol el permiso de usuarios y roles: se quedaria sin poder devolverlo.
 */
final class PermisoPropioException extends RuntimeException {}
