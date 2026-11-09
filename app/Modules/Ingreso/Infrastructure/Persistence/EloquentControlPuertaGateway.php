<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Ingreso\Application\Contracts\ControlPuertaGateway;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentControlPuertaGateway implements ControlPuertaGateway
{
    use LeeFilas;

    public function __construct(
        private readonly BitacoraGateway $bitacora,
    ) {}

    public function aulas(int $examenId): array
    {
        $asignadas = DB::table('examen_aula as asignada')
            ->join('aulas as aula', 'aula.id', '=', 'asignada.aula_id')
            ->join('users as encargado', 'encargado.id', '=', 'asignada.encargado_id')
            ->where('asignada.examen_id', $examenId)
            ->orderBy('aula.nombre')
            ->get([
                'asignada.id',
                'asignada.aula_id',
                'aula.nombre',
                'asignada.codigo_acceso',
                'asignada.encargado_id',
                'encargado.name as encargado',
            ]);

        $apoyo = [];

        foreach (DB::table('examen_aula_apoyo as apoyo')
            ->join('users as usuario', 'usuario.id', '=', 'apoyo.user_id')
            ->join('examen_aula as asignada', 'asignada.id', '=', 'apoyo.examen_aula_id')
            ->where('asignada.examen_id', $examenId)
            ->orderBy('usuario.name')
            ->get(['apoyo.examen_aula_id', 'apoyo.user_id', 'usuario.name']) as $fila) {
            $columnas = $this->columnas($fila);
            $apoyo[$this->entero($columnas['examen_aula_id'] ?? null)][] = [
                'usuario_id' => $this->entero($columnas['user_id'] ?? null),
                'nombre' => $this->texto($columnas['name'] ?? null) ?? '',
                'rol' => 'apoyo',
            ];
        }

        $aulas = [];

        foreach ($asignadas as $fila) {
            $columnas = $this->columnas($fila);
            $aulas[] = [
                'aula_id' => $this->entero($columnas['aula_id'] ?? null),
                'nombre' => $this->texto($columnas['nombre'] ?? null) ?? '',
                'usa_codigo' => ($columnas['codigo_acceso'] ?? null) !== null,
                'personal' => [
                    [
                        'usuario_id' => $this->entero($columnas['encargado_id'] ?? null),
                        'nombre' => $this->texto($columnas['encargado'] ?? null) ?? '',
                        'rol' => 'encargado',
                    ],
                    ...($apoyo[$this->entero($columnas['id'] ?? null)] ?? []),
                ],
            ];
        }

        return $aulas;
    }

    public function trasladarPersona(int $examenId, int $usuarioId, int $deAulaId, int $aAulaId, int $autorId): string
    {
        return DB::transaction(function () use ($examenId, $usuarioId, $deAulaId, $aAulaId, $autorId): string {
            $origen = $this->asignada($examenId, $deAulaId);
            $destino = $this->asignada($examenId, $aAulaId);

            if ($origen === null || $destino === null) {
                return 'AULA_AJENA';
            }

            $estaba = false;

            if ($this->entero($origen['encargado_id'] ?? null) === $usuarioId) {
                // El aula no queda sin nadie a cargo: la toma quien mueve.
                DB::table('examen_aula')->where('id', $origen['id'])->update(['encargado_id' => $autorId]);
                $estaba = true;
            }

            $estaba = DB::table('examen_aula_apoyo')
                ->where('examen_aula_id', $origen['id'])
                ->where('user_id', $usuarioId)
                ->delete() > 0 || $estaba;

            if (! $estaba) {
                return 'NO_ESTA';
            }

            $yaEsta = $this->entero($destino['encargado_id'] ?? null) === $usuarioId
                || DB::table('examen_aula_apoyo')
                    ->where('examen_aula_id', $destino['id'])
                    ->where('user_id', $usuarioId)
                    ->exists();

            if (! $yaEsta) {
                DB::table('examen_aula_apoyo')->insert([
                    'examen_aula_id' => $destino['id'],
                    'user_id' => $usuarioId,
                ]);
            }

            $this->bitacora->registrar(
                $autorId,
                'examen.mover_personal',
                'examen_aula',
                $this->entero($destino['id'] ?? null),
                sprintf(
                    '%s pasó del aula %s al aula %s.',
                    $this->texto(DB::table('users')->where('id', $usuarioId)->value('name')) ?? '—',
                    $this->texto($origen['nombre'] ?? null) ?? '',
                    $this->texto($destino['nombre'] ?? null) ?? '',
                ),
            );

            return 'OK';
        }, 3);
    }

    public function trasladarEstudiantes(int $examenId, int $deAulaId, int $aAulaId, int $autorId): ?int
    {
        return DB::transaction(function () use ($examenId, $deAulaId, $aAulaId, $autorId): ?int {
            $origen = $this->asignada($examenId, $deAulaId);
            $destino = $this->asignada($examenId, $aAulaId);

            if ($origen === null || $destino === null) {
                return null;
            }

            // Los que ya entraron quedan donde entraron.
            $movidos = DB::table('habilitaciones as habilitacion')
                ->where('habilitacion.examen_id', $examenId)
                ->where('habilitacion.aula_id', $deAulaId)
                ->where('habilitacion.habilitado', true)
                ->whereNotExists(function (Builder $ingreso): void {
                    $ingreso->selectRaw('1')
                        ->from('ingresos')
                        ->whereColumn('ingresos.examen_id', 'habilitacion.examen_id')
                        ->whereColumn('ingresos.estudiante_id', 'habilitacion.estudiante_id');
                })
                ->update(['aula_id' => $aAulaId, 'updated_at' => now()]);

            $this->bitacora->registrar(
                $autorId,
                'habilitacion.mover_aula',
                // Sobre el examen: asi lo encuentra su bitacora.
                'examenes',
                $examenId,
                sprintf(
                    '%d %s del aula %s al aula %s.',
                    $movidos,
                    $movidos === 1 ? 'estudiante pasó' : 'estudiantes pasaron',
                    $this->texto($origen['nombre'] ?? null) ?? '',
                    $this->texto($destino['nombre'] ?? null) ?? '',
                ),
            );

            return $movidos;
        }, 3);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function asignada(int $examenId, int $aulaId): ?array
    {
        $fila = DB::table('examen_aula as asignada')
            ->join('aulas as aula', 'aula.id', '=', 'asignada.aula_id')
            ->where('asignada.examen_id', $examenId)
            ->where('asignada.aula_id', $aulaId)
            ->lockForUpdate()
            ->first(['asignada.id', 'asignada.encargado_id', 'aula.nombre']);

        return $fila === null ? null : $this->columnas($fila);
    }
}
