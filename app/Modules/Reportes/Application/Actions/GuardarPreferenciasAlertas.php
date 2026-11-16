<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\Actions;

use App\Modules\Reportes\Application\Contracts\AlertaReporteGateway;

final class GuardarPreferenciasAlertas
{
    public function __construct(private readonly AlertaReporteGateway $gateway) {}

    /**
     * @return array{asistencia_minima:int, incidencias_minimas:int}
     */
    public function execute(int $usuarioId, int $asistenciaMinima, int $incidenciasMinimas): array
    {
        $preferencias = $this->gateway->guardarPreferencias(
            $usuarioId,
            $asistenciaMinima,
            $incidenciasMinimas,
        );

        return [
            'asistencia_minima' => $preferencias->asistenciaMinima,
            'incidencias_minimas' => $preferencias->incidenciasMinimas,
        ];
    }
}
