<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Application\Contracts;

use App\Modules\Monitoreo\Application\DTOs\AulaEnCursoData;
use App\Modules\Monitoreo\Application\DTOs\AulaSeguidaData;
use App\Modules\Monitoreo\Application\DTOs\CifrasEnCursoData;
use App\Modules\Monitoreo\Application\DTOs\ConsultaAsistenciaData;
use App\Modules\Monitoreo\Application\DTOs\ExamenEnCursoData;
use App\Modules\Monitoreo\Application\DTOs\ExamenSeguidoData;
use App\Modules\Monitoreo\Application\DTOs\PaginaAsistenciaData;

/**
 * El seguimiento solo lee: no tiene tablas propias ni escribe en las de
 * nadie. La hora con la que mide «abierto» y «hoy» es la del servidor.
 */
interface SeguimientoGateway
{
    public function examen(int $examenId): ?ExamenSeguidoData;

    /**
     * Los examenes de hoy y los del proximo dia con examen en los que la
     * cuenta es docente del examen (todas sus aulas) o atiende un aula
     * (solo las suyas).
     *
     * @return list<ExamenEnCursoData>
     */
    public function enCurso(int $usuarioId): array;

    public function cifras(int $examenId): CifrasEnCursoData;

    /**
     * @return list<AulaEnCursoData>
     */
    public function aulas(int $examenId, int $usuarioId): array;

    /**
     * Null si el aula no es de ese examen.
     */
    public function aulaDelExamen(int $examenId, int $aulaId): ?AulaSeguidaData;

    public function asistencia(ConsultaAsistenciaData $consulta): PaginaAsistenciaData;
}
