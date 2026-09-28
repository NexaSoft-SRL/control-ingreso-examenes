<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Http\Controllers;

use App\Modules\Administracion\Application\Actions\CreateStudent;
use App\Modules\Administracion\Application\Actions\DeactivateStudent;
use App\Modules\Administracion\Application\Actions\FindStudent;
use App\Modules\Administracion\Application\Actions\ImportStudents;
use App\Modules\Administracion\Application\Actions\ListStudents;
use App\Modules\Administracion\Application\Actions\UpdateStudent;
use App\Modules\Administracion\Http\Requests\ImportStudentsRequest;
use App\Modules\Administracion\Http\Requests\StoreStudentRequest;
use App\Modules\Administracion\Http\Requests\UpdateStudentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use LogicException;

final class StudentController
{
    public function __construct(
        private readonly CreateStudent $createStudent,
        private readonly UpdateStudent $updateStudent,
        private readonly DeactivateStudent $deactivateStudent,
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

            $rows = [];
            $line = 1;

            // La primera fila puede ser el encabezado o ya un estudiante.
            if (! $this->esEncabezado($header)) {
                $rows[] = ['fila' => 1, 'valores' => $header];
            }
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

        return response()->json(
            $this->importStudents->execute($rows, $this->usuarioAutenticado()),
            Response::HTTP_OK,
        );
    }

    /**
     * @param  array<int, string|null>  $fila
     */
    private function esEncabezado(array $fila): bool
    {
        $primera = mb_strtolower(trim((string) ($fila[0] ?? '')));

        return str_contains($primera, 'codigo') || str_contains($primera, 'código');
    }

    public function store(StoreStudentRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = $request->validated();

        return response()->json(
            $this->createStudent->execute($data, $this->usuarioAutenticado()),
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
            $this->updateStudent->execute($found, $data, $this->usuarioAutenticado()),
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

        // HU-03: la baja no borra al estudiante, lo deja inactivo para no
        // perder su historial de examenes.
        return response()->json(
            $this->deactivateStudent->execute($found, $this->usuarioAutenticado()),
            Response::HTTP_OK,
        );
    }

    /**
     * La bitacora (HU-07) necesita saber quien hizo la operacion. Las rutas
     * de este controlador exigen sesion, asi que siempre hay usuario.
     */
    private function usuarioAutenticado(): int
    {
        $user = Auth::guard('web')->user();

        if ($user === null) {
            throw new LogicException(
                'No existe usuario autenticado.'
            );
        }

        $id = $user->getKey();

        if (! is_int($id) && ! is_string($id)) {
            throw new LogicException(
                'El usuario autenticado no tiene un identificador válido.'
            );
        }

        return (int) $id;
    }
}
