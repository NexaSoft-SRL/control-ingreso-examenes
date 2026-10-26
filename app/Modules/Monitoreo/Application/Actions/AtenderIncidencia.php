<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Application\Actions;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Ingreso\Application\Contracts\SeguimientoAulaGateway;
use App\Modules\Monitoreo\Application\Contracts\EnCursoGateway;
use App\Modules\Monitoreo\Application\DTOs\IncidenciaEnCursoData;
use App\Modules\Monitoreo\Domain\Exceptions\ExamenAjenoException;
use App\Modules\Monitoreo\Domain\Exceptions\IncidenciaNoEncontradaException;
use App\Modules\Monitoreo\Domain\Exceptions\IncidenciaYaAtendidaException;
use LogicException;

/**
 * El docente del examen, o quien atiende el aula de la incidencia (HU-18),
 * marca una incidencia como atendida. Deja quien y
 * cuando, se asienta en la bitacora y no se deshace: la marca la escribe
 * el seguimiento del aula (HU-21), que solo la pone si aun no la tiene.
 */
final readonly class AtenderIncidencia
{
    public function __construct(
        private AlcanceExamenGateway $alcance,
        private SeguimientoAulaGateway $seguimiento,
        private EnCursoGateway $enCurso,
    ) {}

    public function ejecutar(int $usuarioId, int $examenId, int $incidenciaId): IncidenciaEnCursoData
    {
        $esDocente = $this->alcance->esDocenteDelExamen($usuarioId, $examenId);
        $mias = $esDocente ? [] : $this->alcance->aulasQueAtiende($usuarioId, $examenId);

        if (! $esDocente && $mias === []) {
            throw new ExamenAjenoException('Este examen no es suyo.');
        }

        $ubicacion = $this->seguimiento->ubicacionIncidencia($incidenciaId);

        if ($ubicacion === null || $ubicacion['examen_id'] !== $examenId) {
            throw new IncidenciaNoEncontradaException('Incidencia no encontrada.');
        }

        if (! $esDocente && ! in_array($ubicacion['aula_id'], $mias, true)) {
            throw new ExamenAjenoException('Solo atiende las incidencias de sus aulas.');
        }

        if (! $this->seguimiento->marcarIncidenciaAtendida($incidenciaId, $usuarioId)) {
            throw new IncidenciaYaAtendidaException('La incidencia ya estaba atendida.');
        }

        return $this->enCurso->incidencia($incidenciaId)
            ?? throw new LogicException('La incidencia atendida no se pudo leer.');
    }
}
