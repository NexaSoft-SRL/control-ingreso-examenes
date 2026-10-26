<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\Enums;

/**
 * El recorrido de un correo: en cola, enviado o fallido con su error.
 */
enum EstadoEnvio: string
{
    case Pendiente = 'PENDIENTE';
    case Enviado = 'ENVIADO';
    case Fallido = 'FALLIDO';
}
