<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Application\Queries;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Monitoreo\Application\Contracts\SeguimientoGateway;
use App\Modules\Monitoreo\Application\DTOs\AsistenciaDeAulaData;
use App\Modules\Monitoreo\Application\DTOs\ConsultaAsistenciaData;
use App\Modules\Monitoreo\Domain\Exceptions\AulaFueraDelExamenException;
use App\Modules\Monitoreo\Domain\Exceptions\SinAlcanceException;

/**
 * Quienes faltan y quienes ingresaron en un aula. La ve un docente del
 * examen, o quien atiende esa aula y solo el dia del examen.
 */
final readonly class ConsultarAsistenciaDeAula
{
    public function __construct(
        private SeguimientoGateway $seguimiento,
        private AlcanceExamenGateway $alcance,
    ) {}

    /**
     * Null si el examen no existe.
     *
     * @throws SinAlcanceException
     * @throws AulaFueraDelExamenException
     */
    public function execute(int $usuarioId, ConsultaAsistenciaData $consulta): ?AsistenciaDeAulaData
    {
        $examen = $this->seguimiento->examen($consulta->examenId);

        if ($examen === null) {
            return null;
        }

        if (! $this->alcance->esDocenteDelExamen($usuarioId, $consulta->examenId)) {
            if (! $this->alcance->atiendeAula($usuarioId, $consulta->examenId, $consulta->aulaId)) {
                throw new SinAlcanceException('Esta aula no está a su cargo.');
            }

            if (! $examen->esHoy) {
                throw new SinAlcanceException('El aula solo se sigue el día del examen.');
            }
        }

        $aula = $this->seguimiento->aulaDelExamen($consulta->examenId, $consulta->aulaId);

        if ($aula === null) {
            throw new AulaFueraDelExamenException('Aula no encontrada.');
        }

        return new AsistenciaDeAulaData(
            examen: $examen,
            aula: $aula,
            pagina: $this->seguimiento->asistencia($consulta),
        );
    }
}
