<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Queries;

use App\Modules\Estudiantes\Application\Contracts\PadronGateway;

/**
 * La ficha de un estudiante del padron, con las materias que lleva en el
 * periodo: es lo que permite seguir a la misma persona en todas ellas.
 */
final readonly class ConsultarFichaDeEstudiante
{
    public function __construct(
        private PadronGateway $padron,
        /** El correo institucional no se guarda: es el codigo universitario con este dominio. */
        private string $dominioCorreo,
    ) {}

    /**
     * @return array<string, mixed>|null null si el estudiante no existe
     */
    public function execute(int $estudianteId): ?array
    {
        return $this->padron->ficha($estudianteId, $this->dominioCorreo);
    }
}
