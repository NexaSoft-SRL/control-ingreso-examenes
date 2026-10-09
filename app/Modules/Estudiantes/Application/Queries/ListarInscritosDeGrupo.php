<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Queries;

use App\Modules\Academico\Application\Contracts\AlcanceDocenteGateway;
use App\Modules\Estudiantes\Application\Contracts\InscripcionGateway;
use App\Modules\Estudiantes\Application\DTOs\FiltroListaData;
use App\Modules\Estudiantes\Application\DTOs\PaginaData;
use App\Modules\Estudiantes\Domain\Exceptions\GrupoAjenoException;

/**
 * La lista de inscritos de un grupo, solo para su docente.
 */
final readonly class ListarInscritosDeGrupo
{
    public function __construct(
        private InscripcionGateway $inscripciones,
        private AlcanceDocenteGateway $alcance,
    ) {}

    /**
     * @return PaginaData|null null si el grupo no existe
     *
     * @throws GrupoAjenoException
     */
    public function execute(int $usuarioId, int $grupoId, FiltroListaData $filtro): ?PaginaData
    {
        if ($this->inscripciones->grupo($grupoId) === null) {
            return null;
        }

        if (! $this->alcance->esGrupoDelDocente($usuarioId, $grupoId)) {
            throw new GrupoAjenoException;
        }

        return $this->inscripciones->inscritosDeGrupo($grupoId, $filtro);
    }
}
