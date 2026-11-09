<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Application\Queries;

use App\Modules\Monitoreo\Application\Contracts\SeguimientoGateway;
use App\Modules\Monitoreo\Application\DTOs\ExamenEnCursoData;

/**
 * El selector de «En curso» y el destino de «Mi aula»: lo que la cuenta
 * puede seguir hoy y el proximo dia con examen.
 */
final readonly class ListarEnCurso
{
    public function __construct(
        private SeguimientoGateway $seguimiento,
    ) {}

    /**
     * @return list<ExamenEnCursoData>
     */
    public function execute(int $usuarioId): array
    {
        return $this->seguimiento->enCurso($usuarioId);
    }
}
