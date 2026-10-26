<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Infrastructure\Persistence;

use App\Modules\Estudiantes\Application\Contracts\PortalEstudianteGateway;
use App\Modules\Estudiantes\Application\DTOs\EstudiantePortalData;
use App\Modules\Estudiantes\Application\DTOs\PlazoFotoData;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final class EloquentPortalEstudianteGateway implements PortalEstudianteGateway
{
    use ConsultasDePadron;

    private const TIPOS = [
        'PRIMER_PARCIAL' => 'Primer parcial',
        'SEGUNDO_PARCIAL' => 'Segundo parcial',
        'FINAL' => 'Examen final',
        'SEGUNDA_INSTANCIA' => 'Segunda instancia',
        'MESA' => 'Examen de mesa',
    ];

    public function porCodigo(string $codigoUniversitario): ?EstudiantePortalData
    {
        $estudiante = Estudiante::query()
            ->where('codigo_universitario', trim($codigoUniversitario))
            ->where('activo', true)
            ->first();

        return $estudiante === null ? null : $this->datos($estudiante);
    }

    public function estudiante(int $estudianteId): ?EstudiantePortalData
    {
        $estudiante = Estudiante::query()->whereKey($estudianteId)->where('activo', true)->first();

        return $estudiante === null ? null : $this->datos($estudiante);
    }

    public function guardarCodigo(int $estudianteId, string $hash, int $minutos): void
    {
        DB::transaction(function () use ($estudianteId, $hash, $minutos): void {
            DB::table('codigos_acceso_estudiante')
                ->where('estudiante_id', $estudianteId)
                ->whereNull('usado_en')
                ->whereNull('reemplazado_en')
                ->update(['reemplazado_en' => now()]);

            DB::table('codigos_acceso_estudiante')->insert([
                'estudiante_id' => $estudianteId,
                'codigo_hash' => $hash,
                'expira_en' => now()->addMinutes($minutos),
                'created_at' => now(),
            ]);
        });
    }

    public function consumirCodigo(int $estudianteId, string $hash, int $maximoIntentos): bool
    {
        /** @var bool $valido */
        $valido = DB::transaction(function () use ($estudianteId, $hash, $maximoIntentos): bool {
            $vigente = DB::table('codigos_acceso_estudiante')
                ->where('estudiante_id', $estudianteId)
                ->whereNull('usado_en')
                ->whereNull('reemplazado_en')
                ->where('expira_en', '>', now())
                ->where('intentos', '<', $maximoIntentos)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first(['id', 'codigo_hash']);

            if ($vigente === null) {
                return false;
            }

            $columnas = get_object_vars($vigente);
            $id = $this->entero($columnas['id'] ?? null);

            if (! hash_equals($this->texto($columnas['codigo_hash'] ?? null) ?? '', $hash)) {
                DB::table('codigos_acceso_estudiante')->where('id', $id)->increment('intentos');

                return false;
            }

            DB::table('codigos_acceso_estudiante')->where('id', $id)->update(['usado_en' => now()]);

            return true;
        });

        return $valido;
    }

    public function intentosRestantes(string $codigoUniversitario, int $maximoIntentos): ?int
    {
        $intentos = DB::table('codigos_acceso_estudiante as codigo')
            ->join('estudiantes as estudiante', 'estudiante.id', '=', 'codigo.estudiante_id')
            ->where('estudiante.codigo_universitario', trim($codigoUniversitario))
            ->whereNull('codigo.usado_en')
            ->whereNull('codigo.reemplazado_en')
            ->where('codigo.expira_en', '>', now())
            ->orderByDesc('codigo.id')
            ->value('codigo.intentos');

        return is_numeric($intentos) ? max(0, $maximoIntentos - (int) $intentos) : null;
    }

    public function fueReemplazado(int $estudianteId, string $hash): bool
    {
        return DB::table('codigos_acceso_estudiante')
            ->where('estudiante_id', $estudianteId)
            ->whereNotNull('reemplazado_en')
            ->where('expira_en', '>', now())
            ->where('codigo_hash', $hash)
            ->exists();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function examenes(int $estudianteId): array
    {
        $filas = DB::table('inscripciones as inscripcion')
            ->join('examen_grupo as eg', 'eg.grupo_id', '=', 'inscripcion.grupo_id')
            ->join('examenes as examen', 'examen.id', '=', 'eg.examen_id')
            ->join('asignaturas as asignatura', 'asignatura.id', '=', 'examen.asignatura_id')
            ->leftJoin('habilitaciones as habilitacion', function (JoinClause $union) use ($estudianteId): void {
                $union->on('habilitacion.examen_id', '=', 'examen.id')
                    ->where('habilitacion.estudiante_id', '=', $estudianteId);
            })
            ->leftJoin('aulas as aula', 'aula.id', '=', 'habilitacion.aula_id')
            ->leftJoin('ingresos as ingreso', function (JoinClause $union) use ($estudianteId): void {
                $union->on('ingreso.examen_id', '=', 'examen.id')
                    ->where('ingreso.estudiante_id', '=', $estudianteId);
            })
            ->leftJoin('edificios as edificio', 'edificio.id', '=', 'aula.edificio_id')
            ->where('inscripcion.estudiante_id', $estudianteId)
            ->distinct()
            ->orderBy('examen.fecha')
            ->orderBy('examen.hora_inicio')
            ->get([
                'examen.id',
                'examen.tipo',
                'examen.fecha',
                'examen.hora_inicio',
                'examen.duracion_minutos',
                'asignatura.nombre as asignatura',
                'habilitacion.habilitado',
                'habilitacion.motivo',
                'aula.nombre as aula',
                'aula.piso',
                'edificio.nombre as edificio',
                'edificio.id as edificio_id',
                'ingreso.registrado_en as ingreso_en',
            ]);

        $hoy = now()->toDateString();
        $proximos = [];
        $rendidos = [];

        foreach ($filas as $objeto) {
            $fila = get_object_vars($objeto);
            $fecha = substr($this->texto($fila['fecha'] ?? null) ?? '', 0, 10);
            $habilitado = ($fila['habilitado'] ?? null) === null ? null : (bool) $fila['habilitado'];
            $tipo = $this->texto($fila['tipo'] ?? null) ?? '';

            $examen = [
                'id' => $this->entero($fila['id'] ?? null),
                'asignatura' => $this->texto($fila['asignatura'] ?? null),
                'tipo' => self::TIPOS[$tipo] ?? $tipo,
                'fecha' => $fecha,
                'hora' => substr($this->texto($fila['hora_inicio'] ?? null) ?? '', 0, 5),
                'duracion' => $this->entero($fila['duracion_minutos'] ?? null),
                'estado' => match ($habilitado) {
                    true => 'HABILITADO',
                    false => 'NO_HABILITADO',
                    null => 'PENDIENTE',
                },
                'motivo' => $habilitado === false ? $this->texto($fila['motivo'] ?? null) : null,
                'aula' => $habilitado === true ? $this->texto($fila['aula'] ?? null) : null,
                'piso' => $habilitado === true ? $this->texto($fila['piso'] ?? null) : null,
                'edificio' => $habilitado === true ? $this->texto($fila['edificio'] ?? null) : null,
                'edificio_id' => $habilitado === true ? $this->enteroONulo($fila['edificio_id'] ?? null) : null,
                // La hora en que entro, si entro: «Rendido» solo con ingreso.
                'ingreso' => ($fila['ingreso_en'] ?? null) === null ? null : substr($this->texto($fila['ingreso_en']) ?? '', 11, 5),
            ];

            if ($fecha < $hoy) {
                $rendidos[] = $examen;
            } else {
                $proximos[] = $examen;
            }
        }

        return [...$proximos, ...array_reverse($rendidos)];
    }

    public function fechasVigentes(): ?array
    {
        $hoy = now()->toDateString();
        $fila = DB::table('periodos')
            ->whereDate('fecha_inicio', '<=', $hoy)
            ->whereDate('fecha_fin', '>=', $hoy)
            ->selectRaw('min(fecha_inicio) as inicio, max(fecha_fin) as fin')
            ->first();
        $columnas = $fila === null ? [] : get_object_vars($fila);
        $inicio = $this->texto($columnas['inicio'] ?? null);
        $fin = $this->texto($columnas['fin'] ?? null);

        return $inicio === null || $fin === null
            ? null
            : ['inicio' => substr($inicio, 0, 10), 'fin' => substr($fin, 0, 10)];
    }

    public function plazo(): ?PlazoFotoData
    {
        $fila = DB::table('plazos_foto')->orderByDesc('id')->first(['desde', 'hasta']);

        if ($fila === null) {
            return null;
        }

        $columnas = get_object_vars($fila);

        return new PlazoFotoData(
            desde: substr($this->texto($columnas['desde'] ?? null) ?? '', 0, 10),
            hasta: substr($this->texto($columnas['hasta'] ?? null) ?? '', 0, 10),
        );
    }

    public function fijarPlazo(PlazoFotoData $plazo, int $usuarioId): void
    {
        DB::transaction(function () use ($plazo, $usuarioId): void {
            DB::table('plazos_foto')->delete();
            DB::table('plazos_foto')->insert([
                'desde' => $plazo->desde,
                'hasta' => $plazo->hasta,
                'fijado_por' => $usuarioId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function ponerFoto(int $estudianteId, string $ruta): ?string
    {
        /** @var string|null $anterior */
        $anterior = DB::transaction(function () use ($estudianteId, $ruta): ?string {
            $previa = DB::table('estudiantes')->where('id', $estudianteId)->lockForUpdate()->value('foto');

            DB::table('estudiantes')->where('id', $estudianteId)->update([
                'foto' => $ruta,
                'foto_subida_en' => now(),
                'foto_habilitada_hasta' => null,
                'foto_habilitada_por' => null,
                'updated_at' => now(),
            ]);

            return is_string($previa) && $previa !== '' ? $previa : null;
        });

        return $anterior;
    }

    public function estadoFoto(int $estudianteId): array
    {
        $fila = DB::table('estudiantes as estudiante')
            ->leftJoin('usuarios as usuario', 'usuario.id', '=', 'estudiante.foto_habilitada_por')
            ->where('estudiante.id', $estudianteId)
            ->first(['estudiante.foto', 'estudiante.foto_subida_en', 'estudiante.foto_habilitada_hasta', 'usuario.nombre']);
        $columnas = $fila === null ? [] : get_object_vars($fila);
        $conFoto = $this->texto($columnas['foto'] ?? null) !== null;
        $subida = $this->texto($columnas['foto_subida_en'] ?? null);
        $hasta = $this->texto($columnas['foto_habilitada_hasta'] ?? null);

        return [
            'subida_en' => $conFoto && $subida !== null ? substr($subida, 0, 19) : null,
            'habilitada_hasta' => $hasta === null ? null : substr($hasta, 0, 10),
            'habilitada_por' => $this->texto($columnas['nombre'] ?? null),
        ];
    }

    public function habilitarFoto(int $estudianteId, string $hasta, int $usuarioId): void
    {
        DB::table('estudiantes')->where('id', $estudianteId)->update([
            'foto_habilitada_hasta' => $hasta,
            'foto_habilitada_por' => $usuarioId,
            'updated_at' => now(),
        ]);
    }

    public function rutaFoto(int $estudianteId): ?string
    {
        $ruta = DB::table('estudiantes')->where('id', $estudianteId)->value('foto');

        return is_string($ruta) && $ruta !== '' ? $ruta : null;
    }

    private function datos(Estudiante $estudiante): EstudiantePortalData
    {
        return new EstudiantePortalData(
            id: $this->entero($estudiante->getKey()),
            codigo: $estudiante->codigo_universitario,
            // Como en el resto del sistema: «Apellidos Nombres».
            nombre: trim($estudiante->apellidos.' '.$estudiante->nombres),
            correo: $estudiante->correo(),
            conFoto: is_string($estudiante->getAttribute('foto')) && $estudiante->getAttribute('foto') !== '',
        );
    }
}
