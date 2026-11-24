<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Queries;

use App\Modules\Academico\Application\Contracts\AlcanceDocenteGateway;
use App\Modules\Estudiantes\Application\Contracts\FotosDeEstudiantes;
use App\Modules\Estudiantes\Application\Contracts\InscripcionGateway;
use App\Modules\Estudiantes\Domain\Exceptions\GrupoAjenoException;
use App\Modules\Estudiantes\Domain\Exceptions\NoInscritoException;

/**
 * La foto de un estudiante vista por el docente de su grupo: solo la de un
 * inscrito en un grupo suyo.
 */
final readonly class VerFotoDeInscrito
{
    public function __construct(
        private InscripcionGateway $inscripciones,
        private AlcanceDocenteGateway $alcance,
        private FotosDeEstudiantes $fotos,
    ) {}

    /**
     * El JPEG, o null si el grupo no existe o el estudiante no tiene foto.
     *
     * @throws GrupoAjenoException
     * @throws NoInscritoException
     */
    public function execute(int $usuarioId, int $grupoId, int $estudianteId): ?string
    {
        if ($this->inscripciones->grupo($grupoId) === null) {
            return null;
        }

        if (! $this->alcance->esGrupoDelDocente($usuarioId, $grupoId)) {
            throw new GrupoAjenoException;
        }

        if ($this->inscripciones->existentes([$estudianteId], [$grupoId]) === []) {
            throw new NoInscritoException;
        }

        return $this->fotos->foto($estudianteId);
    }
}
