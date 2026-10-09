<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Queries;

use App\Modules\Academico\Application\Contracts\PeriodoGateway;
use App\Modules\Examenes\Application\Contracts\ConsultaExamenGateway;
use App\Modules\Examenes\Application\DTOs\ListadoExamenesData;

/**
 * Los examenes del docente: los que registro y los que incluyen un grupo
 * suyo. Sin periodo, los de los periodos vigentes.
 */
final readonly class ListarExamenesDelDocente
{
    public function __construct(
        private ConsultaExamenGateway $consulta,
        private PeriodoGateway $periodos,
    ) {}

    public function execute(int $usuarioId, ?string $periodo): ListadoExamenesData
    {
        if ($periodo !== null) {
            $pedido = $this->consulta->periodo($periodo);
            $periodoIds = $pedido === null ? [] : [$pedido['id']];
            $codigo = $pedido['codigo'] ?? null;
        } else {
            $periodoIds = [];

            foreach ($this->periodos->vigentes() as $vigente) {
                $periodoIds[] = $vigente->id;
            }

            $codigo = $this->periodos->principal()?->codigo;
        }

        return new ListadoExamenesData(
            examenes: $periodoIds === [] ? [] : $this->consulta->listar($usuarioId, $periodoIds),
            periodo: $codigo,
            hoy: $this->consulta->hoy(),
            horaServidor: $this->consulta->horaServidor(),
        );
    }
}
