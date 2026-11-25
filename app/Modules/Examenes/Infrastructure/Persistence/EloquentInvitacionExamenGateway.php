<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Examenes\Application\Contracts\InvitacionExamenGateway;
use App\Modules\Examenes\Application\DTOs\InvitacionData;
use App\Modules\Examenes\Application\DTOs\InvitacionDeExamenData;
use App\Modules\Examenes\Application\DTOs\InvitacionRecibidaData;
use App\Modules\Examenes\Domain\Enums\TipoExamen;
use Illuminate\Support\Facades\DB;

/**
 * Las invitaciones a sumar un grupo a un examen de otro docente. Los grupos,
 * docentes y cuentas son de otros modulos: se leen por nombre de tabla.
 */
final class EloquentInvitacionExamenGateway implements InvitacionExamenGateway
{
    use ConvierteFilas;
    use DejaSinHabilitacionAQuienSale;

    public function __construct(
        private readonly BitacoraGateway $bitacora,
    ) {}

    public function pendientesDe(int $usuarioId): array
    {
        $filas = DB::table('invitaciones_examen as invitacion')
            ->join('examenes as examen', 'examen.id', '=', 'invitacion.examen_id')
            ->join('asignaturas as asignatura', 'asignatura.id', '=', 'examen.asignatura_id')
            ->join('grupos as grupo', 'grupo.id', '=', 'invitacion.grupo_id')
            ->join('docentes as docente', 'docente.id', '=', 'grupo.docente_id')
            ->join('users as quien', 'quien.id', '=', 'invitacion.invitado_por')
            ->leftJoin('docentes as docente_quien', 'docente_quien.user_id', '=', 'quien.id')
            ->where('docente.user_id', $usuarioId)
            ->where('invitacion.estado', InvitacionDeExamenData::PENDIENTE)
            ->where('examen.fecha', '>=', now()->toDateString())
            ->orderBy('examen.fecha')
            ->orderBy('examen.hora_inicio')
            ->orderBy('invitacion.id')
            ->get([
                'invitacion.id',
                'invitacion.examen_id',
                'invitacion.grupo_id',
                'examen.tipo',
                'examen.fecha',
                'examen.hora_inicio',
                'examen.duracion_minutos',
                'examen.asignatura_id',
                'asignatura.codigo as asignatura_codigo',
                'asignatura.nombre as asignatura_nombre',
                'grupo.codigo as grupo_codigo',
                'quien.name as invitado_nombre',
                'docente_quien.nombre_completo as invitado_docente',
            ]);

        $examenIds = [];

        foreach ($filas as $fila) {
            $examenIds[] = $this->entero($this->columnas($fila)['examen_id'] ?? null);
        }

        $aulas = $this->aulasPorExamen(array_values(array_unique($examenIds)));
        $invitaciones = [];

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);
            $examenId = $this->entero($columnas['examen_id'] ?? null);
            $tipo = $this->cadena($columnas['tipo'] ?? null);

