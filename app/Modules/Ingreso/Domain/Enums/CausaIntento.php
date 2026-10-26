<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Enums;

/**
 * Intentos en la puerta que no se resuelven a un examen del aula. Quedan
 * en las incidencias de ingreso sin examen, distinguidos por su causa.
 */
enum CausaIntento: string
{
    case NoInscrito = 'NO_INSCRITO';
    case NoRegistrado = 'NO_REGISTRADO';
    case QrNoValido = 'QR_NO_VALIDO';
}
