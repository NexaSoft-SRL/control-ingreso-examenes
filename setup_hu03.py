#!/usr/bin/env python3
import os

base = os.getcwd()
print("Directorio base:", base)

files = {}

files['app/Modules/Administracion/Domain/Models/Student.php'] = '''<?php

namespace App\\Modules\\Administracion\\Domain\\Models;

use Illuminate\\Database\\Eloquent\\Builder;
use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;

class Student extends Model
{
    protected $table = 'estudiantes';

    protected $fillable = [
        'carrera_id',
        'codigo_sis',
        'ci',
        'nombres',
        'apellidos',
        'correo',
        'telefono',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'carrera_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class, 'carrera_id');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', 'ACTIVO');
    }

    public function scopeBuscar(Builder $query, string $termino): Builder
    {
        return $query->where(function (Builder $q) use ($termino) {
            $q->where('codigo_sis', 'ILIKE', '%' . $termino . '%')
                ->orWhere('ci', 'ILIKE', '%' . $termino . '%')
                ->orWhere('nombres', 'ILIKE', '%' . $termino . '%')
                ->orWhere('apellidos', 'ILIKE', '%' . $termino . '%');
        });
    }
}
'''

files['app/Modules/Administracion/Application/Contracts/StudentRepository.php'] = '''<?php

namespace App\\Modules\\Administracion\\Application\\Contracts;

use App\\Modules\\Administracion\\Domain\\Models\\Student;
use Illuminate\\Contracts\\Pagination\\LengthAwarePaginator;

interface StudentRepository
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function findById(int $id): ?Student;
    public function create(array $data): Student;
    public function update(Student $student, array $data): Student;
    public function delete(Student $student): void;
}
'''

files['app/Modules/Administracion/Infrastructure/Persistence/EloquentStudentRepository.php'] = '''<?php

namespace App\\Modules\\Administracion\\Infrastructure\\Persistence;

use App\\Modules\\Administracion\\Application\\Contracts\\StudentRepository;
use App\\Modules\\Administracion\\Domain\\Models\\Student;
use Illuminate\\Contracts\\Pagination\\LengthAwarePaginator;

final readonly class EloquentStudentRepository implements StudentRepository
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
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

        return $query->orderBy('apellidos')->orderBy('nombres')->paginate($perPage);
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
'''

files['app/Modules/Administracion/Application/Actions/UpdateStudent.php'] = '''<?php

namespace App\\Modules\\Administracion\\Application\\Actions;

use App\\Modules\\Administracion\\Application\\Contracts\\StudentRepository;
use App\\Modules\\Administracion\\Domain\\Models\\Student;

final readonly class UpdateStudent
{
    public function __construct(
        private StudentRepository $repository,
    ) {}

    public function execute(Student $student, array $data): Student
    {
        return $this->repository->update($student, $data);
    }
}
'''

files['app/Modules/Administracion/Application/Actions/DeleteStudent.php'] = '''<?php

namespace App\\Modules\\Administracion\\Application\\Actions;

use App\\Modules\\Administracion\\Application\\Contracts\\StudentRepository;
use App\\Modules\\Administracion\\Domain\\Models\\Student;

final readonly class DeleteStudent
{
    public function __construct(
        private StudentRepository $repository,
    ) {}

    public function execute(Student $student): void
    {
        $this->repository->delete($student);
    }
}
'''

files['app/Modules/Administracion/Application/Actions/FindStudent.php'] = '''<?php

namespace App\\Modules\\Administracion\\Application\\Actions;

use App\\Modules\\Administracion\\Application\\Contracts\\StudentRepository;
use App\\Modules\\Administracion\\Domain\\Models\\Student;

final readonly class FindStudent
{
    public function __construct(
        private StudentRepository $repository,
    ) {}

    public function execute(int $id): ?Student
    {
        return $this->repository->findById($id);
    }
}
'''

files['app/Modules/Administracion/Application/Actions/ListStudents.php'] = '''<?php

namespace App\\Modules\\Administracion\\Application\\Actions;

use App\\Modules\\Administracion\\Application\\Contracts\\StudentRepository;
use Illuminate\\Contracts\\Pagination\\LengthAwarePaginator;

final readonly class ListStudents
{
    public function __construct(
        private StudentRepository $repository,
    ) {}

    public function execute(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($filters, $perPage);
    }
}
'''

files['app/Modules/Administracion/Http/Requests/StoreStudentRequest.php'] = '''<?php

namespace App\\Modules\\Administracion\\Http\\Requests;

use Illuminate\\Foundation\\Http\\FormRequest;
use Illuminate\\Validation\\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'carrera_id' => ['required', 'integer', 'exists:carreras,id'],
            'codigo_sis' => ['required', 'string', 'max:30', Rule::unique('estudiantes', 'codigo_sis')],
            'ci' => ['required', 'string', 'max:30', Rule::unique('estudiantes', 'ci')],
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'correo' => ['nullable', 'email', 'max:150', Rule::unique('estudiantes', 'correo')],
            'telefono' => ['nullable', 'string', 'max:30'],
            'estado' => ['sometimes', 'string', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }
}
'''

