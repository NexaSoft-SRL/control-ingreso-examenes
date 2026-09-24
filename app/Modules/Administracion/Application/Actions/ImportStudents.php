<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\StudentRepository;
use Illuminate\Support\Facades\Validator;

final readonly class ImportStudents
{
    public function __construct(
        private StudentRepository $repository,
    ) {}

    /**
     * @param  list<array{fila: int, valores: array<int, string|null>}>  $rows
     * @return array{creados: int, actualizados: int, rechazados: int, detalles: list<array{fila: int, motivo: string, tipo: string}>}
     */
    public function execute(array $rows): array
    {
        $existingStudents = $this->repository->all();
        $byCi = [];
        $emailOwners = [];

        foreach ($existingStudents as $student) {
            $byCi[$student->ci] = $student;
            $emailOwners[strtolower($student->correo)] = $student->id;
        }

        $seenCi = [];
        $seenEmails = [];
        $created = 0;
        $updated = 0;
        $details = [];

        foreach ($rows as $row) {
            if (count($row['valores']) !== 5) {
                $details[] = [
                    'fila' => $row['fila'],
                    'motivo' => 'La fila debe tener exactamente cinco columnas.',
                    'tipo' => 'rechazado',
                ];
                continue;
            }

            [$nombre, $apellido, $ci, $correo, $activo] = array_map(
                static fn (?string $value): string => trim((string) $value),
                array_slice($row['valores'], 0, 5),
            );

            $data = [
                'nombre' => $nombre,
                'apellido' => $apellido,
                'ci' => $ci,
                'correo' => $correo,
                'activo' => $activo === '' ? true : filter_var($activo, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ];

            $validator = Validator::make($data, [
                'nombre' => ['required', 'string', 'max:100'],
                'apellido' => ['required', 'string', 'max:100'],
                'ci' => ['required', 'string', 'max:30'],
                'correo' => ['required', 'email', 'max:150'],
                'activo' => ['required', 'boolean'],
            ]);

            if ($validator->fails()) {
                $details[] = ['fila' => $row['fila'], 'motivo' => $validator->errors()->first(), 'tipo' => 'rechazado'];
                continue;
            }

            $normalizedEmail = strtolower($correo);
            if (isset($seenCi[$ci]) || isset($seenEmails[$normalizedEmail])) {
                $details[] = ['fila' => $row['fila'], 'motivo' => 'CI o correo repetido en el archivo.', 'tipo' => 'rechazado'];
                continue;
            }
            $seenCi[$ci] = true;
            $seenEmails[$normalizedEmail] = true;

            $student = $byCi[$ci] ?? null;
            $emailOwnerId = $emailOwners[$normalizedEmail] ?? null;
            if ($emailOwnerId !== null && $emailOwnerId !== $student?->id) {
                $details[] = ['fila' => $row['fila'], 'motivo' => 'El correo ya pertenece a otro estudiante.', 'tipo' => 'rechazado'];
                continue;
            }

            if ($student === null) {
                $student = $this->repository->create($data);
                $created++;
            } else {
                unset($emailOwners[strtolower($student->correo)]);
                $student = $this->repository->update($student, $data);
                $updated++;
                $details[] = ['fila' => $row['fila'], 'motivo' => 'Estudiante actualizado por coincidencia de CI.', 'tipo' => 'actualizado'];
            }

            $byCi[$ci] = $student;
            $emailOwners[$normalizedEmail] = $student->id;
        }

        return [
            'creados' => $created,
            'actualizados' => $updated,
            'rechazados' => count(array_filter($details, static fn (array $detail): bool => $detail['tipo'] === 'rechazado')),
            'detalles' => $details,
        ];
    }
}
