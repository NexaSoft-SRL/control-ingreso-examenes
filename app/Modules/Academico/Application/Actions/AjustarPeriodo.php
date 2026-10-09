<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Actions;

use App\Modules\Academico\Application\Contracts\AjustePeriodoGateway;
use App\Modules\Academico\Application\Contracts\ConsultaOfertaGateway;

/**
 * Ajusta a mano las fechas de un periodo (los que la fuente deja sin
 * fechas, como el anual).
 */
final readonly class AjustarPeriodo
{
    public function __construct(
        private AjustePeriodoGateway $ajuste,
        private ConsultaOfertaGateway $oferta,
    ) {}

    /**
     * Devuelve la fila del periodo ya ajustado, o null si no existe.
     *
     * @return array<string, mixed>|null
     */
    public function execute(int $periodoId, string $fechaInicio, string $fechaFin, ?int $autorId): ?array
    {
        if (! $this->ajuste->ajustar($periodoId, $fechaInicio, $fechaFin, $autorId)) {
            return null;
        }

        return $this->oferta->periodo($periodoId);
    }
}
