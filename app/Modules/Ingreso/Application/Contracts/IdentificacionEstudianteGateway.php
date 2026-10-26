<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

/**
 * Un solo campo «Código SIS o C.I.» en la puerta: primero se busca el
 * codigo universitario y, si no, el C.I. sin espacios, guiones ni
 * mayusculas («5003748-1A», «5003748 1A» y «50037481a» son el mismo).
 */
interface IdentificacionEstudianteGateway
{
    /**
     * @return array{codigo: string, medio: 'codigo'|'documento'}|null
     */
    public function codigoPorIdentificador(string $identificador): ?array;
}
