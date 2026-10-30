<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\Queries;

use App\Modules\Reportes\Application\Contracts\ReporteGateway;
use App\Modules\Reportes\Application\DTOs\AlcanceReporte;
use App\Modules\Reportes\Application\DTOs\FilaExamenReporte;

/**
 * El listado de examenes del administrador. La tabla y los conteos por
 * facultad siguen a los filtros; las cifras de las tarjetas siguen al
 * alcance de la descarga: los filtros o, si se eligio uno, solo ese examen.
 */
final readonly class ListarExamenesParaReporte
{
    public function __construct(
        private ReporteGateway $gateway,
    ) {}

    /**
     * @return array{
     *     filas: list<FilaExamenReporte>,
     *     total: int,
     *     conteos: array<string, int>,
     *     cifras: array{ingresos: int, examenes: int, intentos: int, estudiantes: int},
     * }
     */
    public function execute(AlcanceReporte $alcance, int $pagina, int $porPagina): array
    {
        $listado = $alcance->sinExamen();
        $porFacultad = $this->gateway->examenesPorFacultad($listado->sinFacultad());

        return [
            'filas' => $this->gateway->examenes($listado, $pagina, $porPagina),
            'total' => $this->gateway->contarExamenes($listado),
            'conteos' => [
                'todas' => $this->gateway->contarExamenes($listado->sinFacultad()),
                ...$porFacultad,
            ],
            'cifras' => [
                'ingresos' => $this->gateway->contarIngresos($alcance),
                'examenes' => $this->gateway->contarExamenes($alcance),
                'intentos' => $this->gateway->contarIntentos($alcance),
                'estudiantes' => $this->gateway->contarEstudiantes($alcance),
            ],
        ];
    }
}
