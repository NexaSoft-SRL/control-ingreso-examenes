<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Application\Contracts\StudentRepository;
use App\Modules\Administracion\Domain\Models\Student;
use Illuminate\Support\Facades\DB;

final class EloquentStudentRepository implements StudentRepository
{
    public function __construct(
        private readonly BitacoraGateway $bitacora,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?int $usuarioId): Student
    {
        return DB::transaction(function () use ($data, $usuarioId): Student {
            $student = Student::query()->create($data);
            $this->bitacora->registrar(
                $usuarioId,
                'Registro de nuevo estudiante',
                'students',
                $student->id,
                'Registro de nuevo estudiante en Padrón / Estudiantes.',
            );

            return $student;
        });
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
    public function update(Student $student, array $data, ?int $usuarioId): Student
    {
        return DB::transaction(function () use ($student, $data, $usuarioId): Student {
            $student->fill($data);
            $student->save();
            $student->refresh();

            $this->bitacora->registrar(
                $usuarioId,
                'Actualización de estudiante',
                'students',
                $student->id,
                'Actualización de estudiante en Padrón / Estudiantes.',
            );

            return $student;
        });
    }
}
