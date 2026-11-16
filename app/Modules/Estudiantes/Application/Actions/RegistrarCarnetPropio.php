<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Actions;

use App\Modules\Estudiantes\Application\Contracts\PortalEstudianteGateway;
use App\Modules\Estudiantes\Application\DTOs\EstudiantePortalData;
use App\Modules\Estudiantes\Domain\Exceptions\CarnetYaRegistradoException;
use App\Modules\Estudiantes\Domain\Exceptions\DocumentoEnUsoException;

/**
 * El estudiante escribe su carnet. Los docentes y la administracion no lo
 * tienen: es responsabilidad del estudiante que sea correcto, y por eso no
 * puede modificarlo despues. Sin carnet no se lo habilita en un examen.
 */
final readonly class RegistrarCarnetPropio
{
    public function __construct(
        private PortalEstudianteGateway $portal,
    ) {}

    /**
     * @throws CarnetYaRegistradoException
     * @throws DocumentoEnUsoException si el carnet es de otro estudiante
     */
    public function execute(int $estudianteId, string $documento): EstudiantePortalData
    {
        $this->portal->registrarCarnet($estudianteId, trim($documento));

        return $this->portal->estudiante($estudianteId) ?? throw new CarnetYaRegistradoException;
    }
}