files['app/Modules/Administracion/Http/Requests/UpdateStudentRequest.php'] = '''<?php

namespace App\\Modules\\Administracion\\Http\\Requests;

use Illuminate\\Foundation\\Http\\FormRequest;
use Illuminate\\Validation\\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $studentId = (int) $this->route('student');

        return [
            'carrera_id' => ['sometimes', 'integer', 'exists:carreras,id'],
            'codigo_sis' => ['sometimes', 'string', 'max:30', Rule::unique('estudiantes', 'codigo_sis')->ignore($studentId)],
            'ci' => ['sometimes', 'string', 'max:30', Rule::unique('estudiantes', 'ci')->ignore($studentId)],
            'nombres' => ['sometimes', 'string', 'max:100'],
            'apellidos' => ['sometimes', 'string', 'max:100'],
            'correo' => ['nullable', 'email', 'max:150', Rule::unique('estudiantes', 'correo')->ignore($studentId)],
            'telefono' => ['nullable', 'string', 'max:30'],
            'estado' => ['sometimes', 'string', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }
}
'''

files['routes/students.php'] = '''<?php

use App\\Modules\\Administracion\\Http\\Controllers\\StudentController;
use Illuminate\\Support\\Facades\\Route;

Route::prefix('api/admin/students')
    ->name('students.')
    ->middleware('auth')
    ->group(function (): void {
        Route::get('/', [StudentController::class, 'index'])->name('index');
        Route::post('/', [StudentController::class, 'store'])->name('store');
        Route::get('/{student}', [StudentController::class, 'show'])->name('show');
        Route::put('/{student}', [StudentController::class, 'update'])->name('update');
        Route::delete('/{student}', [StudentController::class, 'destroy'])->name('destroy');
    });
'''

files['app/Modules/Administracion/Http/Controllers/StudentController.php'] = '''<?php

namespace App\\Modules\\Administracion\\Http\\Controllers;

use App\\Modules\\Administracion\\Application\\Actions\\CreateStudent;
use App\\Modules\\Administracion\\Application\\Actions\\DeleteStudent;
use App\\Modules\\Administracion\\Application\\Actions\\FindStudent;
use App\\Modules\\Administracion\\Application\\Actions\\ListStudents;
use App\\Modules\\Administracion\\Application\\Actions\\UpdateStudent;
use App\\Modules\\Administracion\\Http\\Requests\\StoreStudentRequest;
use App\\Modules\\Administracion\\Http\\Requests\\UpdateStudentRequest;
use Illuminate\\Http\\JsonResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Http\\Response;

class StudentController
{
    public function __construct(
        private readonly CreateStudent $createStudent,
        private readonly UpdateStudent $updateStudent,
        private readonly DeleteStudent $deleteStudent,
        private readonly FindStudent $findStudent,
        private readonly ListStudents $listStudents,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['buscar', 'estado', 'carrera_id']);
        $perPage = (int) $request->input('per_page', 15);
        $paginator = $this->listStudents->execute($filters, $perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(function ($student) {
                return [
                    'id' => $student->getKey(),
                    'codigo_sis' => $student->codigo_sis,
                    'ci' => $student->ci,
                    'nombres' => $student->nombres,
                    'apellidos' => $student->apellidos,
                    'correo' => $student->correo,
                    'telefono' => $student->telefono,
                    'estado' => $student->estado,
                    'carrera' => $student->carrera ? [
                        'id' => $student->carrera->getKey(),
                        'nombre' => $student->carrera->nombre,
                    ] : null,
                ];
            }),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StoreStudentRequest $request): JsonResponse
    {
        $student = $this->createStudent->execute($request->validated());

        return response()->json([
            'message' => 'Estudiante registrado correctamente.',
            'data' => [
                'id' => $student->getKey(),
                'codigo_sis' => $student->codigo_sis,
                'ci' => $student->ci,
                'nombres' => $student->nombres,
                'apellidos' => $student->apellidos,
                'correo' => $student->correo,
                'telefono' => $student->telefono,
                'estado' => $student->estado,
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(int $student): JsonResponse
    {
        $found = $this->findStudent->execute($student);

        if ($found === null) {
            return response()->json(['message' => 'Estudiante no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'data' => [
                'id' => $found->getKey(),
                'codigo_sis' => $found->codigo_sis,
                'ci' => $found->ci,
                'nombres' => $found->nombres,
                'apellidos' => $found->apellidos,
                'correo' => $found->correo,
                'telefono' => $found->telefono,
                'estado' => $found->estado,
                'carrera' => $found->carrera ? [
                    'id' => $found->carrera->getKey(),
                    'nombre' => $found->carrera->nombre,
                ] : null,
            ],
        ], Response::HTTP_OK);
    }

    public function update(UpdateStudentRequest $request, int $student): JsonResponse
    {
        $found = $this->findStudent->execute($student);

        if ($found === null) {
            return response()->json(['message' => 'Estudiante no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $updated = $this->updateStudent->execute($found, $request->validated());

        return response()->json([
            'message' => 'Estudiante actualizado correctamente.',
            'data' => [
                'id' => $updated->getKey(),
                'codigo_sis' => $updated->codigo_sis,
                'ci' => $updated->ci,
                'nombres' => $updated->nombres,
                'apellidos' => $updated->apellidos,
                'correo' => $updated->correo,
                'telefono' => $updated->telefono,
                'estado' => $updated->estado,
            ],
        ], Response::HTTP_OK);
    }

    public function destroy(int $student): JsonResponse
    {
        $found = $this->findStudent->execute($student);

        if ($found === null) {
            return response()->json(['message' => 'Estudiante no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $this->deleteStudent->execute($found);

        return response()->json(['message' => 'Estudiante eliminado correctamente.'], Response::HTTP_OK);
    }
}
'''

for rel_path in files:
    content = files[rel_path]
    full_path = os.path.join(base, rel_path)
    try:
        with open(full_path, 'w') as f:
            f.write(content)
        print("OK:", rel_path, "(", len(content), "bytes )")
    except Exception as e:
        print("ERROR:", rel_path, "->", str(e))

print("Listo!")
