<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\Enums;

/**
 * Los correos del sistema que dejan registro en `envios_correo`.
 */
enum TipoCorreo: string
{
    case CodigoQr = 'CODIGO_QR';
    case Aviso = 'AVISO';
}
