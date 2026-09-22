<?php

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\StudentRepository;
use App\Modules\Administracion\Domain\Models\Student; 

class UpdateStudent
{
    public function __construct(private StudentRepository $repository) {}

    public function execute(Student $student, array $data) 
    {
        return $this->repository->update($student, $data);
    }
}
