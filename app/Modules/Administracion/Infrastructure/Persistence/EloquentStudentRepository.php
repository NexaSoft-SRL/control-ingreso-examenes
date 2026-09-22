<?php

namespace App\Modules\Administracion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\StudentRepository;
use App\Modules\Administracion\Infrastructure\Persistence\EloquentStudent;

class EloquentStudentRepository implements StudentRepository
{
    public function create(array $data): EloquentStudent
    {
        return EloquentStudent::create($data);
    }

    public function all()
    {
        return EloquentStudent::all();
    }

    public function update(EloquentStudent $student, array $data): EloquentStudent
    {
        $student->update($data);
        return $student;
    }

    public function delete(EloquentStudent $student): void
    {
        $student->delete();
    }
}
