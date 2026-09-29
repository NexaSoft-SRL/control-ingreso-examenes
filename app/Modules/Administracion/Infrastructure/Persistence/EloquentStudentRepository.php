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
    public function create(array $data, ?int $usuarioId = null, bool $registrarBitacora = true): Student
    {
        return DB::transaction(function () use ($data, $usuarioId, $registrarBitacora): Student {
            $student = Student::query()->create($data);

            if ($registrarBitacora) {
                $this->bitacora->registrar(
                    $usuarioId,
                    'estudiante.registrar',
                    'students',
                    $student->id,
                    'Registro de nuevo estudiante en el padron.',
                );
            }

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
    public function update(
        Student $student,
        array $data,
        ?int $usuarioId = null,
        bool $registrarBitacora = true,
        string $operacion = 'estudiante.actualizar',
    ): Student {
        return DB::transaction(function () use ($student, $data, $usuarioId, $registrarBitacora, $operacion): Student {
            $student->fill($data);
            $student->save();
            $student->refresh();

            if ($registrarBitacora) {
                $this->bitacora->registrar(
                    $usuarioId,
                    $operacion,
                    'students',
                    $student->id,
                    'Actualizacion de estudiante en el padron.',
                );
            }

            return $student;
        });
    }

    public function delete(Student $student): void
    {
        $student->delete();
    }
}
