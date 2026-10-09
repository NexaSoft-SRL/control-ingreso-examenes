<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Actions;

use App\Modules\Estudiantes\Application\Contracts\ConflictoGateway;
use App\Modules\Estudiantes\Domain\Enums\ResolucionConflicto;
use App\Modules\Estudiantes\Domain\Exceptions\ConflictoYaResueltoException;
use App\Modules\Estudiantes\Domain\Exceptions\DocumentoEnUsoException;

/**
 * La administracion decide entre el dato guardado y el de la carga. Con
 * cualquiera de las dos salidas se crea la inscripcion que estaba en
 * espera.
 */
final readonly class ResolverConflicto
{
    public function __construct(
        private ConflictoGateway $conflictos,
    ) {}

    /**
     * @return string|null el codigo universitario del estudiante; null si el conflicto no existe
     *
     * @throws ConflictoYaResueltoException
     * @throws DocumentoEnUsoException
     */
    public function execute(int $conflictoId, ResolucionConflicto $resolucion, int $usuarioId): ?string
    {
        $conflicto = $this->conflictos->buscar($conflictoId);

        if ($conflicto === null) {
            return null;
        }

        if ($conflicto->resuelto) {
            throw new ConflictoYaResueltoException;
        }

        $this->conflictos->resolver($conflictoId, $resolucion, $usuarioId);

        return $conflicto->codigoUniversitario;
    }
}
