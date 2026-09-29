<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Application\Contracts\StudentRepository;

/**
 * Carga masiva del padron (HU-04). Las columnas son las cinco del backlog,
 * en este orden:
 *
 *   codigo_universitario, documento_identidad, nombres, apellidos, carrera
 *
 * Un error en una fila no interrumpe la carga: esa fila se rechaza con su
 * numero y su motivo, y el resto continua. Quien ya esta en el padron se
 * actualiza en lugar de duplicarse.
 */
final readonly class ImportStudents
{
    /**
     * @var list<string>
     */
    public const COLUMNAS = [
        'codigo_universitario',
        'documento_identidad',
        'nombres',
        'apellidos',
        'carrera',
    ];

    public function __construct(
        private StudentRepository $repository,
        private BitacoraGateway $bitacora,
    ) {}

    /**
     * @param  list<array{fila: int, valores: array<int, string|null>}>  $rows
     * @param  string|null  $archivo  Nombre del archivo cargado, para la bitácora
     * @return array{creados: int, actualizados: int, rechazados: int, detalles: list<array{fila: int, motivo: string, tipo: string}>}
     */
    public function execute(array $rows, int $usuarioId, ?string $archivo = null): array
    {
        $porCodigo = [];
        $porDocumento = [];

        foreach ($this->repository->all() as $student) {
            $codigo = $student->codigo_universitario;

            if (is_string($codigo) && $codigo !== '') {
                $porCodigo[$codigo] = $student;
            }

            $porDocumento[$student->ci] = $student;
        }

        $codigosVistos = [];
        $documentosVistos = [];
        $creados = 0;
        $actualizados = 0;
        $detalles = [];

        foreach ($rows as $row) {
            if (count($row['valores']) < count(self::COLUMNAS)) {
                $detalles[] = [
                    'fila' => $row['fila'],
                    'motivo' => 'La fila no tiene las cinco columnas esperadas.',
                    'tipo' => 'rechazado',
                ];

                continue;
            }

            [$codigo, $documento, $nombres, $apellidos, $carrera] = array_map(
                static fn (?string $value): string => trim((string) $value),
                array_slice($row['valores'], 0, 5),
            );

            $motivo = $this->motivoDeRechazo($codigo, $documento, $nombres, $apellidos, $carrera);

            if ($motivo !== null) {
                $detalles[] = ['fila' => $row['fila'], 'motivo' => $motivo, 'tipo' => 'rechazado'];

                continue;
            }

            if (isset($codigosVistos[$codigo])) {
                $detalles[] = [
                    'fila' => $row['fila'],
                    'motivo' => "El código universitario {$codigo} se repite en el archivo.",
                    'tipo' => 'rechazado',
                ];

                continue;
            }

            if (isset($documentosVistos[$documento])) {
                $detalles[] = [
                    'fila' => $row['fila'],
                    'motivo' => "El documento de identidad {$documento} se repite en el archivo.",
                    'tipo' => 'rechazado',
                ];

                continue;
            }

            $codigosVistos[$codigo] = true;
            $documentosVistos[$documento] = true;

            $datos = [
                'codigo_universitario' => $codigo,
                'ci' => $documento,
                'nombre' => $nombres,
                'apellido' => $apellidos,
                'carrera' => $carrera,
            ];

            $existente = $porCodigo[$codigo] ?? $porDocumento[$documento] ?? null;

            if ($existente === null) {
                $student = $this->repository->create($datos + ['activo' => true], $usuarioId, false);
                $creados++;
            } else {
                $student = $this->repository->update($existente, $datos, $usuarioId, false);
                $actualizados++;

                $detalles[] = [
                    'fila' => $row['fila'],
                    'motivo' => 'Estudiante ya registrado: se actualizaron sus datos.',
                    'tipo' => 'actualizado',
                ];
            }

            $porCodigo[$codigo] = $student;
            $porDocumento[$documento] = $student;
        }

        $rechazados = count(array_filter(
            $detalles,
            static fn (array $detalle): bool => $detalle['tipo'] === 'rechazado',
        ));

        // La carga toca muchas filas de una vez: en la bitacora se asienta
        // como una sola operacion con su resumen.
        $this->bitacora->registrar(
            $usuarioId,
            'padron.importar',
            'students',
            null,
            sprintf(
                'Carga masiva de %s: %d nuevos, %d actualizados, %d rechazados.',
                $archivo ?? 'archivo sin nombre',
                $creados,
                $actualizados,
                $rechazados,
            ),
        );

        return [
            'creados' => $creados,
            'actualizados' => $actualizados,
            'rechazados' => $rechazados,
            'detalles' => $detalles,
        ];
    }

    private function motivoDeRechazo(
        string $codigo,
        string $documento,
        string $nombres,
        string $apellidos,
        string $carrera,
    ): ?string {
        $limites = [
            'código universitario' => [$codigo, 20],
            'documento de identidad' => [$documento, 30],
            'nombres' => [$nombres, 100],
            'apellidos' => [$apellidos, 100],
            'carrera' => [$carrera, 120],
        ];

        foreach ($limites as $campo => [$valor, $maximo]) {
            if ($valor === '') {
                return "Falta el dato obligatorio: {$campo}.";
            }

            if (mb_strlen($valor) > $maximo) {
                return "El campo {$campo} no puede superar {$maximo} caracteres.";
            }
        }

        return null;
    }
}
