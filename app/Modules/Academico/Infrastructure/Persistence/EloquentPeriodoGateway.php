<?php

declare(strict_types=1);

namespace App\Modules\Academico\Infrastructure\Persistence;

use App\Modules\Academico\Application\Contracts\PeriodoGateway;
use App\Modules\Academico\Application\DTOs\PeriodoData;
use App\Modules\Academico\Domain\Models\Periodo;

final class EloquentPeriodoGateway implements PeriodoGateway
{
    /**
     * @return list<PeriodoData>
     */
    public function vigentes(): array
    {
        $vigentes = [];

        foreach ($this->periodosVigentes() as $periodo) {
            $vigentes[] = $this->dato($periodo);
        }

        return $vigentes;
    }

    public function principal(): ?PeriodoData
    {
        $principal = null;
        $mayor = -1;

        // Llegan del mas reciente al mas antiguo: ante un empate de grupos
        // gana el mas reciente.
        foreach ($this->periodosVigentes() as $periodo) {
            $grupos = $periodo->getAttribute('grupos_count');
            $grupos = is_int($grupos) ? $grupos : 0;

            if ($grupos > $mayor) {
                $principal = $periodo;
                $mayor = $grupos;
            }
        }

        return $principal instanceof Periodo ? $this->dato($principal) : null;
    }

    /**
     * @return list<Periodo>
     */
    private function periodosVigentes(): array
    {
        $hoy = now()->toDateString();

        return array_values(
            Periodo::withCount('grupos')
                ->whereDate('fecha_inicio', '<=', $hoy)
                ->whereDate('fecha_fin', '>=', $hoy)
                ->orderByDesc('anio')
                ->orderByDesc('numero')
                ->get()
                ->all()
        );
    }

    private function dato(Periodo $periodo): PeriodoData
    {
        $ventanas = [];

        foreach ($periodo->ventanas ?? [] as $nombre => $rango) {
            if (is_string($nombre)) {
                $ventanas[$nombre] = $rango;
            }
        }

        return new PeriodoData(
            id: $periodo->id,
            codigo: $periodo->codigo,
            tipo: $periodo->tipo->value,
            tipoEtiqueta: $periodo->tipo->etiqueta(),
            fechaInicio: $periodo->fecha_inicio?->toDateString(),
            fechaFin: $periodo->fecha_fin?->toDateString(),
            ventanas: $ventanas,
        );
    }
}
