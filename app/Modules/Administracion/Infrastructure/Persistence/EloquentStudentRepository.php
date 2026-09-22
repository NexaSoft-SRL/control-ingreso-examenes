<?php

namespace App\Modules\Administracion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\StudentRepository;
use App\Modules\Administracion\Domain\Models\Student;
use App\Modules\Administracion\Domain\Models\EloquentStudent;

class EloquentStudentRepository implements StudentRepository
{
    public function create(array $data): Student
    {
        $eloquent = EloquentStudent::create($data);
        return $this->toDomain($eloquent);
    }

    public function all(): array
    {
        return EloquentStudent::all()
            ->map(fn ($eloquent) => $this->toDomain($eloquent))
            ->toArray();
    }

    public function update(Student $student, array $data): Student
    {
        $eloquent = EloquentStudent::findOrFail($student->id);
        $eloquent->update($data);
        return $this->toDomain($eloquent);
    }

    public function delete(Student $student): void
    {
        $eloquent = EloquentStudent::findOrFail($student->id);
        $eloquent->delete();
    }

    private function toDomain(EloquentStudent $eloquent): Student
    {
        return new Student(
            id: $eloquent->id,
            nombre: $eloquent->nombre,
            apellido: $eloquent->apellido,
            ci: $eloquent->ci,
            correo: $eloquent->correo,
            activo: $eloquent->activo,
        );
    }
}
