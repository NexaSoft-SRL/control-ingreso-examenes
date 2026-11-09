<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\Exceptions;

use RuntimeException;

/**
 * El respaldo que se quiso descargar o restaurar ya no existe o su archivo no esta en disco.
 */
final class RespaldoNoEncontradoException extends RuntimeException {}
