<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\Exceptions;

use RuntimeException;

/**
 * La generacion fallo: quedo la fila con su error, pero no hay archivo que
 * descargar ni que restaurar.
 */
final class RespaldoFallidoException extends RuntimeException {}
