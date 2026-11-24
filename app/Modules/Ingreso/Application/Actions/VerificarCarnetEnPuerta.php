<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Ingreso\Application\Authorization\AlcancePuesto;
use App\Modules\Ingreso\Application\Contracts\IncidenciaGateway;
use App\Modules\Ingreso\Application\Contracts\PuertaGateway;
use App\Modules\Ingreso\Application\DTOs\AulaData;
use App\Modules\Ingreso\Application\DTOs\NuevaIncidenciaData;
use App\Modules\Ingreso\Application\DTOs\OperadorData;
use App\Modules\Ingreso\Domain\Enums\OrigenIncidencia;
use App\Modules\Ingreso\Domain\Enums\TipoIncidencia;
use App\Modules\Ingreso\Domain\Exceptions\DatoNoValidoException;
use App\Modules\Ingreso\Domain\Exceptions\FueraDeAlcanceException;

/**
 * Quien controla la puerta le pide el carnet a quien llega y lo compara con
 * el que escribio el estudiante. Si no coincide se asienta una suplantacion;
 * si el estudiante no escribio su carnet, no hay con que comparar. El
 * carnet guardado nunca sale de aqui.
 */
final readonly class VerificarCarnetEnPuerta
{
    public function __construct(
        private PuertaGateway $puerta,
        private IncidenciaGateway $incidencias,
        private AlcancePuesto $alcance,
    ) {}

    /**
     * @return array{coincide: bool|null, sin_carnet: bool, incidencia: array{id: int}|null}
     *
     * @throws FueraDeAlcanceException
     * @throws DatoNoValidoException
     */
    public function execute(
        AulaData $aula,
        OperadorData $operador,
        int $estudianteId,
        int $examenId,
        string $presentado,
    ): array {
        $delDia = $this->puerta->examenesDelDia($aula->id);

        if (! $this->alcance->atiendeAula($operador, $aula->id, $delDia)) {
            throw FueraDeAlcanceException::aula();
        }

        if ($this->puerta->examenesDondeEstaInscrito($estudianteId, [$examenId]) === []) {
            throw new DatoNoValidoException('estudiante', 'No es de este examen.');
        }

        $guardado = $this->puerta->documentoDe($estudianteId);

        if ($guardado === null) {
            return ['coincide' => null, 'sin_carnet' => true, 'incidencia' => null];
        }

        // Un carnet guardado antes de la regla «solo numeros» puede llevar su
        // complemento («5003748-1A»): el numero presentado lo identifica igual.
        $numero = explode(' ', (string) preg_replace('/[\s\-]+/u', ' ', trim($guardado)), 2)[0];

        if ($this->normalizar($guardado) === $this->normalizar($presentado)
            || $numero === $this->normalizar($presentado)) {
            return ['coincide' => true, 'sin_carnet' => false, 'incidencia' => null];
        }

        $incidencia = $this->incidencias->registrar(new NuevaIncidenciaData(
            tipo: TipoIncidencia::Suplantacion,
            origen: OrigenIncidencia::Persona,
            detalle: 'El carnet que presentó no coincide con el del estudiante.',
            operador: $operador,
            examenId: $examenId,
            aulaId: $aula->id,
            estudianteId: $estudianteId,
        ));

        return [
            'coincide' => false,
            'sin_carnet' => false,
            'incidencia' => $incidencia === null ? null : ['id' => $incidencia],
        ];
    }

    private function normalizar(string $documento): string
    {
        return mb_strtoupper((string) preg_replace('/[\s\-.]+/u', '', $documento));
    }
}
