<?php

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\StudentRepository;
use App\Modules\Administracion\Domain\Models\Student;

class DeleteStudent
{
    public function __construct(private StudentRepository $repository) {}

    public function execute(Student $student)
    {
        return $this->repository->delete($student);
    }
}
