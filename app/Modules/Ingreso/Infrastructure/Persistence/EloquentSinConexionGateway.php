<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Ingreso\Application\Contracts\SinConexionGateway;
use App\Modules\Ingreso\Application\DTOs\OperadorData;
use App\Modules\Ingreso\Domain\Enums\TipoIrregularidad;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final class EloquentSinConexionGateway implements SinConexionGateway
{
    use LeeFilas;

    public function instantanea(array $examenIds): array
    {
        if ($examenIds === []) {
            return ['estudiantes' => [], 'claves' => []];
        }

        $inscritos = DB::table('examen_grupo as incluido')
            ->join('inscripciones as inscripcion', function (JoinClause $union): void {
                $union->on('inscripcion.grupo_id', '=', 'incluido.grupo_id')->whereNull('inscripcion.baja_en');
            })
            ->join('estudiantes as estudiante', 'estudiante.id', '=', 'inscripcion.estudiante_id')
            ->whereIn('incluido.examen_id', $examenIds)
            ->where('estudiante.activo', true)
            ->distinct()
            ->orderBy('estudiante.apellidos')
            ->orderBy('estudiante.nombres')
            ->get([
                'incluido.examen_id',
                'estudiante.id',
                'estudiante.codigo_universitario',
                'estudiante.apellidos',
                'estudiante.nombres',
            ]);

        $condiciones = [];

        foreach (DB::table('habilitaciones as h')
            ->leftJoin('aulas as aula', 'aula.id', '=', 'h.aula_id')
            ->whereIn('h.examen_id', $examenIds)
            ->get(['h.examen_id', 'h.estudiante_id', 'h.habilitado', 'h.motivo', 'h.aula_id', 'aula.nombre as aula']) as $fila) {
            $c = $this->columnas($fila);
            $condiciones[$this->entero($c['examen_id'] ?? null).'|'.$this->entero($c['estudiante_id'] ?? null)] = [
                'habilitado' => (bool) ($c['habilitado'] ?? false),
                'motivo' => $this->texto($c['motivo'] ?? null),
                'aula_id' => $this->enteroONulo($c['aula_id'] ?? null),
                'aula' => $this->texto($c['aula'] ?? null),
            ];
        }

        $ingresos = [];

        foreach (DB::table('ingresos_vigentes as ingreso')
            ->join('aulas as aula', 'aula.id', '=', 'ingreso.aula_id')
            ->leftJoin('incidencias as expulsion', function (JoinClause $union): void {
                $union->on('expulsion.ingreso_id', '=', 'ingreso.id')->where('expulsion.tipo', '=', 'EXPULSION');
            })
            ->whereIn('ingreso.examen_id', $examenIds)
            ->get(['ingreso.examen_id', 'ingreso.estudiante_id', 'ingreso.aula_id', 'ingreso.registrado_en', 'aula.nombre as aula', 'expulsion.id as expulsion']) as $fila) {
            $c = $this->columnas($fila);
            $ingresos[$this->entero($c['examen_id'] ?? null).'|'.$this->entero($c['estudiante_id'] ?? null)] = [
                'aula_id' => $this->entero($c['aula_id'] ?? null),
                'aula' => $this->texto($c['aula'] ?? null) ?? '',
                'hora' => $this->hora($this->texto($c['registrado_en'] ?? null)),
                'expulsado' => $this->enteroONulo($c['expulsion'] ?? null) !== null,
            ];
        }

        $estudiantes = [];

        foreach ($inscritos as $fila) {
            $c = $this->columnas($fila);
            $id = $this->entero($c['id'] ?? null);
            $examenId = $this->entero($c['examen_id'] ?? null);
            $clave = $examenId.'|'.$id;

            $estudiantes[$id] ??= [
                'id' => $id,
                'codigo' => $this->texto($c['codigo_universitario'] ?? null) ?? '',
                'nombre' => trim(($this->texto($c['apellidos'] ?? null) ?? '').' '.($this->texto($c['nombres'] ?? null) ?? '')),
                'examenes' => [],
            ];

            $estudiantes[$id]['examenes'][] = [
                'examen_id' => $examenId,
                // Sin fila, «sin revisar»: la puerta lo trata como no habilitado.
                'condicion' => $condiciones[$clave] ?? null,
                'ingreso' => $ingresos[$clave] ?? null,
            ];
        }

        $claves = [];

        foreach (DB::table('codigos_qr')->whereIn('examen_id', $examenIds)->get(['examen_id', 'estudiante_id', 'token']) as $fila) {
            $c = $this->columnas($fila);
            $token = $this->texto($c['token'] ?? null);

            if ($token !== null) {
                $claves[hash('sha256', $token)] = [
                    'examen_id' => $this->entero($c['examen_id'] ?? null),
                    'estudiante_id' => $this->entero($c['estudiante_id'] ?? null),
                ];
            }
        }

        return ['estudiantes' => array_values($estudiantes), 'claves' => $claves];
    }

    public function abrirSesion(string $dispositivo, int $aulaId, OperadorData $operador, string $instantaneaEn, array $examenIds): int
    {
        $existente = $this->sesion($dispositivo, $aulaId, $instantaneaEn);

        if ($existente !== null) {
            return $existente;
        }

        return $this->entero(DB::table('sesiones_sin_conexion')->insertGetId([
            'dispositivo' => $dispositivo,
            'aula_id' => $aulaId,
            'user_id' => $operador->usuarioId,
            'acceso_aula_id' => $operador->accesoAulaId,
            'operador' => $operador->nombre,
            'instantanea_en' => $instantaneaEn,
            'examenes' => json_encode($examenIds, JSON_THROW_ON_ERROR),
            'estado' => 'DESCARGADA',
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    public function sesion(string $dispositivo, int $aulaId, string $instantaneaEn): ?int
    {
        $id = DB::table('sesiones_sin_conexion')
            ->where('dispositivo', $dispositivo)
            ->where('aula_id', $aulaId)
            ->where('instantanea_en', $instantaneaEn)
            ->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    public function eventoAplicado(string $uid): bool
    {
        return DB::table('eventos_sin_conexion')->where('uid', $uid)->exists();
    }

    public function guardarEvento(int $sesionId, string $uid, string $hora, string $medio, string $resultado, ?int $examenId, ?int $estudianteId, ?int $ingresoId): void
    {
        DB::table('eventos_sin_conexion')->insert([
            'sesion_id' => $sesionId,
            'uid' => $uid,
            'examen_id' => $examenId,
            'estudiante_id' => $estudianteId,
            'hora' => $hora,
            'medio' => $medio,
            'resultado' => $resultado,
            'ingreso_id' => $ingresoId,
            'created_at' => now(),
        ]);
    }

    public function cerrarSesion(int $sesionId, int $eventos, string $ahora): void
    {
        DB::table('sesiones_sin_conexion')->where('id', $sesionId)->update([
            'estado' => 'SINCRONIZADA',
            'sincronizada_en' => $ahora,
            'eventos' => DB::raw('eventos + '.$eventos),
            'updated_at' => now(),
        ]);
    }

    public function registrarIrregularidad(int $examenId, ?int $sesionId, TipoIrregularidad $tipo, string $detalle, ?int $estudianteId, ?int $aulaId, ?array $datos): int
    {
        return $this->entero(DB::table('irregularidades_sin_conexion')->insertGetId([
            'examen_id' => $examenId,
            'sesion_id' => $sesionId,
            'tipo' => $tipo->value,
            'gravedad' => $tipo->gravedad(),
            'estudiante_id' => $estudianteId,
            'aula_id' => $aulaId,
            'detalle' => $detalle,
            'datos' => $datos === null ? null : json_encode($datos, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    public function irregularidades(int $examenId): array
    {
        $filas = DB::table('irregularidades_sin_conexion as irregularidad')
            ->leftJoin('estudiantes as estudiante', 'estudiante.id', '=', 'irregularidad.estudiante_id')
            ->leftJoin('aulas as aula', 'aula.id', '=', 'irregularidad.aula_id')
            ->leftJoin('users as quien', 'quien.id', '=', 'irregularidad.resuelta_por')
            ->where('irregularidad.examen_id', $examenId)
            ->orderByRaw("case irregularidad.estado when 'PENDIENTE' then 0 else 1 end")
            ->orderByRaw("case irregularidad.gravedad when 'ALTA' then 0 when 'MEDIA' then 1 else 2 end")
            ->orderBy('irregularidad.id')
            ->get([
                'irregularidad.id',
                'irregularidad.tipo',
                'irregularidad.gravedad',
                'irregularidad.detalle',
                'irregularidad.datos',
                'irregularidad.estado',
                'irregularidad.nota',
                'irregularidad.resuelta_en',
                'irregularidad.created_at',
                'estudiante.codigo_universitario',
                'estudiante.apellidos',
                'estudiante.nombres',
                'aula.nombre as aula',
                'quien.name as resuelta_por',
            ]);

        $lista = [];

        foreach ($filas as $fila) {
            $c = $this->columnas($fila);
            $tipo = TipoIrregularidad::tryFrom($this->texto($c['tipo'] ?? null) ?? '');
            $datos = $this->texto($c['datos'] ?? null);
            $codigo = $this->texto($c['codigo_universitario'] ?? null);

            $lista[] = [
                'id' => $this->entero($c['id'] ?? null),
                'tipo' => $tipo?->value,
                'tipo_texto' => $tipo?->etiqueta() ?? $this->texto($c['tipo'] ?? null),
                'gravedad' => $this->texto($c['gravedad'] ?? null),
                'estudiante' => $codigo === null
                    ? null
                    : trim(($this->texto($c['apellidos'] ?? null) ?? '').' '.($this->texto($c['nombres'] ?? null) ?? '')).' · '.$codigo,
                'aula' => $this->texto($c['aula'] ?? null),
                'detalle' => $this->texto($c['detalle'] ?? null),
                'datos' => $datos === null ? null : json_decode($datos, true),
                'estado' => $this->texto($c['estado'] ?? null),
                'nota' => $this->texto($c['nota'] ?? null),
                'resuelta_por' => $this->texto($c['resuelta_por'] ?? null),
                'resuelta_en' => $this->texto($c['resuelta_en'] ?? null),
                'creada_en' => $this->texto($c['created_at'] ?? null),
            ];
        }

        return $lista;
    }

    public function sesionesDelExamen(int $examenId): array
    {
        $filas = DB::table('sesiones_sin_conexion as sesion')
            ->join('aulas as aula', 'aula.id', '=', 'sesion.aula_id')
            ->leftJoin('users as usuario', 'usuario.id', '=', 'sesion.user_id')
            ->whereJsonContains('sesion.examenes', $examenId)
            ->orderBy('sesion.instantanea_en')
            ->orderBy('sesion.id')
            ->get([
                'sesion.id',
                'sesion.dispositivo',
                'sesion.instantanea_en',
                'sesion.sincronizada_en',
                'sesion.eventos',
                'sesion.estado',
                'sesion.operador',
                'aula.nombre as aula',
                'usuario.name as usuario',
            ]);

        $lista = [];

        foreach ($filas as $fila) {
            $c = $this->columnas($fila);
            $lista[] = [
                'id' => $this->entero($c['id'] ?? null),
                // Solo el final: alcanza para distinguir celulares.
                'dispositivo' => substr($this->texto($c['dispositivo'] ?? null) ?? '', -6),
                'aula' => $this->texto($c['aula'] ?? null),
                'operador' => $this->texto($c['usuario'] ?? null) ?? $this->texto($c['operador'] ?? null),
                'descargada_en' => $this->texto($c['instantanea_en'] ?? null),
                'sincronizada_en' => $this->texto($c['sincronizada_en'] ?? null),
                'eventos' => $this->entero($c['eventos'] ?? 0),
                'estado' => $this->texto($c['estado'] ?? null),
            ];
        }

        return $lista;
    }

    public function examenDeIrregularidad(int $irregularidadId): ?int
    {
        $id = DB::table('irregularidades_sin_conexion')->where('id', $irregularidadId)->value('examen_id');

        return is_numeric($id) ? (int) $id : null;
    }

    public function resolver(int $irregularidadId, int $usuarioId, string $estado, string $nota): bool
    {
        return DB::table('irregularidades_sin_conexion')
            ->where('id', $irregularidadId)
            ->where('estado', 'PENDIENTE')
            ->update([
                'estado' => $estado,
                'nota' => $nota,
                'resuelta_por' => $usuarioId,
                'resuelta_en' => now(),
                'updated_at' => now(),
            ]) === 1;
    }
}
