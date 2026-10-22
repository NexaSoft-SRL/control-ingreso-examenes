<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Application\Queries;

use App\Modules\Examenes\Application\Contracts\ConsultaExamenGateway;
use App\Modules\Examenes\Application\DTOs\ExamenResumenData;
use App\Modules\Ingreso\Application\Contracts\VentanaPuntoControl;
use App\Modules\Monitoreo\Application\DTOs\ExamenEnCursoData;

/**
 * El selector de «En curso»: los examenes del docente (los que registro y
 * los de sus grupos incluidos) de hoy y del proximo dia con examen.
 */
final readonly class ListarEnCurso
{
    public function __construct(
        private ConsultaExamenGateway $consulta,
        private VentanaPuntoControl $ventana,
    ) {}

    /**
     * @return list<ExamenEnCursoData>
     */
    public function ejecutar(int $usuarioId): array
    {
        $hoy = $this->consulta->hoy();
        $proximo = null;
        $lista = [];

        foreach ($this->consulta->listar($usuarioId, null) as $examen) {
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
