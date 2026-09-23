<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\StudentRepository;
use App\Modules\Administracion\Domain\Models\Student;

final readonly class ListStudents
{
    public function __construct(
        private StudentRepository $repository,
    ) {}

    /**
     * @return list<Student>
     */
    public function execute(): array
    {
        return $this->repository->all();
    }
}
