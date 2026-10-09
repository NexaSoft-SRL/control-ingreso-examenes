<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\Exceptions;

use RuntimeException;

/**
 * Quien administra las cuentas no puede dejarse a si mismo sin acceso.
 */
final class BloqueoPropioException extends RuntimeException {}
