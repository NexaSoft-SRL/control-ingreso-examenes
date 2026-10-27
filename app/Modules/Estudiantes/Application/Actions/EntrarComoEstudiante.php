<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Actions;

use App\Modules\Estudiantes\Application\Contracts\PortalEstudianteGateway;
use App\Modules\Estudiantes\Application\DTOs\EstudiantePortalData;
use App\Modules\Estudiantes\Domain\Exceptions\CodigoDeAccesoInvalidoException;

/**
 * El estudiante entra con su codigo universitario y el codigo que le
 * llego al correo. Cinco intentos por codigo.
 */
final readonly class EntrarComoEstudiante
{
    public const INTENTOS = 5;

    public function __construct(
        private PortalEstudianteGateway $portal,
    ) {}

    public function execute(string $codigoUniversitario, string $codigo): EstudiantePortalData
    {
        $estudiante = $this->portal->porCodigo($codigoUniversitario);

        if ($estudiante === null || ! $this->portal->consumirCodigo($estudiante->id, hash('sha256', $codigo), self::INTENTOS)) {
            throw new CodigoDeAccesoInvalidoException;
        }

        return $estudiante;
    }
}
