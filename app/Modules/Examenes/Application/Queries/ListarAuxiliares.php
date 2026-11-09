<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Queries;

use App\Modules\Examenes\Application\Contracts\AuxiliarGateway;
use App\Modules\Examenes\Application\DTOs\AuxiliarData;

/**
 * Paso 4 del asistente: los auxiliares del docente y, al buscar, cualquier
 * cuenta de auxiliar activa.
 */
final readonly class ListarAuxiliares
{
    private const MINIMO_PARA_BUSCAR = 3;

    public function __construct(
        private AuxiliarGateway $auxiliares,
    ) {}

    /**
     * @return list<AuxiliarData>
     */
    public function execute(int $usuarioId, ?string $buscar): array
    {
        $buscar = $buscar === null ? '' : trim($buscar);

        return $this->auxiliares->listar(
            $usuarioId,
            mb_strlen($buscar) >= self::MINIMO_PARA_BUSCAR ? $buscar : null,
        );
    }
}
