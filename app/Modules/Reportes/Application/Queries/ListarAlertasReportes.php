<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\Queries;

use App\Modules\Reportes\Application\Contracts\AlertaReporteGateway;
use App\Modules\Reportes\Application\DTOs\AlertaReporteData;

final class ListarAlertasReportes
{
    public function __construct(private readonly AlertaReporteGateway $gateway) {}

    /**
     * @return array{preferencias: array{asistencia_minima:int, incidencias_minimas:int}, alertas:list<array<string, mixed>>}
     */
    public function execute(int $usuarioId): array
    {
        $preferencias = $this->gateway->preferencias($usuarioId);
        $alertas = $this->gateway->alertas($usuarioId);

        return [
            'preferencias' => [
                'asistencia_minima' => $preferencias->asistenciaMinima,
                'incidencias_minimas' => $preferencias->incidenciasMinimas,
            ],
            'alertas' => array_map(
                static fn (AlertaReporteData $alerta): array => [
                    'id' => $alerta->id,
                    'examen_id' => $alerta->examenId,
                    'asignatura' => $alerta->asignatura,
                    'tipo_examen' => $alerta->tipoExamen,
                    'fecha' => $alerta->fecha,
                    'tipo' => $alerta->tipo,
                    'titulo' => $alerta->titulo,
                    'mensaje' => $alerta->mensaje,
                    'correo_estado' => $alerta->correoEstado,
                    'leida_en' => $alerta->leidaEn,
                    'creada_en' => $alerta->creadaEn,
                ],
                $alertas,
            ),
        ];
    }
}
