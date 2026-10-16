<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Queries;

use App\Modules\Ingreso\Application\Contracts\EstadoExpulsionGateway;
use App\Modules\Ingreso\Application\Contracts\IngresoGateway;
use App\Modules\Ingreso\Application\DTOs\ResultadoResolucionPuertaData;
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

        $coincidencias = [];

        foreach (array_unique($examenesAbiertosIds) as $examenId) {
            if (! is_int($examenId) || $examenId <= 0) {
                throw new InvalidArgumentException(
                    'Identificador de examen inválido.'
                );
            }

            $candidato = $this->candidatos->consultar(
                $examenId,
                $tipoIdentificador,
                $identificador,
            );

            if ($candidato === null || ! $candidato->inscrito) {
                continue;
            }

            $estudianteId = $candidato->estudiante->id;

            $veredicto = $this->evaluador->evaluar(
                inscrito: true,
                habilitado: $candidato->habilitacion?->habilitado,
                aulaAsignadaId: $candidato->habilitacion?->aulaId,
                aulaControlId: $aulaId,
                yaIngreso: $this->ingresos->yaIngreso(
                    $examenId,
                    $estudianteId,
                ),
                expulsado: $this->expulsiones->estaExpulsado(
                    $examenId,
                    $estudianteId,
                ),
            );

            $coincidencias[] = new ResultadoResolucionPuertaData(
                $examenId,
                $veredicto,
            );
        }

        foreach ($coincidencias as $resultado) {
            if ($resultado->puedeAdmitir()) {
                return $resultado;
            }
        }

        if (count($coincidencias) === 1) {
            return $coincidencias[0];
        }

        return new ResultadoResolucionPuertaData(
            null,
            null,
            count($coincidencias) > 1,
        );
    }
}
