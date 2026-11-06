<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Queries;

use App\Modules\Academico\Application\Contracts\AlcanceDocenteGateway;
use App\Modules\Estudiantes\Application\Contracts\FotosDeEstudiantes;
use App\Modules\Estudiantes\Application\Contracts\InscripcionGateway;
use App\Modules\Estudiantes\Domain\Exceptions\GrupoAjenoException;

final readonly class ConsultarFotoDeInscrito
{
    public function __construct(
        private InscripcionGateway $inscripciones,
        private AlcanceDocenteGateway $alcance,
        private FotosDeEstudiantes $fotos,
    ) {}

    /**
     * @throws GrupoAjenoException
     */
    public function execute(int $usuarioId, int $grupoId, int $estudianteId): ?string
    {
        if ($this->inscripciones->grupo($grupoId) === null) {
            return null;
        }

        if (! $this->alcance->esGrupoDelDocente($usuarioId, $grupoId)) {
            throw new GrupoAjenoException;
        }

        if (! $this->inscripciones->inscritoEnGrupo($grupoId, $estudianteId)) {
            return null;
        }

        return $this->fotos->foto($estudianteId);
    }
}
