<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

interface ContextoOperativoPuntoControl
{
    /**
     * Devuelve exclusivamente los exámenes y aulas que el usuario
     * está autorizado a operar. Nunca devuelve el catálogo general.
     *
     * @return list<array<string, mixed>>
     */
    public function examenesAutorizados(int $usuarioId): array;
}
