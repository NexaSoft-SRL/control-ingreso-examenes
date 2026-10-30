<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\Queries;

use App\Modules\Reportes\Application\Contracts\ReporteGateway;
use App\Modules\Reportes\Application\DTOs\AlcanceReporte;
use App\Modules\Reportes\Application\DTOs\ExamenReporteData;
use App\Modules\Reportes\Application\DTOs\FilaAsistencia;
use App\Modules\Reportes\Application\DTOs\FilaAusente;
use App\Modules\Reportes\Application\DTOs\FilaIncidencia;
use App\Modules\Reportes\Application\DTOs\FilaIntento;
use App\Modules\Reportes\Application\DTOs\FilaRegistrados;
use App\Modules\Reportes\Application\DTOs\FilaResumenEstudiante;
use App\Modules\Reportes\Application\DTOs\ReporteHorarios;
use App\Modules\Reportes\Application\DTOs\TablaReporte;

/**
 * Los siete reportes. Son consultas: no hay ninguna ruta de reportes que
 * escriba.
 */
final readonly class ConsultarReporte
{
    public function __construct(
        private ReporteGateway $gateway,
    ) {}

    public function validarVolumenExportacion(
        string $tipo,
        AlcanceReporte $alcance
    ): void {
        $this->gateway->validarVolumenExportacion(
            $tipo,
            $alcance,
            TablaReporte::MAX_FILAS_EXPORTACION
        );
    }

    public function horarios(AlcanceReporte $alcance): ReporteHorarios
    {
        return $this->gateway->horarios($alcance);
    }

    /**
     * @return list<FilaRegistrados>
     */
    public function registrados(AlcanceReporte $alcance): array
    {
        return $this->gateway->registrados($alcance);
    }

    /**
     * @return list<FilaIntento>
     */
    public function intentos(AlcanceReporte $alcance): array
    {
        return $this->gateway->intentos($alcance);
    }

    /**
     * @return list<FilaResumenEstudiante>
     */
    public function resumenPorEstudiante(AlcanceReporte $alcance): array
    {
        return $this->gateway->resumenPorEstudiante($alcance);
    }

    /**
     * @return list<FilaIncidencia>
     */
    public function incidenciasDeExamen(int $examenId): array
    {
        return $this->gateway->incidenciasDeExamen($examenId);
    }

    /**
     * @return list<FilaAsistencia>
     */
    public function asistencia(int $examenId): array
    {
        return $this->gateway->asistencia($examenId);
    }

    /**
     * @return list<FilaAusente>
     */
    public function ausentes(int $examenId): array
    {
        return $this->gateway->ausentes($examenId);
    }

    public function examen(int $examenId): ?ExamenReporteData
    {
        return $this->gateway->examen($examenId);
    }

    public function tieneExamenes(AlcanceReporte $alcance): bool
    {
        return $this->gateway->contarExamenes($alcance) > 0;
    }

    /**
     * @return array<string, string>
     */
    public function nombresDelAlcance(AlcanceReporte $alcance): array
    {
        return $this->gateway->nombresDelAlcance($alcance);
    }
}
