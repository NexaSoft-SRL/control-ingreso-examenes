<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Exceptions;

use InvalidArgumentException;

/**
 * El QR leido no es de ningun examen abierto que la persona atiende en el
 * aula: falso, de otro examen o ilegible. Es un intento, no un error de
 * datos.
 */
final class CodigoQrNoValido extends InvalidArgumentException {}
