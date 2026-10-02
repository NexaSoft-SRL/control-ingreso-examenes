<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Habilitacion\Application\Contracts\HabilitacionGateway;
use App\Modules\Habilitacion\Application\DTOs\ConsultaHabilitacionData;
use App\Modules\Habilitacion\Application\DTOs\EstudianteHabilitacionData;
use App\Modules\Habilitacion\Application\DTOs\ExamenHabilitacionData;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use LogicException;

final class EloquentHabilitacionGateway implements HabilitacionGateway
{
    public function __construct(
        private readonly BitacoraGateway $bitacora,
    ) {}

    public function existeExamen(int $examenId): bool
    {
        return DB::table('examenes')->where('id', $examenId)->exists();
    }

    /** @return list<ExamenHabilitacionData> */
    public function listarExamenes(): array
    {
        $filas = DB::table('examenes')
            ->join('grupos_asignatura', 'grupos_asignatura.id', '=', 'examenes.grupo_id')
            ->join('asignaturas', 'asignaturas.id', '=', 'grupos_asignatura.asignatura_id')
            ->select([
                'examenes.id',
                'examenes.nombre',
                'examenes.fecha',
                'asignaturas.codigo as asignatura_codigo',
                'grupos_asignatura.codigo_grupo',
            ])
            ->orderBy('examenes.fecha')
            ->orderBy('asignaturas.codigo')
            ->orderBy('examenes.nombre')
            ->get();

        $examenes = [];

        foreach ($filas as $examen) {
            $examenes[] = new ExamenHabilitacionData(
                self::entero($examen->id),
                self::texto($examen->nombre),
                self::texto($examen->fecha),
                self::texto($examen->asignatura_codigo),
                self::texto($examen->codigo_grupo),
            );
        }

        return $examenes;
    }

    /** @return list<EstudianteHabilitacionData> */
    public function listarEstudiantes(int $examenId): array
    {
        $filas = $this->consultaEstudiantes($examenId)->get();
        $estudiantes = [];

        foreach ($filas as $estudiante) {
            $estudiantes[] = new EstudianteHabilitacionData(
                self::entero($estudiante->id),
                self::textoOpcional($estudiante->codigo_universitario),
                self::texto($estudiante->ci),
                self::texto($estudiante->nombre),
                self::texto($estudiante->apellido),
                self::textoOpcional($estudiante->carrera),
                self::texto($estudiante->condicion),
                self::textoOpcional($estudiante->motivo),
                self::textoOpcional($estudiante->registrado_por),
                self::fechaIso($estudiante->fecha_habilitacion),
            );
        }

        return $estudiantes;
    }

    /** @return list<ConsultaHabilitacionData> */
    public function consultarPorIdentificador(int $examenId, string $identificador): array
    {
        // En la puerta se teclea lo que el estudiante muestre: su código
        // universitario o su documento. Las dos columnas tienen índice único.
        $filas = $this->consultaEstudiantes($examenId)
            ->where(function (Builder $consulta) use ($identificador): void {
                $consulta->where('students.codigo_universitario', $identificador)
                    ->orWhere('students.ci', $identificador);
            })
            ->get();

        $coincidencias = [];

        foreach ($filas as $estudiante) {
            $coincidencias[] = new ConsultaHabilitacionData(
                self::entero($estudiante->id),
                self::textoOpcional($estudiante->codigo_universitario),
                self::texto($estudiante->ci),
                self::texto($estudiante->nombre),
                self::texto($estudiante->apellido),
                self::textoOpcional($estudiante->carrera),
                self::texto($estudiante->condicion),
                self::textoOpcional($estudiante->motivo),
                // La distribución de estudiantes por ambiente llega con la
                // HU-14: hasta entonces nadie tiene uno asignado.
                null,
                self::textoOpcional($estudiante->registrado_por),
                self::fechaIso($estudiante->fecha_habilitacion),
            );
        }

        return $coincidencias;
    }