            $invitaciones[] = new InvitacionRecibidaData(
                id: $this->entero($columnas['id'] ?? null),
                examenId: $examenId,
                tipo: $tipo,
                tipoTexto: TipoExamen::from($tipo)->etiqueta(),
                asignaturaId: $this->entero($columnas['asignatura_id'] ?? null),
                asignaturaCodigo: $this->cadena($columnas['asignatura_codigo'] ?? null),
                asignaturaNombre: $this->cadena($columnas['asignatura_nombre'] ?? null),
                fecha: $this->fecha($columnas['fecha'] ?? null),
                hora: $this->hora($columnas['hora_inicio'] ?? null),
                duracion: $this->entero($columnas['duracion_minutos'] ?? null),
                aulas: $aulas[$examenId] ?? [],
                grupoId: $this->entero($columnas['grupo_id'] ?? null),
                grupoCodigo: $this->cadena($columnas['grupo_codigo'] ?? null),
                invitadoPor: $this->texto($columnas['invitado_docente'] ?? null)
                    ?? $this->cadena($columnas['invitado_nombre'] ?? null),
            );
        }

        return $invitaciones;
    }

    public function encontrar(int $invitacionId): ?InvitacionData
    {
        $fila = DB::table('invitaciones_examen as invitacion')
            ->join('examenes as examen', 'examen.id', '=', 'invitacion.examen_id')
            ->join('grupos as grupo', 'grupo.id', '=', 'invitacion.grupo_id')
            ->leftJoin('docentes as docente', 'docente.id', '=', 'grupo.docente_id')
            ->where('invitacion.id', $invitacionId)
            ->first([
                'invitacion.id',
                'invitacion.examen_id',
                'invitacion.grupo_id',
                'invitacion.estado',
                'examen.tipo',
                'grupo.codigo as grupo_codigo',
                'docente.user_id as docente_cuenta',
            ]);

        if ($fila === null) {
            return null;
        }

        $columnas = $this->columnas($fila);

        return new InvitacionData(
            id: $this->entero($columnas['id'] ?? null),
            examenId: $this->entero($columnas['examen_id'] ?? null),
            grupoId: $this->entero($columnas['grupo_id'] ?? null),
            grupoCodigo: $this->cadena($columnas['grupo_codigo'] ?? null),
            estado: $this->cadena($columnas['estado'] ?? null),
            tipo: $this->cadena($columnas['tipo'] ?? null),
            docenteUsuarioId: $this->enteroONulo($columnas['docente_cuenta'] ?? null),
        );
    }

    public function aceptar(int $invitacionId, int $usuarioId): void
    {
        DB::transaction(function () use ($invitacionId, $usuarioId): void {
            $invitacion = $this->encontrar($invitacionId);

            if ($invitacion === null) {
                return;
            }

            DB::table('examen_grupo')->insertOrIgnore([
                'examen_id' => $invitacion->examenId,
                'grupo_id' => $invitacion->grupoId,
            ]);
            DB::table('invitaciones_examen')->where('id', $invitacionId)->delete();

            $this->bitacora->registrar(
                $usuarioId,
                'examen.aceptar_invitacion',
                'examenes',
                $invitacion->examenId,
                sprintf('Aceptó sumar su grupo %s a %s', $invitacion->grupoCodigo, $this->examen($invitacion->examenId)),
            );
        }, 3);
    }

    public function rechazar(int $invitacionId, int $usuarioId): void
    {
        DB::transaction(function () use ($invitacionId, $usuarioId): void {
            $invitacion = $this->encontrar($invitacionId);

            if ($invitacion === null) {
                return;
            }

            DB::table('invitaciones_examen')->where('id', $invitacionId)->update([
                'estado' => InvitacionDeExamenData::RECHAZADA,
                'respondida_en' => now(),
                'updated_at' => now(),
            ]);

            $this->bitacora->registrar(
                $usuarioId,
                'examen.rechazar_invitacion',
                'examenes',
                $invitacion->examenId,
                sprintf('Rechazó sumar su grupo %s a %s', $invitacion->grupoCodigo, $this->examen($invitacion->examenId)),
            );
        }, 3);
    }

    public function gruposDelDocenteEnExamen(int $examenId, int $usuarioId): array
    {
        $ids = [];

        $filas = DB::table('examen_grupo as incluido')
            ->join('grupos as grupo', 'grupo.id', '=', 'incluido.grupo_id')
            ->join('docentes as docente', 'docente.id', '=', 'grupo.docente_id')
            ->where('incluido.examen_id', $examenId)
            ->where('docente.user_id', $usuarioId)
            ->orderBy('grupo.codigo')
            ->pluck('incluido.grupo_id');

        foreach ($filas as $id) {
            $ids[] = $this->entero($id);
        }

        return $ids;
    }

    public function salir(int $examenId, array $grupoIds, int $usuarioId): void
    {
        if ($grupoIds === []) {
            return;
        }

        DB::transaction(function () use ($examenId, $grupoIds, $usuarioId): void {
            $codigos = [];

            foreach (DB::table('grupos')->whereIn('id', $grupoIds)->orderBy('codigo')->pluck('codigo') as $codigo) {
                $codigos[] = $this->cadena($codigo);
            }

            DB::table('examen_grupo')
                ->where('examen_id', $examenId)
                ->whereIn('grupo_id', $grupoIds)
                ->delete();

            $this->limpiarHabilitacionesDelExamen($examenId);

            $this->bitacora->registrar(
                $usuarioId,
                'examen.salir',
                'examenes',
                $examenId,
                sprintf('Sacó su grupo %s de %s', implode(', ', $codigos), $this->examen($examenId)),
            );
        }, 3);
    }

    /**
     * «Primer parcial de Calculo I del 2026-11-30 a las 08:15».
     */
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

    /**
     * @param  list<int>  $examenIds
     * @return array<int, list<string>>
     */
    private function aulasPorExamen(array $examenIds): array
    {
        if ($examenIds === []) {
            return [];
        }

        $porExamen = [];

        $filas = DB::table('examen_aula as asignada')
            ->join('aulas as aula', 'aula.id', '=', 'asignada.aula_id')
            ->whereIn('asignada.examen_id', $examenIds)
            ->orderBy('asignada.id')
            ->get(['asignada.examen_id', 'aula.nombre']);

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);

            $porExamen[$this->entero($columnas['examen_id'] ?? null)][] = $this->cadena($columnas['nombre'] ?? null);
        }

        return $porExamen;
    }
}
