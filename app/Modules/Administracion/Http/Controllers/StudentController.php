<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Controllers;

use App\Modules\Administracion\Application\Actions\CreateStudent;
use App\Modules\Administracion\Application\Actions\DeleteStudent;
use App\Modules\Administracion\Application\Actions\FindStudent;
use App\Modules\Administracion\Application\Actions\ImportStudents;
use App\Modules\Administracion\Application\Actions\ListStudents;
use App\Modules\Administracion\Application\Actions\UpdateStudent;
use App\Modules\Administracion\Http\Requests\ImportStudentsRequest;
use App\Modules\Administracion\Http\Requests\StoreStudentRequest;
use App\Modules\Administracion\Http\Requests\UpdateStudentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class StudentController
{
    public function __construct(
        private readonly CreateStudent $createStudent,
        private readonly UpdateStudent $updateStudent,
        private readonly DeleteStudent $deleteStudent,
        private readonly FindStudent $findStudent,
        private readonly ListStudents $listStudents,
        private readonly ImportStudents $importStudents,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(
            $this->listStudents->execute(),
            Response::HTTP_OK,
        );
    }

    public function import(ImportStudentsRequest $request): JsonResponse
    {
        $file = $request->file('archivo');
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            return response()->json(['message' => 'No se pudo leer el archivo.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false) {
                return response()->json(['message' => 'El archivo está vacío.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $header[0] = preg_replace('/^\\xEF\\xBB\\xBF/', '', (string) $header[0]);
            $expectedHeader = ['nombre', 'apellido', 'ci', 'correo', 'activo'];
            if ($header !== $expectedHeader) {
                return response()->json([
                    'message' => 'Las columnas deben ser: nombre, apellido, ci, correo, activo.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $rows = [];
            $line = 1;
            while (($values = fgetcsv($handle)) !== false) {
                $line++;
                if ($values === [null]) {
                    continue;
                }

                $rows[] = ['fila' => $line, 'valores' => $values];
            }
        } finally {
            fclose($handle);
        }

        return response()->json($this->importStudents->execute($rows), Response::HTTP_OK);
    }

    public function store(StoreStudentRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = $request->validated();

        return response()->json(
            $this->createStudent->execute($data),
            Response::HTTP_CREATED,
        );
    }

    public function show(int $student): JsonResponse
    {
        $found = $this->findStudent->execute($student);

        if ($found === null) {
            return response()->json(
                ['message' => 'Estudiante no encontrado.'],
                Response::HTTP_NOT_FOUND,
            );
        }

        return response()->json($found, Response::HTTP_OK);
    }

    public function update(UpdateStudentRequest $request, int $student): JsonResponse
    {
        $found = $this->findStudent->execute($student);

        if ($found === null) {
            return response()->json(
                ['message' => 'Estudiante no encontrado.'],
                Response::HTTP_NOT_FOUND,
            );
        }

        /** @var array<string, mixed> $data */
        $data = $request->validated();

        return response()->json(
            $this->updateStudent->execute($found, $data),
            Response::HTTP_OK,
        );
    }

    public function destroy(int $student): JsonResponse
    {
        $found = $this->findStudent->execute($student);

        if ($found === null) {
            return response()->json(
                ['message' => 'Estudiante no encontrado.'],
                Response::HTTP_NOT_FOUND,
            );
        }

        $this->deleteStudent->execute($found);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
