<?php

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\StudentRepository;

class CreateStudent
{
    public function __construct(private StudentRepository $repository) {}

    public function execute(array $data)
    {
        return $this->repository->create($data);
    }
}
