<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Queries;

use App\Modules\Estudiantes\Application\DTOs\EstudianteData;
use App\Modules\Ingreso\Application\Contracts\EstadoExpulsionGateway;
use App\Modules\Ingreso\Application\Contracts\IngresoGateway;
use App\Modules\Ingreso\Application\DTOs\ResultadoResolucionPuertaData;
use App\Modules\Ingreso\Domain\Enums\CausaIntento;
use App\Modules\Ingreso\Domain\Rules\EvaluarIngreso;
use InvalidArgumentException;

final readonly class ResolverExamenEnPuerta
{
    public function __construct(
        private ConsultarCandidato $candidatos,
        private EvaluarIngreso $evaluador,
        private IngresoGateway $ingresos,
        private EstadoExpulsionGateway $expulsiones,
    ) {}

    /**
     * @param  list<int>  $examenesAbiertosIds
     */
    public function resolverManual(
        array $examenesAbiertosIds,
        int $aulaId,
        string $tipoIdentificador,
        string $identificador,
    ): ?int {
        return $this->resolverDetallado(
            $examenesAbiertosIds,
            $aulaId,
            $tipoIdentificador,
            $identificador,
        )->examenId;
    }

    /**
     * La lista de exámenes debe provenir de un contexto autorizado
     * y validado por el servidor, nunca del cliente HTTP.
     *
     * @param  list<int>  $examenesAbiertosIds
     */
    public function resolverDetallado(
        array $examenesAbiertosIds,
        int $aulaId,
        string $tipoIdentificador,
        string $identificador,
    ): ResultadoResolucionPuertaData {
        if ($aulaId <= 0 || $examenesAbiertosIds === []) {
            throw new InvalidArgumentException(
                'La puerta requiere un aula y exámenes abiertos.'
            );
        }

        if (! in_array($tipoIdentificador, ['codigo', 'documento'], true)) {
            throw new InvalidArgumentException(
                'Tipo de identificación inválido.'
            );
        }

        $mejor = null;
        $menor = PHP_INT_MAX;
        $enPadron = null;

        foreach (array_unique($examenesAbiertosIds) as $examenId) {
            if ($examenId <= 0) {
                throw new InvalidArgumentException(
                    'Identificador de examen inválido.'
                );
            }

            $candidato = $this->candidatos->consultar(
                $examenId,
                $tipoIdentificador,
                $identificador,
            );

            if ($candidato === null) {
                continue;
            }

            if (! $candidato->inscrito) {
                $enPadron ??= $candidato->estudiante;

                continue;
            }

            $estudianteId = $candidato->estudiante->id;
            $yaIngreso = $this->ingresos->yaIngreso($examenId, $estudianteId);
            $expulsado = $this->expulsiones->estaExpulsado($examenId, $estudianteId);

            $veredicto = $this->evaluador->evaluar(
                inscrito: true,
                habilitado: $candidato->habilitacion?->habilitado,
                yaIngreso: $yaIngreso,
                expulsado: $expulsado,
            );

            // En mas de un examen del aula a la vez: el que puede
            // admitirlo (sin ingreso, habilitado y con esta aula) y, si
            // no, el que mas se le acerque.
            $puesto = $this->evaluador->puesto(
                $yaIngreso || $expulsado,
                $candidato->habilitacion?->habilitado,
                $candidato->habilitacion?->aulaId,
                $aulaId,
            );

            if ($puesto < $menor) {
                $menor = $puesto;
                $mejor = new ResultadoResolucionPuertaData(
                    $examenId,
                    $veredicto,
                    candidato: $candidato,
                );
            }
        }

        if ($mejor !== null) {
            return $mejor;
        }

        // Nadie lo espera en el aula: distinguir a quien esta en el padron
        // (no inscrito en estos examenes) de un codigo que no existe.
        return $this->sinExamen($enPadron);
    }

    private function sinExamen(
        ?EstudianteData $estudiante,
    ): ResultadoResolucionPuertaData {
        return new ResultadoResolucionPuertaData(
            null,
            null,
            causa: $estudiante === null
                ? CausaIntento::NoRegistrado
                : CausaIntento::NoInscrito,
            estudiante: $estudiante,
        );
    }
}
