<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Queries;

use App\Modules\Ingreso\Application\Contracts\EstadoExpulsionGateway;
use App\Modules\Ingreso\Application\Contracts\IngresoGateway;
use App\Modules\Ingreso\Domain\Enums\VeredictoIngreso;
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
     * @param  list<int>  $examenesAbiertosIds  Exámenes abiertos,
     *                                          autorizados y asociados al aula.
     */
    public function resolverManual(
        array $examenesAbiertosIds,
        int $aulaId,
        string $tipoIdentificador,
        string $identificador,
    ): ?int {
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

            $coincidencias[] = [
                'examen' => $examenId,
                'veredicto' => $veredicto,
            ];
        }

        foreach ($coincidencias as $coincidencia) {
            if ($coincidencia['veredicto'] === VeredictoIngreso::Admitido) {
                return $coincidencia['examen'];
            }
        }

        // Si pertenece a un único examen, conservar su veredicto
        // aunque no pueda ser admitido.
        if (count($coincidencias) === 1) {
            return $coincidencias[0]['examen'];
        }

        // No decidir arbitrariamente entre varios exámenes rechazados.
        return null;
    }
}
