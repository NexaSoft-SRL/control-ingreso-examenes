<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Ingreso\Application\Contracts\VentanaPuntoControl;
use App\Modules\Ingreso\Domain\Rules\ComprobarVentanaIngreso;
use DateTimeImmutable;
use DateTimeZone;

final readonly class VentanaExamen implements VentanaPuntoControl
{
    public function __construct(
        private ExamenGateway $examenes,
        private ComprobarVentanaIngreso $regla,
    ) {}

    public function estaAbierta(int $examenId): bool
    {
        $examen = $this->examenes->actual($examenId);

        if ($examen === null) {
            return false;
        }

        return $this->regla->comprobar(
            $examen->fecha,
            $examen->horaInicio,
            $examen->duracionMinutos,
            new DateTimeImmutable(
                'now',
                new DateTimeZone('America/La_Paz'),
            ),
        );
    }
}
