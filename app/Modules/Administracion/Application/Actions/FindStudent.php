<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\StudentRepository;
use App\Modules\Administracion\Domain\Models\Student;

final readonly class FindStudent
{
    public function __construct(
        private StudentRepository $repository,
    ) {}

    public function execute(int $id): ?Student
    {
        return $this->repository->findById($id);
    }
}
