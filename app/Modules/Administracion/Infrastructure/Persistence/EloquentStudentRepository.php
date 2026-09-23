<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\StudentRepository;
use App\Modules\Administracion\Domain\Models\Student;

final class EloquentStudentRepository implements StudentRepository
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Student
    {
        return Student::query()->create($data);
    }

    /**
     * @return list<Student>
     */
    public function all(): array
    {
        /** @var list<Student> $students */
        $students = Student::query()
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->get()
            ->all();

        return $students;
    }

    public function findById(int $id): ?Student
    {
        return Student::query()->find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Student $student, array $data): Student
    {
        $student->fill($data);
        $student->save();

        return $student->refresh();
    }

    public function delete(Student $student): void
    {
        $student->delete();
    }
}
