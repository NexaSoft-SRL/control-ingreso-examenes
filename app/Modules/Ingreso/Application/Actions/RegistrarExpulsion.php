<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Ingreso\Application\Authorization\AlcancePuesto;
use App\Modules\Ingreso\Application\Contracts\IncidenciaGateway;
use App\Modules\Ingreso\Application\Contracts\PuertaGateway;
use App\Modules\Ingreso\Application\DTOs\IngresoData;
use App\Modules\Ingreso\Application\DTOs\NuevaIncidenciaData;
use App\Modules\Ingreso\Application\DTOs\OperadorData;
use App\Modules\Ingreso\Domain\Enums\OrigenIncidencia;
use App\Modules\Ingreso\Domain\Enums\TipoIncidencia;
use App\Modules\Ingreso\Domain\Exceptions\FueraDeAlcanceException;
use App\Modules\Ingreso\Domain\Exceptions\YaExpulsadoException;

/**
 * La expulsion es una incidencia sobre un ingreso. El ingreso no se toca;
 * la hora la pone el servidor y se asienta una sola vez.
 */
final readonly class RegistrarExpulsion
{
    public function __construct(
        private PuertaGateway $puerta,
        private IncidenciaGateway $incidencias,
        private AlcancePuesto $alcance,
    ) {}

    public function execute(IngresoData $ingreso, string $motivo, OperadorData $operador): int
    {
        $examen = $this->puerta->examen($ingreso->examenId);

        if ($examen === null || ! $this->alcance->actuaEnExamen($operador, $examen, $ingreso->aulaId)) {
            throw FueraDeAlcanceException::aula();
        }

        if ($ingreso->expulsadoA !== null) {
            throw new YaExpulsadoException;
        }

        $incidenciaId = $this->incidencias->registrar(new NuevaIncidenciaData(
            tipo: TipoIncidencia::Expulsion,
            origen: OrigenIncidencia::Persona,
            detalle: $motivo,
            operador: $operador,
            examenId: $ingreso->examenId,
            aulaId: $ingreso->aulaId,
            estudianteId: $ingreso->estudianteId,
            ingresoId: $ingreso->id,
        ), 'incidencia.expulsion');

        if ($incidenciaId === null) {
            // Dos puestos a la vez: gana el unico por ingreso.
            throw new YaExpulsadoException;
        }

        return $incidenciaId;
    }
}
