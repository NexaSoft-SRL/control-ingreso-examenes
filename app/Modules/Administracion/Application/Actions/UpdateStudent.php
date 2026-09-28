<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\StudentRepository;
use App\Modules\Administracion\Domain\Models\Student;

final readonly class UpdateStudent
{
    public function __construct(
        private StudentRepository $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Student $student, array $data, ?int $usuarioId): Student
    {
        $operation = isset($data['activo'])
            && filter_var($data['activo'], FILTER_VALIDATE_BOOLEAN)
            && ! $student->activo
                ? 'estudiante.reactivar'
                : 'estudiante.actualizar';

        return $this->repository->update($student, $data, $usuarioId, true, $operation);
    }
}
