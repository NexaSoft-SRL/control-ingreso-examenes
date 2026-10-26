<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Application\Queries;

use App\Modules\Examenes\Application\Contracts\ConsultaExamenGateway;
use App\Modules\Examenes\Application\DTOs\ExamenResumenData;
use App\Modules\Ingreso\Application\Contracts\SeguimientoAulaGateway;
use App\Modules\Ingreso\Application\Contracts\VentanaPuntoControl;
use App\Modules\Monitoreo\Application\DTOs\ExamenEnCursoData;
use LogicException;

/**
 * El selector de «En curso»: los examenes del docente (los que registro y
 * los de sus grupos incluidos) de hoy y del proximo dia con examen.
 */
final readonly class ListarEnCurso
{
    public function __construct(
        private ConsultaExamenGateway $consulta,
        private VentanaPuntoControl $ventana,
        private SeguimientoAulaGateway $seguimiento,
    ) {}

    /**
     * @return list<ExamenEnCursoData>
     */
    public function ejecutar(int $usuarioId): array
    {
        $hoy = $this->consulta->hoy();
        $proximo = null;
        $lista = [];
        $porId = [];

        foreach ($this->consulta->listar($usuarioId, null) as $examen) {
            $porId[$examen->id] = $examen;
        }

        foreach ($this->seguimiento->aulasDelUsuario($usuarioId) as $aula) {
            $examenId = $aula['examen_id'] ?? null;

            if (! is_int($examenId)) {
                throw new LogicException('La lista de aulas no contiene un identificador de examen válido.');
            }

            if (isset($porId[$examenId])) {
                continue;
            }

            $detalle = $this->consulta->detalle($examenId, $usuarioId);

            if ($detalle === null) {
                throw new LogicException('El examen asignado no se pudo leer.');
            }

            $porId[$examenId] = $detalle->resumen;
        }

        $examenes = array_values($porId);
        usort(
            $examenes,
            static fn (ExamenResumenData $a, ExamenResumenData $b): int => [$a->fecha, $a->hora, $a->id]
                <=> [$b->fecha, $b->hora, $b->id],
        );

        foreach ($examenes as $examen) {
            if ($examen->fecha < $hoy) {
                continue;
            }

            if ($examen->fecha !== $hoy) {
                $proximo ??= $examen->fecha;

                if ($examen->fecha !== $proximo) {
                    break;
                }
            }

            $lista[] = $this->comoEnCurso($examen);
        }

        return $lista;
    }

    private function comoEnCurso(ExamenResumenData $examen): ExamenEnCursoData
    {
        return new ExamenEnCursoData(
            id: $examen->id,
            fecha: $examen->fecha,
            hora: $examen->hora,
            duracion: $examen->duracion,
            tipo: $examen->tipo,
            tipoTexto: $examen->tipoTexto,
            asignatura: $examen->asignaturaNombre,
            abierto: $this->ventana->estaAbierta($examen->id),
        );
    }
}
