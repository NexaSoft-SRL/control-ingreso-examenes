<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Actions;

use App\Modules\Academico\Application\Contracts\AlcanceDocenteGateway;
use App\Modules\Estudiantes\Application\Contracts\InscripcionGateway;
use App\Modules\Estudiantes\Application\DTOs\BajaDeInscritoData;
use App\Modules\Estudiantes\Domain\Exceptions\GrupoAjenoException;
use App\Modules\Estudiantes\Domain\Exceptions\InscritoConIngresoException;
use App\Modules\Estudiantes\Domain\Exceptions\NoInscritoException;
use App\Modules\Estudiantes\Domain\Exceptions\PeriodoCerradoException;

/**
 * El docente quita a un inscrito de su grupo con un motivo. La inscripcion
 * no se borra: queda dada de baja y deja de contar en todo el sistema.
 * Quien ya ingreso a un examen del grupo no se puede quitar.
 */
final readonly class QuitarInscrito
{
    public function __construct(
        private InscripcionGateway $inscripciones,
        private AlcanceDocenteGateway $alcance,
    ) {}

    /**
     * @return bool false si el grupo no existe
     *
     * @throws GrupoAjenoException
     * @throws PeriodoCerradoException
     * @throws NoInscritoException
     * @throws InscritoConIngresoException
     */
    public function execute(BajaDeInscritoData $baja): bool
    {
        $grupo = $this->inscripciones->grupo($baja->grupoId);

        if ($grupo === null) {
            return false;
        }

        if (! $this->alcance->esGrupoDelDocente($baja->usuarioId, $grupo->id)) {
            throw new GrupoAjenoException;
        }

        if (! $grupo->periodoVigente) {
            throw new PeriodoCerradoException;
        }

        if ($this->inscripciones->existentes([$baja->estudianteId], [$grupo->id]) === []) {
            throw new NoInscritoException;
        }

        if ($this->inscripciones->tieneIngresoEnGrupo($baja->estudianteId, $grupo->id)) {
            throw new InscritoConIngresoException;
        }

        $this->inscripciones->darDeBaja($baja);

        return true;
    }
}
