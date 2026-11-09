<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\Queries;

use App\Modules\Reportes\Application\Contracts\ReporteGateway;
use App\Modules\Reportes\Application\DTOs\FilaMiExamen;

/**
 * Los examenes del docente con su asistencia. Un docente tiene como mucho
 * unas decenas: se leen todos y aqui se filtran por asignatura y se pagina.
 */
final readonly class ListarMisExamenes
{
    public function __construct(
        private ReporteGateway $gateway,
    ) {}

    /**
     * `examenes` y `rendidos` cuentan todos los del periodo, sin el filtro
     * de asignatura; `asignaturas` da el conteo de cada ficha.
     *
     * @return array{
     *     filas: list<FilaMiExamen>,
     *     total: int,
     *     examenes: int,
     *     rendidos: int,
     *     asignaturas: list<array{id: int, nombre: string, examenes: int}>,
     * }
     */
    public function execute(
        int $usuarioId,
        ?int $periodoId,
        ?int $asignaturaId,
        int $pagina,
        int $porPagina,
    ): array {
        $todos = $this->gateway->examenesDelDocente($usuarioId, $periodoId);

        $asignaturas = [];
        $rendidos = 0;

        foreach ($todos as $examen) {
            $asignaturas[$examen->asignaturaId] ??= [
                'id' => $examen->asignaturaId,
                'nombre' => $examen->asignatura,
                'examenes' => 0,
            ];
            $asignaturas[$examen->asignaturaId]['examenes']++;

            if ($examen->rendido) {
                $rendidos++;
            }
        }

        $filtrados = $asignaturaId === null
            ? $todos
            : array_values(array_filter(
                $todos,
                static fn (FilaMiExamen $examen): bool => $examen->asignaturaId === $asignaturaId,
            ));

        return [
            'filas' => array_slice($filtrados, ($pagina - 1) * $porPagina, $porPagina),
            'total' => count($filtrados),
            'examenes' => count($todos),
            'rendidos' => $rendidos,
            'asignaturas' => array_values($asignaturas),
        ];
    }
}
