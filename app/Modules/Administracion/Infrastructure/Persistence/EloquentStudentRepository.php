<?php

namespace App\Modules\Administracion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\StudentRepository;
use App\Modules\Administracion\Domain\Models\Student;

final readonly class EloquentStudentRepository implements StudentRepository
{
    public function paginate(array $filters = [], int $perPage = 15): array
    {
        $query = Student::query()->with('carrera');

        if (! empty($filters['buscar'])) {
            $query->buscar((string) $filters['buscar']);
        }

        if (! empty($filters['estado'])) {
            $query->where('estado', (string) $filters['estado']);
        }

        if (! empty($filters['carrera_id'])) {
            $query->where('carrera_id', (int) $filters['carrera_id']);
        }

        $paginator = $query->orderBy('apellidos')->orderBy('nombres')->paginate($perPage);

        return [
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function findById(int $id): ?Student
    {
        return Student::query()->with('carrera')->find($id);
    }

    public function create(array $data): Student
    {
        return Student::query()->create($data);
    }

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
