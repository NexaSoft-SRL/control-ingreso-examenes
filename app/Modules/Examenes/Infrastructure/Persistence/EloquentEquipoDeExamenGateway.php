<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Examenes\Application\Contracts\EquipoDeExamenGateway;
use App\Modules\Examenes\Domain\Enums\TipoExamen;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * El equipo del examen, leido y escrito por nombre de tabla: las aulas y el
 * apoyo son del modulo, las cuentas y los roles de Administracion.
 */
final class EloquentEquipoDeExamenGateway implements EquipoDeExamenGateway
{
    use ConvierteFilas;

    private const ROL_AUXILIAR = 'Auxiliar';

    public function __construct(
        private readonly BitacoraGateway $bitacora,
    ) {}

    public function esAuxiliarActivo(int $usuarioId): bool
    {
        return DB::table('users as cuenta')
            ->leftJoin('roles as rol', 'rol.id', '=', 'cuenta.role_id')
            ->leftJoin('auxiliares as auxiliar', 'auxiliar.user_id', '=', 'cuenta.id')
            ->where('cuenta.id', $usuarioId)
            ->where('cuenta.is_active', true)
            ->where(function (Builder $es): void {
                $es->where('rol.name', self::ROL_AUXILIAR)->orWhereNotNull('auxiliar.id');
            })
            ->exists();
    }

    public function tieneAulas(int $examenId): bool
    {
        return DB::table('examen_aula')->where('examen_id', $examenId)->exists();
    }

    public function estaEnElEquipo(int $examenId, int $usuarioId): bool
    {
        return $this->estaACargo($examenId, $usuarioId)
            || DB::table('examen_aula_apoyo as apoyo')
                ->join('examen_aula as asignada', 'asignada.id', '=', 'apoyo.examen_aula_id')
                ->where('asignada.examen_id', $examenId)
                ->where('apoyo.user_id', $usuarioId)
                ->exists();
    }

    public function estaACargo(int $examenId, int $usuarioId): bool
    {
        return DB::table('examen_aula')
            ->where('examen_id', $examenId)
            ->where('encargado_id', $usuarioId)
            ->exists();
    }

    public function agregadoPor(int $examenId, int $usuarioId): ?int
    {
        $valor = DB::table('examen_aula_apoyo as apoyo')
            ->join('examen_aula as asignada', 'asignada.id', '=', 'apoyo.examen_aula_id')
            ->where('asignada.examen_id', $examenId)
            ->where('apoyo.user_id', $usuarioId)
            ->whereNotNull('apoyo.agregado_por')
            ->orderBy('apoyo.id')
            ->value('apoyo.agregado_por');

        return $this->enteroONulo($valor);
    }

    public function sumar(int $examenId, int $usuarioId, int $porId): bool
    {
        return DB::transaction(function () use ($examenId, $usuarioId, $porId): bool {
            $aulas = DB::table('examen_aula as asignada')
                ->where('asignada.examen_id', $examenId)
                ->where('asignada.encargado_id', '<>', $usuarioId)
                ->whereNotExists(function (Builder $apoyo) use ($usuarioId): void {
                    $apoyo->selectRaw('1')
                        ->from('examen_aula_apoyo as ya')
                        ->whereColumn('ya.examen_aula_id', 'asignada.id')
                        ->where('ya.user_id', $usuarioId);
                })
                ->pluck('asignada.id')
                ->all();

            foreach ($aulas as $aulaDelExamenId) {
                DB::table('examen_aula_apoyo')->insert([
                    'examen_aula_id' => $this->entero($aulaDelExamenId),
                    'user_id' => $usuarioId,
                    'agregado_por' => $porId,
                ]);
            }

            if ($aulas === []) {
                return false;
            }

            $this->bitacora->registrar(
                $porId,
                'examen.equipo_sumar',
                'examenes',
                $examenId,
                sprintf('Sumó a %s al equipo de %s', $this->cuenta($usuarioId), $this->examen($examenId)),
            );

            return true;
        }, 3);
    }

    public function quitar(int $examenId, int $usuarioId, int $porId): void
    {
        DB::transaction(function () use ($examenId, $usuarioId, $porId): void {
            $filas = DB::table('examen_aula as asignada')
                ->where('asignada.examen_id', $examenId)
                ->pluck('asignada.id')
                ->all();

            DB::table('examen_aula_apoyo')
                ->whereIn('examen_aula_id', $filas)
                ->where('user_id', $usuarioId)
                ->delete();

            $this->bitacora->registrar(
                $porId,
                'examen.equipo_quitar',
                'examenes',
                $examenId,
                sprintf('Sacó a %s del equipo de %s', $this->cuenta($usuarioId), $this->examen($examenId)),
            );
        }, 3);
    }

    private function cuenta(int $usuarioId): string
    {
        return $this->texto(DB::table('users')->where('id', $usuarioId)->value('name')) ?? 'una cuenta';
    }

    private function examen(int $examenId): string
    {
        $fila = DB::table('examenes as examen')
            ->join('asignaturas as asignatura', 'asignatura.id', '=', 'examen.asignatura_id')
            ->where('examen.id', $examenId)
            ->first(['examen.tipo', 'examen.fecha', 'examen.hora_inicio', 'asignatura.nombre']);

        if ($fila === null) {
            return 'un examen';
        }

        $columnas = $this->columnas($fila);

        return sprintf(
            '%s de %s del %s a las %s.',
            TipoExamen::from($this->cadena($columnas['tipo'] ?? null))->etiqueta(),
            $this->cadena($columnas['nombre'] ?? null),
            $this->fecha($columnas['fecha'] ?? null),
            $this->hora($columnas['hora_inicio'] ?? null),
        );
    }
}
