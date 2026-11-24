<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Queries;

use App\Modules\Examenes\Application\Contracts\ConsultaExamenGateway;
use App\Modules\Examenes\Application\DTOs\ChoquesDeEstudiantesData;

/**
 * Aviso del asistente: cuantos estudiantes de los grupos elegidos ya tienen
 * otro examen que se solapa con este. No impide registrar: el docente puede
 * tomarles otro examen aparte.
 */
final readonly class ConsultarChoquesDeEstudiantes
{
    // Cuantos estudiantes se listan; el total cuenta a todos.
    public const MAXIMO = 50;

    public function __construct(
        private ConsultaExamenGateway $consulta,
    ) {}

    /**
     * @param  list<int>  $grupoIds
     */
    public function execute(
        array $grupoIds,
        string $fecha,
        string $horaInicio,
        int $duracionMinutos,
        ?int $exceptoExamenId,
    ): ChoquesDeEstudiantesData {
        $estudiantes = $this->consulta->estudiantesConOtroExamen(
            $grupoIds,
            $fecha,
            $horaInicio,
            $duracionMinutos,
            $exceptoExamenId,
        );

        return new ChoquesDeEstudiantesData(
            total: count($estudiantes),
            estudiantes: array_map(
                static fn (array $estudiante): array => [
                    'nombre' => $estudiante['nombre'],
                    'codigo' => $estudiante['codigo'],
                    'examenes' => $estudiante['examenes'],
                ],
                array_slice($estudiantes, 0, self::MAXIMO),
            ),
        );
    }
}
