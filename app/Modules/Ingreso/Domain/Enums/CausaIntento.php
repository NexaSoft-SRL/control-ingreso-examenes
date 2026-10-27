<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Enums;

/**
 * Por que la puerta rechazo un intento. Solo en incidencias del sistema.
 * Las tres ultimas vienen del modelo anterior: ya no se asientan, pero
 * quedan en las incidencias convertidas.
 */
enum CausaIntento: string
{
    case NoInscrito = 'NO_INSCRITO';
    case NoHabilitado = 'NO_HABILITADO';
    case NoRegistrado = 'NO_REGISTRADO';
    case QrNoValido = 'QR_NO_VALIDO';
    case Expulsado = 'EXPULSADO';
    case NoCorresponde = 'NO_CORRESPONDE';
    case OtraAula = 'OTRA_AULA';

    public function etiqueta(): string
    {
        return match ($this) {
            self::NoInscrito => 'No inscrito',
            self::NoHabilitado => 'No habilitado',
            self::NoRegistrado => 'Sin registro',
            self::QrNoValido => 'Código QR no válido',
            self::Expulsado => 'Expulsado',
            self::NoCorresponde => 'No corresponde',
            self::OtraAula => 'Otra aula',
        };
    }
}
