<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Application\Queries;

use App\Modules\Estudiantes\Application\Contracts\FotosDeEstudiantes;
use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Habilitacion\Application\Contracts\HabilitacionGateway;
use App\Modules\Habilitacion\Domain\Exceptions\EstudianteNoInscritoException;
use App\Modules\Habilitacion\Domain\Exceptions\ExamenAjenoException;

/**
 * La foto de un inscrito para la lista de habilitacion: solo la ve el
 * docente del examen y solo de quien rinde ese examen. Sin foto, null.
 */
final readonly class FotoDelInscrito
{
    public function __construct(
        private HabilitacionGateway $habilitaciones,
        private AlcanceExamenGateway $alcance,
        private FotosDeEstudiantes $fotos,
    ) {}

    /**
     * @throws ExamenAjenoException
     * @throws EstudianteNoInscritoException
     */
    public function execute(int $examenId, int $estudianteId, int $usuarioId): ?string
    {
        if (! $this->alcance->esDocenteDelExamen($usuarioId, $examenId)) {
            throw new ExamenAjenoException;
        }

        if ($this->habilitaciones->cuantosNoInscritos($examenId, [$estudianteId]) > 0) {
            throw new EstudianteNoInscritoException(1);
        }

        return $this->fotos->foto($estudianteId);
    }
}
