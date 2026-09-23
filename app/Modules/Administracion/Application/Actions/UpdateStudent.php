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
    public function execute(Student $student, array $data): Student
    {
        return $this->repository->update($student, $data);
    }
}
