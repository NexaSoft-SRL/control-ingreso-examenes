<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Queries;

use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use App\Modules\Ingreso\Application\Contracts\VentanaPuntoControl;
use App\Modules\Ingreso\Application\DTOs\ResultadoResolucionPuertaData;
use App\Modules\Ingreso\Domain\Exceptions\AccesoPuntoDenegado;
use InvalidArgumentException;

final readonly class ResolverIngresoAutorizadoEnPuerta
{
    public function __construct(
        private ExamenGateway $examenes,
        private AlcancePuntoControl $alcance,
        private VentanaPuntoControl $ventana,
        private ResolverExamenEnPuerta $resolver,
    ) {}

    /**
     * @param  list<int>  $examenesCandidatosIds
     */
    public function ejecutar(
        int $usuarioId,
        int $aulaId,
        array $examenesCandidatosIds,
        string $tipoIdentificador,
        string $identificador,
    ): ResultadoResolucionPuertaData {
        if ($usuarioId <= 0 || $aulaId <= 0) {
            throw new InvalidArgumentException(
                'Operador o aula inválidos.'
            );
        }

        $autorizados = [];
        $tieneAlcance = false;

        foreach (array_unique($examenesCandidatosIds) as $examenId) {
            if ($examenId <= 0) {
                throw new InvalidArgumentException(
                    'Identificador de examen inválido.'
                );
            }

            $examen = $this->examenes->actual($examenId);

            if (
                $examen === null
                || ! in_array($aulaId, $examen->aulas, true)
                || ! $this->alcance->puedeOperar(
                    $usuarioId,
                    $examenId,
                    $aulaId,
                )
            ) {
                continue;
            }

            $tieneAlcance = true;

            if ($this->ventana->estaAbierta($examenId)) {
                $autorizados[] = $examenId;
            }
        }

        if (! $tieneAlcance) {
            throw new AccesoPuntoDenegado(
                'No atiendes esta aula.'
            );
        }

        if ($autorizados === []) {
            throw new InvalidArgumentException(
                'Sin examen a esta hora.'
            );
        }

        return $this->resolver->resolverDetallado(
            $autorizados,
            $aulaId,
            $tipoIdentificador,
            $identificador,
        );
    }
}
