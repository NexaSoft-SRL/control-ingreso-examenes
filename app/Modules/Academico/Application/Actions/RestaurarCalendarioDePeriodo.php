<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Actions;

use App\Modules\Academico\Application\Contracts\AjustePeriodoGateway;
use App\Modules\Academico\Application\Contracts\ConsultaOfertaGateway;
use App\Modules\Academico\Application\Contracts\FuenteCalendarioGateway;
use App\Modules\Academico\Application\DTOs\CalendarioData;
use App\Modules\Academico\Domain\Exceptions\CalendarioDeLaUniversidadNoDisponibleException;

/**
 * Devuelve un periodo a lo que publica la universidad: las fechas y las
 * ventanas del calendario vuelven a las de la fuente y la deteccion
 * puede volver a actualizarlas. Lo ajustado a mano se pierde.
 */
final readonly class RestaurarCalendarioDePeriodo
{
    public function __construct(
        private AjustePeriodoGateway $ajuste,
        private ConsultaOfertaGateway $oferta,
        private FuenteCalendarioGateway $fuente,
    ) {}

    /**
     * La fila del periodo ya restaurada, o null si no existe.
     *
     * @return array<string, mixed>|null
     *
     * @throws CalendarioDeLaUniversidadNoDisponibleException
     */
    public function execute(int $periodoId, ?int $autorId): ?array
    {
        $fila = $this->oferta->periodo($periodoId);

        if ($fila === null) {
            return null;
        }

        $codigo = is_string($fila['codigo'] ?? null) ? $fila['codigo'] : '';
        $calendario = $this->calendarioDe($codigo);

        if ($calendario === null) {
            throw new CalendarioDeLaUniversidadNoDisponibleException;
        }

        if (! $this->ajuste->restaurarCalendario($periodoId, $calendario, $autorId)) {
            return null;
        }

        return $this->oferta->periodo($periodoId);
    }

    /**
     * El calendario publicado del periodo con algo que restaurar: de varios
     * del mismo periodo vale el primero que trae fechas (como en la deteccion).
     */
    private function calendarioDe(string $codigo): ?CalendarioData
    {
        $elegido = null;

        foreach ($this->fuente->calendarios() as $calendario) {
            if ($calendario->periodo !== $codigo) {
                continue;
            }

            $trae = $calendario->inicio !== null && $calendario->fin !== null;

            if ($elegido === null || (($elegido->inicio === null || $elegido->fin === null) && $trae)) {
                $elegido = $calendario;
            }
        }

        if ($elegido === null) {
            return null;
        }

        $traeFechas = $elegido->inicio !== null && $elegido->fin !== null;

        return $traeFechas || $elegido->ventanas !== [] ? $elegido : null;
    }
}
