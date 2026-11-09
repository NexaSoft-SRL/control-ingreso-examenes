<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Infrastructure\Persistence;

use App\Modules\Examenes\Application\Contracts\AlcanceExamenGateway;
use App\Modules\Examenes\Application\DTOs\AulaAtendidaData;
use App\Modules\Examenes\Domain\Models\Examen;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Los grupos y los docentes son de otro modulo: se leen por nombre de
 * tabla, sin importar sus modelos.
 */
final class EloquentAlcanceExamenGateway implements AlcanceExamenGateway
{
    public function esDocenteDelExamen(int $usuarioId, int $examenId): bool
    {
        if ($this->loRegistro($usuarioId, $examenId)) {
            return true;
        }

        return DB::table('examen_grupo as incluido')
            ->join('grupos as grupo', 'grupo.id', '=', 'incluido.grupo_id')
            ->join('docentes as docente', 'docente.id', '=', 'grupo.docente_id')
            ->where('incluido.examen_id', $examenId)
            ->where('docente.user_id', $usuarioId)
            ->exists();
    }

    public function loRegistro(int $usuarioId, int $examenId): bool
    {
        return Examen::where('id', $examenId)
            ->where('creado_por', $usuarioId)
            ->exists();
    }

    public function atiendeAula(int $usuarioId, int $examenId, int $aulaId): bool
    {
        return $this->asignaciones($usuarioId)
            ->where('asignada.examen_id', $examenId)
            ->where('asignada.aula_id', $aulaId)
            ->exists();
    }

    /**
     * @return list<AulaAtendidaData>
     */
    public function aulasQueAtiende(int $usuarioId, string $fecha): array
    {
        $filas = $this->asignaciones($usuarioId)
            ->join('examenes as examen', 'examen.id', '=', 'asignada.examen_id')
            ->whereDate('examen.fecha', $fecha)
            ->orderBy('examen.hora_inicio')
            ->orderBy('asignada.id')
            ->select([
                'asignada.examen_id',
                'asignada.aula_id',
                'asignada.encargado_id',
            ])
            ->get();

        $aulas = [];

        foreach ($filas as $fila) {
            /** @var array<string, mixed> $columnas */
            $columnas = get_object_vars($fila);

            $aulas[] = new AulaAtendidaData(
                examenId: $this->entero($columnas['examen_id'] ?? null),
                aulaId: $this->entero($columnas['aula_id'] ?? null),
                esEncargado: $this->entero($columnas['encargado_id'] ?? null) === $usuarioId,
            );
        }

        return $aulas;
    }

    /**
     * Las aulas de examen donde la cuenta es la encargada o esta de apoyo.
     */
    private function asignaciones(int $usuarioId): Builder
    {
        return DB::table('examen_aula as asignada')
            ->where(function (Builder $consulta) use ($usuarioId): void {
                $consulta
                    ->where('asignada.encargado_id', $usuarioId)
                    ->orWhereExists(function (Builder $apoyo) use ($usuarioId): void {
                        $apoyo->selectRaw('1')
                            ->from('examen_aula_apoyo as apoyo')
                            ->whereColumn('apoyo.examen_aula_id', 'asignada.id')
                            ->where('apoyo.user_id', $usuarioId);
                    });
            });
    }

    private function entero(mixed $valor): int
    {
        if (is_int($valor)) {
            return $valor;
        }

        if (is_string($valor) && preg_match('/^-?\d+$/', $valor) === 1) {
            return (int) $valor;
        }

        throw new LogicException('La columna no contiene un entero válido.');
    }
}
