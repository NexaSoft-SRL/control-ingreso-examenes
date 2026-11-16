<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Application\Actions;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Habilitacion\Application\Contracts\HabilitacionGateway;
use App\Modules\Habilitacion\Application\DTOs\CambioHabilitacionData;
use App\Modules\Habilitacion\Application\DTOs\FiltroHabilitacionData;
use App\Modules\Habilitacion\Application\DTOs\ResultadoPorCodigosData;
use App\Modules\Habilitacion\Domain\Exceptions\EstudianteConIngresoException;
use App\Modules\Habilitacion\Domain\Exceptions\ExamenAjenoException;

/**
 * «Habilitar» / «Inhabilitar» desde una lista de codigos universitarios,
 * por ejemplo la de quienes no cumplieron una condicion que el docente
 * armo fuera del sistema. Solo cuentan los inscritos del examen; los demas
 * codigos se devuelven para que el docente los revise.
 */
final readonly class CambiarHabilitacionPorCodigos
{
    public function __construct(
        private HabilitacionGateway $habilitaciones,
        private AlcanceExamenGateway $alcance,
        private CambiarHabilitacion $cambiar,
    ) {}

    /**
     * @param  list<string>  $codigos
     *
     * @throws ExamenAjenoException
     * @throws EstudianteConIngresoException
     */
    public function execute(
        int $examenId,
        array $codigos,
        bool $habilitado,
        ?string $motivo,
        int $usuarioId,
    ): ResultadoPorCodigosData {
        if (! $this->alcance->esDocenteDelExamen($usuarioId, $examenId)) {
            throw new ExamenAjenoException;
        }

        $codigos = array_values(array_unique($codigos));
        $hallados = $this->habilitaciones->estudiantesPorCodigos($examenId, $codigos);
        $noEncontrados = array_values(array_diff($codigos, array_keys($hallados)));

        $cambio = $this->cambiar->execute(
            $examenId,
            new CambioHabilitacionData(
                habilitado: $habilitado,
                motivo: $motivo,
                estudiantes: array_values($hallados),
                todos: false,
                filtros: new FiltroHabilitacionData,
            ),
            $usuarioId,
        );

        return new ResultadoPorCodigosData($cambio, count($hallados), $noEncontrados);
    }
}
