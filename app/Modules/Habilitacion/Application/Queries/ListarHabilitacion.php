<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Application\Queries;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Habilitacion\Application\Contracts\HabilitacionGateway;
use App\Modules\Habilitacion\Application\Contracts\RepartoGateway;
use App\Modules\Habilitacion\Application\DTOs\FiltroHabilitacionData;
use App\Modules\Habilitacion\Application\DTOs\ListadoHabilitacionData;
use App\Modules\Habilitacion\Domain\Exceptions\ExamenAjenoException;

final readonly class ListarHabilitacion
{
    public function __construct(
        private HabilitacionGateway $habilitaciones,
        private RepartoGateway $reparto,
        private AlcanceExamenGateway $alcance,
    ) {}

    /**
     * @throws ExamenAjenoException
     */
    public function execute(
        int $examenId,
        FiltroHabilitacionData $filtros,
        int $pagina,
        int $porPagina,
        int $usuarioId,
    ): ListadoHabilitacionData {
        if (! $this->alcance->esDocenteDelExamen($usuarioId, $examenId)) {
            throw new ExamenAjenoException;
        }

        $condiciones = $this->habilitaciones->condiciones($examenId, $filtros);

        return new ListadoHabilitacionData(
            filas: $this->habilitaciones->listar($examenId, $filtros, $pagina, $porPagina),
            total: $condiciones[$filtros->condicion ?? 'todos'] ?? $condiciones['todos'],
            pagina: $pagina,
            porPagina: $porPagina,
            cifras: $this->habilitaciones->cifras($examenId),
            porAula: $this->reparto->porAula($examenId),
            condiciones: $condiciones,
            grupos: $this->habilitaciones->gruposDe($examenId, $usuarioId),
        );
    }
}
