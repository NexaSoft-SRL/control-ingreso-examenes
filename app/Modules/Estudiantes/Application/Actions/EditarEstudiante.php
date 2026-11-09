<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Actions;

use App\Modules\Academico\Application\Contracts\AlcanceDocenteGateway;
use App\Modules\Estudiantes\Application\Contracts\InscripcionGateway;
use App\Modules\Estudiantes\Application\Contracts\PadronGateway;
use App\Modules\Estudiantes\Application\DTOs\EdicionDeEstudianteData;
use App\Modules\Estudiantes\Domain\Exceptions\DocumentoEnUsoException;
use App\Modules\Estudiantes\Domain\Exceptions\GrupoAjenoException;
use App\Modules\Estudiantes\Domain\Exceptions\NoInscritoException;

/**
 * Corrige los nombres y el documento de un estudiante. El estudiante existe
 * una sola vez en el padron: el cambio vale en todos sus grupos y examenes.
 * La administracion corrige a cualquiera; el docente, a un inscrito de un
 * grupo suyo (`$grupoId`). Cada cambio queda en la bitacora con el dato
 * anterior y el nuevo.
 */
final readonly class EditarEstudiante
{
    public function __construct(
        private PadronGateway $padron,
        private InscripcionGateway $inscripciones,
        private AlcanceDocenteGateway $alcance,
    ) {}

    /**
     * @return bool false si el estudiante (o el grupo) no existe
     *
     * @throws GrupoAjenoException
     * @throws NoInscritoException
     * @throws DocumentoEnUsoException
     */
    public function execute(EdicionDeEstudianteData $edicion, ?int $grupoId = null): bool
    {
        if ($grupoId !== null) {
            $grupo = $this->inscripciones->grupo($grupoId);

            if ($grupo === null) {
                return false;
            }

            if (! $this->alcance->esGrupoDelDocente($edicion->usuarioId, $grupo->id)) {
                throw new GrupoAjenoException;
            }

            if ($this->inscripciones->existentes([$edicion->estudianteId], [$grupo->id]) === []) {
                throw new NoInscritoException;
            }
        }

        if (! $this->padron->existe($edicion->estudianteId)) {
            return false;
        }

        if ($this->padron->documentoDeOtro($edicion->documento, $edicion->estudianteId)) {
            throw new DocumentoEnUsoException;
        }

        $this->padron->editar($edicion);

        return true;
    }
}
