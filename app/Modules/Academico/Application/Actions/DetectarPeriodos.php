<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Actions;

use App\Modules\Academico\Application\Contracts\DeteccionPeriodoGateway;
use App\Modules\Academico\Application\Contracts\FuenteCalendarioGateway;
use App\Modules\Academico\Application\Contracts\FuenteOfertaGateway;
use App\Modules\Academico\Application\DTOs\CalendarioData;
use App\Modules\Academico\Application\DTOs\DeteccionPeriodosData;
use App\Modules\Academico\Domain\Exceptions\FuenteNoDisponibleException;
use App\Modules\Academico\Domain\Rules\CodigoPeriodo;

/**
 * Detecta los periodos en las fuentes: el que declara cada archivo de
 * oferta, el anual de las carreras anuales y los que tienen calendario
 * publicado. Las fechas salen del calendario; sin el, el periodo queda
 * «sin fechas» hasta que alguien las ajuste.
 */
final readonly class DetectarPeriodos
{
    public function __construct(
        private FuenteOfertaGateway $oferta,
        private FuenteCalendarioGateway $calendario,
        private DeteccionPeriodoGateway $periodos,
    ) {}

    public function execute(): DeteccionPeriodosData
    {
        $calendarios = $this->calendariosPorPeriodo();
        $codigos = [];

        foreach ($this->oferta->facultades() as $facultad) {
            try {
                $oferta = $this->oferta->leer($facultad);
            } catch (FuenteNoDisponibleException) {
                continue;
            }

            $partes = $oferta->periodoCodigo === null ? null : CodigoPeriodo::partes($oferta->periodoCodigo);

            if ($partes === null) {
                continue;
            }

            if ($oferta->tieneCarrerasSemestrales()) {
                $codigos[$partes['numero'].'/'.$partes['anio']] = true;
            }

            if ($oferta->tieneCarrerasAnuales()) {
                $codigos[CodigoPeriodo::anualDe($partes['anio'])] = true;
            }
        }

        foreach (array_keys($calendarios) as $codigo) {
            $codigos[$codigo] = true;
        }

        $creados = 0;
        $actualizados = 0;

        foreach (array_keys($codigos) as $codigo) {
            $partes = CodigoPeriodo::partes($codigo);

            if ($partes === null) {
                continue;
            }

            $creado = $this->periodos->registrar(
                $codigo,
                $partes['anio'],
                $partes['numero'],
                CodigoPeriodo::tipo($partes['numero']),
                $calendarios[$codigo] ?? null,
            );

            if ($creado) {
                $creados++;
            } else {
                $actualizados++;
            }
        }

        return new DeteccionPeriodosData($creados, $actualizados, $this->periodos->todos());
    }

    /**
     * De varios calendarios del mismo periodo vale el primero que trae
     * fechas de inicio y fin.
     *
     * @return array<string, CalendarioData>
     */
    private function calendariosPorPeriodo(): array
    {
        $porPeriodo = [];

        foreach ($this->calendario->calendarios() as $calendario) {
            $actual = $porPeriodo[$calendario->periodo] ?? null;
            $traeFechas = $calendario->inicio !== null && $calendario->fin !== null;

            if ($actual === null || (($actual->inicio === null || $actual->fin === null) && $traeFechas)) {
                $porPeriodo[$calendario->periodo] = $calendario;
            }
        }

        return $porPeriodo;
    }
}
