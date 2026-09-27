<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\StudentRepository;

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

            $validationError = $this->validationError($data);
            if ($validationError !== null) {
                $details[] = ['fila' => $row['fila'], 'motivo' => $validationError, 'tipo' => 'rechazado'];

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

    /**
     * @param  array{nombre: string, apellido: string, ci: string, correo: string, activo: bool|null}  $data
     */
    private function validationError(array $data): ?string
    {
        foreach (['nombre' => 100, 'apellido' => 100, 'ci' => 30, 'correo' => 150] as $field => $maxLength) {
            if ($data[$field] === '') {
                return "El campo {$field} es obligatorio.";
            }

            $length = function_exists('mb_strlen') ? mb_strlen($data[$field]) : strlen($data[$field]);
            if ($length > $maxLength) {
                return "El campo {$field} no puede superar {$maxLength} caracteres.";
            }
        }

        if (filter_var($data['correo'], FILTER_VALIDATE_EMAIL) === false) {
            return 'El correo no tiene un formato válido.';
        }

        if ($data['activo'] === null) {
            return 'El campo activo debe ser true o false.';
        }

        return null;
    }
}
