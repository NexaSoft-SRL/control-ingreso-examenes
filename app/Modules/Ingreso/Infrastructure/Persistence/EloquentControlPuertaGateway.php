<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Ingreso\Application\Contracts\ControlPuertaGateway;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final class EloquentControlPuertaGateway implements ControlPuertaGateway
{
    public function __construct(
        private readonly BitacoraGateway $bitacora,
    ) {}

    public function aulas(int $examenId): array
    {
        $filas = DB::table('examen_aula as asignada')
            ->join('aulas as aula', 'aula.id', '=', 'asignada.aula_id')
            ->leftJoin('examen_aula_codigos as codigo', static function (JoinClause $union): void {
                $union->on('codigo.examen_id', '=', 'asignada.examen_id')
                    ->on('codigo.aula_id', '=', 'asignada.aula_id');
            })
            ->where('asignada.examen_id', $examenId)
            ->orderBy('aula.nombre')
            ->get(['asignada.aula_id', 'aula.nombre', 'codigo.codigo']);

        $personal = [];

        foreach (DB::table('examen_aula_personal as p')
            ->join('usuarios as u', 'u.id', '=', 'p.usuario_id')
            ->where('p.examen_id', $examenId)
            ->orderByRaw("case when p.rol = 'a_cargo' then 0 else 1 end")
            ->orderBy('u.nombre')
            ->get(['p.aula_id', 'p.usuario_id', 'p.rol', 'u.nombre']) as $fila) {
            $columnas = get_object_vars($fila);
            $personal[self::entero($columnas['aula_id'] ?? null)][] = [
                'usuario_id' => self::entero($columnas['usuario_id'] ?? null),
                'nombre' => self::texto($columnas['nombre'] ?? null),
                'rol' => self::texto($columnas['rol'] ?? null),
            ];
        }

        $aulas = [];

        foreach ($filas as $fila) {
            $columnas = get_object_vars($fila);
            $aulaId = self::entero($columnas['aula_id'] ?? null);
            $aulas[] = [
                'aula_id' => $aulaId,
                'nombre' => self::texto($columnas['nombre'] ?? null),
                'usa_codigo' => ($columnas['codigo'] ?? null) !== null,
                'personal' => $personal[$aulaId] ?? [],
            ];
        }

        return $aulas;
    }

    public function presentes(int $examenId, int $minutos): array
    {
        $filas = DB::table('presencias_puerta as presencia')
            ->join('examen_aula as asignada', 'asignada.aula_id', '=', 'presencia.aula_id')
            ->join('usuarios as usuario', 'usuario.id', '=', 'presencia.usuario_id')
            ->where('asignada.examen_id', $examenId)
            ->where('presencia.visto_en', '>=', now()->subMinutes($minutos))
            ->orderBy('usuario.nombre')
            ->get(['presencia.aula_id', 'presencia.usuario_id', 'usuario.nombre', 'presencia.visto_en']);

        $presentes = [];

        foreach ($filas as $fila) {
            $columnas = get_object_vars($fila);
            $presentes[] = [
                'aula_id' => self::entero($columnas['aula_id'] ?? null),
                'usuario_id' => self::entero($columnas['usuario_id'] ?? null),
                'nombre' => self::texto($columnas['nombre'] ?? null),
                'visto_en' => self::iso($columnas['visto_en'] ?? null),
            ];
        }

        return $presentes;
    }

    public function marcarPresencia(int $aulaId, int $usuarioId): void
    {
        // Un aula que no existe la rechaza despues la puerta.
        if (! DB::table('aulas')->where('id', $aulaId)->exists()) {
            return;
        }

        DB::table('presencias_puerta')->upsert(
            [['aula_id' => $aulaId, 'usuario_id' => $usuarioId, 'visto_en' => now()]],
            ['aula_id', 'usuario_id'],
            ['visto_en'],
        );
    }

    public function trasladarPersona(int $examenId, int $usuarioId, int $deAulaId, int $aAulaId, int $autorId): string
    {
        return DB::transaction(function () use ($examenId, $usuarioId, $deAulaId, $aAulaId, $autorId): string {
            $origen = $this->nombreDeAula($examenId, $deAulaId);
            $destino = $this->nombreDeAula($examenId, $aAulaId);

            if ($origen === null || $destino === null) {
                return 'AULA_AJENA';
            }

            $fila = DB::table('examen_aula_personal')
                ->where('examen_id', $examenId)
                ->where('aula_id', $deAulaId)
                ->where('usuario_id', $usuarioId)
                ->lockForUpdate()
                ->first(['id', 'rol']);

            if ($fila === null) {
                return 'NO_ESTA';
            }

            $columnas = get_object_vars($fila);
            DB::table('examen_aula_personal')->where('id', $columnas['id'])->delete();

            // El aula no queda sin nadie a cargo: la toma quien mueve.
            if (($columnas['rol'] ?? null) === 'a_cargo') {
                DB::table('examen_aula_personal')
                    ->where('examen_id', $examenId)
                    ->where('aula_id', $deAulaId)
                    ->where('usuario_id', $autorId)
                    ->delete();
                DB::table('examen_aula_personal')->insert([
                    'examen_id' => $examenId,
                    'aula_id' => $deAulaId,
                    'usuario_id' => $autorId,
                    'rol' => 'a_cargo',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $yaEsta = DB::table('examen_aula_personal')
                ->where('examen_id', $examenId)
                ->where('aula_id', $aAulaId)
                ->where('usuario_id', $usuarioId)
                ->exists();

            if (! $yaEsta) {
                DB::table('examen_aula_personal')->insert([
                    'examen_id' => $examenId,
                    'aula_id' => $aAulaId,
                    'usuario_id' => $usuarioId,
                    'rol' => 'apoyo',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $nombre = DB::table('usuarios')->where('id', $usuarioId)->value('nombre');

            $this->bitacora->registrar(
                $autorId,
                'examen.mover_personal',
                'examenes',
                $examenId,
                sprintf(
                    '%s pasó del aula %s al aula %s.',
                    is_string($nombre) ? $nombre : '—',
                    $origen,
                    $destino,
                ),
            );

            return 'OK';
        }, 3);
    }

    public function trasladarEstudiantes(int $examenId, int $deAulaId, int $aAulaId, int $autorId): ?int
    {
        return DB::transaction(function () use ($examenId, $deAulaId, $aAulaId, $autorId): ?int {
            $origen = $this->nombreDeAula($examenId, $deAulaId);
            $destino = $this->nombreDeAula($examenId, $aAulaId);

            if ($origen === null || $destino === null) {
                return null;
            }

            // Los que ya entraron quedan donde entraron.
            $movidos = DB::table('habilitaciones as habilitacion')
                ->where('habilitacion.examen_id', $examenId)
                ->where('habilitacion.aula_id', $deAulaId)
                ->where('habilitacion.habilitado', true)
                ->whereNotExists(static function (Builder $ingreso): void {
                    $ingreso->selectRaw('1')
                        ->from('ingresos')
                        ->whereColumn('ingresos.examen_id', 'habilitacion.examen_id')
                        ->whereColumn('ingresos.estudiante_id', 'habilitacion.estudiante_id');
                })
                ->update(['aula_id' => $aAulaId, 'updated_at' => now()]);

            $this->bitacora->registrar(
                $autorId,
                'habilitacion.mover_aula',
                'examenes',
                $examenId,
                sprintf(
                    '%d %s del aula %s al aula %s.',
                    $movidos,
                    $movidos === 1 ? 'estudiante pasó' : 'estudiantes pasaron',
                    $origen,
                    $destino,
                ),
            );

            return $movidos;
        }, 3);
    }

    private function nombreDeAula(int $examenId, int $aulaId): ?string
    {
        $nombre = DB::table('examen_aula as asignada')
            ->join('aulas as aula', 'aula.id', '=', 'asignada.aula_id')
            ->where('asignada.examen_id', $examenId)
            ->where('asignada.aula_id', $aulaId)
            ->value('aula.nombre');

        return is_string($nombre) ? $nombre : null;
    }

    private static function iso(mixed $instante): ?string
    {
        if (! is_string($instante) || $instante === '') {
            return null;
        }

        return (new DateTimeImmutable(substr($instante, 0, 19), now()->getTimezone()))->format(DATE_ATOM);
    }

    private static function texto(mixed $valor): string
    {
        return is_scalar($valor) ? (string) $valor : '';
    }

    private static function entero(mixed $valor): int
    {
        return is_numeric($valor) ? (int) $valor : 0;
    }
}
