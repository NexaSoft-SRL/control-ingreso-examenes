<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Examenes\Application\Contracts\NormaExamenGateway;
use App\Modules\Examenes\Application\DTOs\RegistrarNormaExamenData;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;

final class EloquentNormaExamenGateway implements NormaExamenGateway
{
    public function __construct(
        private readonly BitacoraGateway $bitacora,
    ) {}

    /**
     * @return list<array<string, mixed>>|null
     */
    public function listar(int $examenId): ?array
    {
        if (! DB::table('examenes')->where('id', $examenId)->exists()) {
            return null;
        }

        $normas = DB::table('normas_examenes as normas')
            ->leftJoin('students', 'students.id', '=', 'normas.estudiante_id')
            ->where('normas.examen_id', $examenId)
            ->orderByRaw("CASE WHEN normas.alcance = 'general' THEN 0 ELSE 1 END")
            ->orderBy('normas.id')
            ->get([
                'normas.id',
                'normas.examen_id',
                'normas.alcance',
                'normas.texto',
                'normas.estudiante_id',
                'normas.motivo',
                'students.nombre as estudiante_nombre',
                'students.apellido as estudiante_apellido',
                'students.codigo_universitario as estudiante_codigo',
            ])
            ->map(fn (\stdClass $row): array => $this->serializar($row))
            ->all();

        return array_values($normas);
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function listarParaEstudiante(int $examenId, int $estudianteId): ?array
    {
        if (! DB::table('examenes')->where('id', $examenId)->exists()
            || ! DB::table('students')->where('id', $estudianteId)->exists()) {
            return null;
        }

        $normas = DB::table('normas_examenes as normas')
            ->leftJoin('students', 'students.id', '=', 'normas.estudiante_id')
            ->where('normas.examen_id', $examenId)
            ->where(function (Builder $query) use ($estudianteId): void {
                $query->where('normas.alcance', 'general')
                    ->orWhere(function (Builder $particulares) use ($estudianteId): void {
                        $particulares->where('normas.alcance', 'particular')
                            ->where('normas.estudiante_id', $estudianteId);
                    });
            })
            ->orderByRaw("CASE WHEN normas.alcance = 'general' THEN 0 ELSE 1 END")
            ->orderBy('normas.id')
            ->get([
                'normas.id',
                'normas.examen_id',
                'normas.alcance',
                'normas.texto',
                'normas.estudiante_id',
                'normas.motivo',
                'students.nombre as estudiante_nombre',
                'students.apellido as estudiante_apellido',
                'students.codigo_universitario as estudiante_codigo',
            ])
            ->map(fn (\stdClass $row): array => $this->serializar($row))
            ->all();

        return array_values($normas);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function registrar(
        int $examenId,
        RegistrarNormaExamenData $data,
        int $usuarioId,
    ): ?array {
        return DB::transaction(function () use (
            $examenId,
            $data,
            $usuarioId,
        ): ?array {
            $examen = DB::table('examenes')
                ->where('id', $examenId)
                ->lockForUpdate()
                ->first(['id']);

            if ($examen === null) {
                return null;
            }

            $normaId = DB::table('normas_examenes')->insertGetId([
                'examen_id' => $examenId,
                'alcance' => $data->alcance,
                'texto' => $data->texto,
                'estudiante_id' => $data->estudianteId,
                'motivo' => $data->motivo,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->bitacora->registrar(
                $usuarioId,
                'norma_examen.registrar',
                'normas_examenes',
                (int) $normaId,
                'Examen '.$examenId.'; alcance '.$data->alcance,
            );

            $norma = DB::table('normas_examenes as normas')
                ->leftJoin('students', 'students.id', '=', 'normas.estudiante_id')
                ->where('normas.id', $normaId)
                ->first([
                    'normas.id',
                    'normas.examen_id',
                    'normas.alcance',
                    'normas.texto',
                    'normas.estudiante_id',
                    'normas.motivo',
                    'students.nombre as estudiante_nombre',
                    'students.apellido as estudiante_apellido',
                    'students.codigo_universitario as estudiante_codigo',
                ]);

            return $norma === null ? null : $this->serializar($norma);
        }, 3);
    }

    public function eliminar(
        int $examenId,
        int $normaId,
        int $usuarioId,
    ): ?bool {
        return DB::transaction(function () use (
            $examenId,
            $normaId,
            $usuarioId,
        ): ?bool {
            $examen = DB::table('examenes')
                ->where('id', $examenId)
                ->lockForUpdate()
                ->first(['id']);

            if ($examen === null) {
                return null;
            }

            $norma = DB::table('normas_examenes')
                ->where('examen_id', $examenId)
                ->where('id', $normaId)
                ->lockForUpdate()
                ->first(['id']);

            if ($norma === null) {
                return false;
            }

            DB::table('normas_examenes')->where('id', $normaId)->delete();

            $this->bitacora->registrar(
                $usuarioId,
                'norma_examen.eliminar',
                'normas_examenes',
                $normaId,
                'Examen '.$examenId,
            );

            return true;
        }, 3);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(object $norma): array
    {
        return [
            'id' => $this->integer($this->column($norma, 'id')),
            'examen_id' => $this->integer($this->column($norma, 'examen_id')),
            'alcance' => $this->string($this->column($norma, 'alcance')),
            'texto' => $this->string($this->column($norma, 'texto')),
            'estudiante_id' => $this->nullableInteger($this->column($norma, 'estudiante_id')),
            'motivo' => $this->nullableString($this->column($norma, 'motivo')),
            'estudiante_nombre' => $this->nullableString($this->column($norma, 'estudiante_nombre')),
            'estudiante_apellido' => $this->nullableString($this->column($norma, 'estudiante_apellido')),
            'estudiante_codigo' => $this->nullableString($this->column($norma, 'estudiante_codigo')),
        ];
    }

    private function column(object $row, string $column): mixed
    {
        $values = get_object_vars($row);

        return $values[$column] ?? null;
    }

    private function integer(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new LogicException('La norma contiene un identificador inválido.');
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return $this->integer($value);
    }

    private function string(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        throw new LogicException('La norma contiene un campo de texto inválido.');
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->string($value);
    }
}
