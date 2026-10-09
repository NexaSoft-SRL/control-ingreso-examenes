<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\Exceptions;

use RuntimeException;

/**
 * Un rol con cuentas asignadas no se elimina.
 */
final class RolConCuentasException extends RuntimeException {}
