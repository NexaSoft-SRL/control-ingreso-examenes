<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Queries;

use App\Modules\Academico\Application\Contracts\AlcanceDocenteGateway;
use App\Modules\Estudiantes\Application\Contracts\InscripcionGateway;
use App\Modules\Estudiantes\Application\DTOs\PlazoFotoDeGrupoData;
use App\Modules\Estudiantes\Domain\Exceptions\GrupoAjenoException;

/**
 * El plazo de fotos de un grupo, solo para su docente.
 */
final readonly class ConsultarPlazoFotoDeGrupo
{
    public function __construct(
        private InscripcionGateway $inscripciones,
        private AlcanceDocenteGateway $alcance,
    ) {}

    /**
     * Nulo si el grupo no existe.
     *
     * @throws GrupoAjenoException
     */
    public function execute(int $usuarioId, int $grupoId): ?PlazoFotoDeGrupoData
    {
        if ($this->inscripciones->grupo($grupoId) === null) {
            return null;
        }

        if (! $this->alcance->esGrupoDelDocente($usuarioId, $grupoId)) {
            throw new GrupoAjenoException;
        }

        return $this->inscripciones->plazoFotoDeGrupo($grupoId);
    }
}
