<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\Exceptions;

use RuntimeException;

/**
 * El archivo se leyo bien pero no se pudo escribir en el disco del servidor
 * (por ejemplo, una carpeta sin permiso de escritura). Es un error del
 * servidor, no de quien subio el archivo.
 */
final class AlmacenamientoNoDisponibleException extends RuntimeException {}
