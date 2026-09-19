<?php

namespace App\Modules\Administracion\Http\Controllers;

use App\Modules\Administracion\Application\Contracts\StudentRepository;
use App\Modules\Administracion\Domain\Models\Student;
use App\Modules\Administracion\Http\Requests\StoreStudentRequest;
use App\Modules\Administracion\Http\Requests\UpdateStudentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StudentController
{
    public function __construct(
        private readonly StudentRepository $repository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['buscar', 'estado', 'carrera_id']);
        $perPage = (int) $request->input('per_page', 15);

        $result = $this->repository->paginate($filters, $perPage);

        return response()->json([
            'data' => collect($result['data'])->map(fn ($student) => [
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
            ]),
            'meta' => $result['meta'],
        ], Response::HTTP_OK);
    }

    public function store(StoreStudentRequest $request): JsonResponse
    {
        $student = $this->repository->create($request->validated());

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
        $found = $this->repository->findById($student);

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
        $found = $this->repository->findById($student);

        if ($found === null) {
            return response()->json(['message' => 'Estudiante no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $updated = $this->repository->update($found, $request->validated());

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
        $found = $this->repository->findById($student);

        if ($found === null) {
            return response()->json(['message' => 'Estudiante no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $this->repository->delete($found);

        return response()->json(['message' => 'Estudiante eliminado correctamente.'], Response::HTTP_OK);
    }
}
