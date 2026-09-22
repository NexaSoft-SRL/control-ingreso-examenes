<?php

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\StudentRepository;
use App\Modules\Administracion\Infrastructure\Persistence\EloquentStudent;

class UpdateStudent
{
    public function __construct(private StudentRepository $repository) {}

    public function execute(EloquentStudent $student, array $data)
    {
        return $this->repository->update($student, $data);
    }
}
