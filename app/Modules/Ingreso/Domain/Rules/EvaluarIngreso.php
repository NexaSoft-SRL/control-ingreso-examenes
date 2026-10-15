<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Rules;

use App\Modules\Ingreso\Domain\Enums\VeredictoIngreso;

final readonly class EvaluarIngreso
{
    public function evaluar(
        bool $inscrito,
        ?bool $habilitado,
        ?int $aulaAsignadaId,
        int $aulaControlId,
        bool $yaIngreso,
        bool $expulsado,
    ): VeredictoIngreso {
        if (! $inscrito) {
            return VeredictoIngreso::NoCorresponde;
        }

        if ($expulsado) {
            return VeredictoIngreso::Expulsado;
        }

        if ($yaIngreso) {
            return VeredictoIngreso::YaIngreso;
        }

        if ($habilitado !== true) {
            return VeredictoIngreso::NoHabilitado;
        }

        if ($aulaAsignadaId === null) {
            return VeredictoIngreso::NoCorresponde;
        }

        if ($aulaAsignadaId !== $aulaControlId) {
            return VeredictoIngreso::OtraAula;
        }

        return VeredictoIngreso::Admitido;
    }
}
