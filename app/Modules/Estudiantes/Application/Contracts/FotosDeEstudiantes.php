<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Contracts;

/**
 * La foto de un estudiante para otros modulos (la puerta, la lista del
 * aula): los bytes del JPEG, o nulo si todavia no la subio.
 */
interface FotosDeEstudiantes
{
    public function foto(int $estudianteId): ?string;
}
