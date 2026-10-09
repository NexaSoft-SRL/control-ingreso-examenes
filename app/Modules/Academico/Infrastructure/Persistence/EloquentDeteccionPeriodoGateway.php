<?php

declare(strict_types=1);

namespace App\Modules\Academico\Infrastructure\Persistence;

use App\Modules\Academico\Application\Contracts\DeteccionPeriodoGateway;
use App\Modules\Academico\Application\DTOs\CalendarioData;
use App\Modules\Academico\Application\DTOs\PeriodoData;
use App\Modules\Academico\Domain\Enums\TipoPeriodo;
use App\Modules\Academico\Domain\Models\Periodo;

final class EloquentDeteccionPeriodoGateway implements DeteccionPeriodoGateway
{
    public function registrar(
        string $codigo,
        int $anio,
        int $numero,
        TipoPeriodo $tipo,
        ?CalendarioData $calendario,
    ): bool {
        $periodo = Periodo::where('codigo', $codigo)->first();
        $creado = false;

        if (! $periodo instanceof Periodo) {
            $periodo = new Periodo(['codigo' => $codigo]);
            $creado = true;
        }

        $periodo->anio = $anio;
        $periodo->numero = $numero;
        $periodo->tipo = $tipo;

        // Lo que alguien ajusto a mano manda sobre la fuente.
        if ($calendario !== null && $periodo->ajustado_por === null) {
            if ($calendario->inicio !== null && $calendario->fin !== null) {
                $periodo->setAttribute('fecha_inicio', $calendario->inicio);
                $periodo->setAttribute('fecha_fin', $calendario->fin);
                $periodo->fuente = mb_substr($calendario->fuente, 0, 120);
            }

            if ($calendario->ventanas !== []) {
                $periodo->ventanas = $calendario->ventanas;
            }
        }

        $periodo->save();

        return $creado;
    }

    public function asegurar(string $codigo, int $anio, int $numero, TipoPeriodo $tipo): int
    {
        return Periodo::firstOrCreate(
            ['codigo' => $codigo],
            ['anio' => $anio, 'numero' => $numero, 'tipo' => $tipo],
        )->id;
    }

    /**
     * @return list<PeriodoData>
     */
    public function todos(): array
    {
        $periodos = [];

        foreach (Periodo::orderByDesc('anio')->orderByDesc('numero')->get() as $periodo) {
            $ventanas = [];

            foreach ($periodo->ventanas ?? [] as $nombre => $rango) {
                if (is_string($nombre)) {
                    $ventanas[$nombre] = $rango;
                }
            }

            $periodos[] = new PeriodoData(
                id: $periodo->id,
                codigo: $periodo->codigo,
                tipo: $periodo->tipo->value,
                tipoEtiqueta: $periodo->tipo->etiqueta(),
                fechaInicio: $periodo->fecha_inicio?->toDateString(),
                fechaFin: $periodo->fecha_fin?->toDateString(),
                ventanas: $ventanas,
            );
        }

        return $periodos;
    }
}
