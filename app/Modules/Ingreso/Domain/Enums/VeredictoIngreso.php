<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Enums;

enum VeredictoIngreso: string
{
    case Admitido = 'ADMITIDO';
    case NoHabilitado = 'NO_HABILITADO';
    case YaIngreso = 'YA_INGRESO';
    case NoCorresponde = 'NO_CORRESPONDE';
    case Expulsado = 'EXPULSADO';

    public function permiteIngreso(): bool
    {
        return $this === self::Admitido;
    }
}