    /** @param list<int> $estudianteIds */
    public function registrarCondiciones(
        int $examenId,
        array $estudianteIds,
        string $condicion,
        ?string $motivo,
        int $usuarioId,
    ): void {
        $ahora = now();

        DB::transaction(function () use ($examenId, $estudianteIds, $condicion, $motivo, $usuarioId, $ahora): void {
            $filas = array_map(static fn (int $estudianteId): array => [
                'examen_id' => $examenId,
                'estudiante_id' => $estudianteId,
                'estado' => $condicion,
                'motivo' => $motivo,
                'usuario_id' => $usuarioId,
                'fecha_habilitacion' => $ahora,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ], $estudianteIds);

            DB::table('habilitaciones_examen')->upsert(
                $filas,
                ['examen_id', 'estudiante_id'],
                ['estado', 'motivo', 'usuario_id', 'fecha_habilitacion', 'updated_at'],
            );

            foreach ($estudianteIds as $estudianteId) {
                $habilitacionId = DB::table('habilitaciones_examen')
                    ->where('examen_id', $examenId)
                    ->where('estudiante_id', $estudianteId)
                    ->value('id');

                $this->bitacora->registrar(
                    $usuarioId,
                    $condicion === 'HABILITADO'
                        ? 'habilitacion.estudiante.habilitar'
                        : 'habilitacion.estudiante.inhabilitar',
                    'habilitaciones_examen',
                    self::entero($habilitacionId),
                    self::descripcionBitacora($condicion, $estudianteId, $examenId, $motivo),
                );
            }
        });
    }

    private function consultaEstudiantes(int $examenId): Builder
    {
        return DB::table('students')
            ->leftJoin('habilitaciones_examen as habilitacion', function (JoinClause $join) use ($examenId): void {
                $join->on('habilitacion.estudiante_id', '=', 'students.id')
                    ->where('habilitacion.examen_id', '=', $examenId);
            })
            ->leftJoin('usuarios', 'usuarios.id', '=', 'habilitacion.usuario_id')
            ->where('students.activo', true)
            ->select([
                'students.id',
                'students.codigo_universitario',
                'students.ci',
                'students.nombre',
                'students.apellido',
                'students.carrera',
                DB::raw("COALESCE(habilitacion.estado, 'NO_HABILITADO') as condicion"),
                'habilitacion.motivo',
                'usuarios.nombre as registrado_por',
                'habilitacion.fecha_habilitacion',
            ])
            ->orderBy('students.apellido')
            ->orderBy('students.nombre');
    }

    private static function descripcionBitacora(string $condicion, int $estudianteId, int $examenId, ?string $motivo): string
    {
        $descripcion = sprintf(
            'Condición %s registrada para el estudiante %d en el examen %d.',
            $condicion,
            $estudianteId,
            $examenId,
        );

        // HU-12: la bitácora conserva el motivo de cada inhabilitación,
        // aunque después la condición cambie.
        if ($motivo === null) {
            return $descripcion;
        }

        return $descripcion.' Motivo: '.$motivo;
    }

    /**
     * La constancia de cuándo se registró viaja con su zona horaria, para
     * que el navegador la muestre en hora local sin adivinar.
     */
    private static function fechaIso(mixed $valor): ?string
    {
        $texto = self::textoOpcional($valor);

        if ($texto === null) {
            return null;
        }

        return (new DateTimeImmutable($texto, new DateTimeZone('UTC')))->format(DATE_ATOM);
    }

    private static function textoOpcional(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        return self::texto($valor);
    }

    private static function entero(mixed $valor): int
    {
        if (is_int($valor)) {
            return $valor;
        }

        if (is_string($valor) && ctype_digit($valor)) {
            return (int) $valor;
        }

        throw new LogicException('La base de datos devolvió un identificador inválido.');
    }

    private static function texto(mixed $valor): string
    {
        if (is_string($valor)) {
            return $valor;
        }

        if (is_int($valor) || is_float($valor)) {
            return (string) $valor;
        }

        throw new LogicException('La base de datos devolvió un texto inválido.');
    }
}
