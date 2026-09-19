<?php

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\StudentRepository;
use App\Modules\Administracion\Domain\Models\Student;

final readonly class DeleteStudent
{
    public function __construct(
        private StudentRepository $repository,
    ) {}

    public function execute(Student $student): void
    {
        $this->repository->delete($student);
    }
}
