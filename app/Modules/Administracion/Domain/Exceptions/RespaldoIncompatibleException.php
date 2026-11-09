<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\Exceptions;

use RuntimeException;

/**
 * El archivo es de un esquema anterior: su guion nombra tablas y columnas
 * que ya no existen y no puede reponerse sobre el esquema actual.
 */
final class RespaldoIncompatibleException extends RuntimeException {}
