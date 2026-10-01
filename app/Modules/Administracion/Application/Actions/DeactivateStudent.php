<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\StudentRepository;
use App\Modules\Administracion\Domain\Models\Student;

/**
 * Baja de un estudiante (HU-03). El backlog pide darlo de baja "sin borrar su
 * historial": el registro se conserva y solo deja de estar activo, porque de
 * el cuelgan habilitaciones e ingresos de examenes anteriores.
 */
final readonly class DeactivateStudent
{
    public function __construct(
        private StudentRepository $repository,
    ) {}

    public function execute(Student $student, int $usuarioId): Student
    {
        return $this->repository->update(
            $student,
            ['activo' => false],
            $usuarioId,
            true,
            'estudiante.baja',
        );
    }
}
