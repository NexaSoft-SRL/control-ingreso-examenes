<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Actions;

use App\Modules\Estudiantes\Application\Contracts\PadronGateway;

final readonly class CorregirDatosEstudiante
{
    public function __construct(private PadronGateway $padron) {}

    public function execute(
        int $usuarioId,
        int $estudianteId,
        string $nombres,
        string $apellidos,
        ?string $documento,
    ): bool {
        return $this->padron->corregirDatos($usuarioId, $estudianteId, $nombres, $apellidos, $documento);
    }
}
