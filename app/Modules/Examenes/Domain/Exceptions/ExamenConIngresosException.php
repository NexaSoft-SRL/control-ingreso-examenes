<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Exceptions;

use RuntimeException;

/**
 * El examen ya tiene ingresos registrados: solo se le pueden cambiar las
 * normas, y no se elimina.
 */
final class ExamenConIngresosException extends RuntimeException {}
