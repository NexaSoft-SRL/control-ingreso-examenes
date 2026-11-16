<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Actions;

use App\Modules\Estudiantes\Application\Contracts\PortalEstudianteGateway;
use App\Modules\Estudiantes\Application\DTOs\EstudiantePortalData;
use App\Modules\Estudiantes\Domain\Exceptions\DocumentoEnUsoException;
use App\Modules\Estudiantes\Domain\Rules\DatosDeInscrito;

/**
 * El estudiante que verifico su correo se empadrona con sus propios datos.
 * Quedan con origen «Estudiante», el de menos peso: lo que luego cargue un
 * docente o la administracion los reemplaza. Si mientras tanto otra carga
 * ya lo trajo, entra con esos datos y los suyos no se guardan.
 */
final readonly class EmpadronarEstudiante
{
    public function __construct(
        private PortalEstudianteGateway $portal,
    ) {}

    /**
     * @throws DocumentoEnUsoException si el documento es de otro estudiante
     */
    public function execute(
        string $codigoUniversitario,
        string $documentoIdentidad,
        string $nombres,
        string $apellidos,
        int $carreraId,
    ): EstudiantePortalData {
        $existente = $this->portal->porCodigo($codigoUniversitario);

        if ($existente !== null) {
            return $existente;
        }

        return $this->portal->empadronar(
            trim($codigoUniversitario),
            trim($documentoIdentidad),
            mb_substr(trim($nombres), 0, DatosDeInscrito::MAXIMO_NOMBRE),
            mb_substr(trim($apellidos), 0, DatosDeInscrito::MAXIMO_NOMBRE),
            $carreraId,
        );
    }
}
