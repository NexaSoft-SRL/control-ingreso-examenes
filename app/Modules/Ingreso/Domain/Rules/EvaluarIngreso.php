<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Rules;

use App\Modules\Ingreso\Domain\Enums\VeredictoIngreso;

/**
 * Si un estudiante inscrito puede entrar. El aula del reparto es una
 * sugerencia: cualquier puerta del examen admite y el ingreso guarda por
 * donde entro, asi un aula que a ultima hora no se pudo usar no deja a
 * nadie afuera. Un habilitado sin aula asignada tambien entra.
 */
final readonly class EvaluarIngreso
{
    // Pasado este margen desde el inicio, el ingreso se marca como tarde.
    public const TOLERANCIA_MINUTOS = 15;

    // La misma credencial leida otra vez en la misma puerta enseguida de
    // admitirla es un error de quien lee, no un intento.
    public const RELECTURA_SEGUNDOS = 120;

    public function evaluar(
        bool $inscrito,
        ?bool $habilitado,
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

        return VeredictoIngreso::Admitido;
    }

    /**
     * Si el estudiante esta en mas de un examen del aula a la vez, el
     * puesto de cada uno: sin ingreso, habilitado y con esta aula primero;
     * despues, el que mas se le acerque. Menor es mejor.
     */
    public function puesto(bool $yaIngreso, ?bool $habilitado, ?int $aulaAsignadaId, int $aulaControlId): int
    {
        return match (true) {
            $yaIngreso => 3,
            $habilitado !== true => 2,
            $aulaAsignadaId !== $aulaControlId => 1,
            default => 0,
        };
    }

    /**
     * Minutos de retraso pasada la tolerancia, o nulo si llego a tiempo.
     */
    public function minutosTarde(int $minutosDesdeElInicio): ?int
    {
        return $minutosDesdeElInicio > self::TOLERANCIA_MINUTOS ? $minutosDesdeElInicio : null;
    }

    /**
     * Si una lectura del mismo ingreso es una relectura: misma puerta y a
     * menos de dos minutos del ingreso.
     */
    public function esRelectura(?int $aulaIngresoId, int $aulaControlId, int $segundosDesdeElIngreso): bool
    {
        return $aulaIngresoId === $aulaControlId
            && abs($segundosDesdeElIngreso) <= self::RELECTURA_SEGUNDOS;
    }
}
