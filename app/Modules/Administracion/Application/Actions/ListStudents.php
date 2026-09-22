<?php

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\StudentRepository;

class ListStudents
{
    public function __construct(private StudentRepository $repository) {}

    public function execute()
    {
        return $this->repository->all();
    }
}
