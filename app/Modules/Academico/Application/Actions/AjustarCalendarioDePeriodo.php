<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Actions;

use App\Modules\Academico\Application\Contracts\AjustePeriodoGateway;
use App\Modules\Academico\Application\Contracts\ConsultaOfertaGateway;

/**
 * El administrador abre o mueve las ventanas del calendario de examenes de
 * un periodo (primeros parciales, segundos parciales...): dentro de ellas
 * los docentes pueden registrar examenes de ese tipo.
 */
final readonly class AjustarCalendarioDePeriodo
{
    public function __construct(
        private AjustePeriodoGateway $ajuste,
        private ConsultaOfertaGateway $oferta,
    ) {}

    /**
     * La fila del periodo ya ajustada, o null si no existe.
     *
     * @param  array<string, array{0: string, 1: string}>  $ventanas
     * @return array<string, mixed>|null
     */
    public function execute(int $periodoId, array $ventanas, ?int $autorId): ?array
    {
        if (! $this->ajuste->ajustarCalendario($periodoId, $ventanas, $autorId)) {
            return null;
        }

        return $this->oferta->periodo($periodoId);
    }
}
