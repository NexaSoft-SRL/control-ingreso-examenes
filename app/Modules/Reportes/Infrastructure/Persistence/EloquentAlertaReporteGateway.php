<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Persistence;

use App\Modules\Reportes\Application\Contracts\AlertaReporteGateway;
use App\Modules\Reportes\Application\Contracts\ReporteGateway;
use App\Modules\Reportes\Application\DTOs\AlertaReporteData;
use App\Modules\Reportes\Application\DTOs\PreferenciasAlertaData;
use App\Modules\Reportes\Domain\Rules\FechaLegible;
use App\Modules\Reportes\Infrastructure\Mail\AlertaReporteMail;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use LogicException;
use Throwable;

final class EloquentAlertaReporteGateway implements AlertaReporteGateway
{
    public function __construct(private readonly ReporteGateway $reportes) {}

    public function preferencias(int $usuarioId): PreferenciasAlertaData
    {
        $fila = DB::table('preferencias_alertas_reportes')
            ->where('user_id', $usuarioId)
            ->first();
        $columnas = $fila === null ? [] : $this->columnas($fila);

        return new PreferenciasAlertaData(
            asistenciaMinima: $fila === null ? 70 : $this->entero($columnas['asistencia_minima'] ?? null),
            incidenciasMinimas: $fila === null ? 5 : $this->entero($columnas['incidencias_minimas'] ?? null),
        );
    }

    public function guardarPreferencias(
        int $usuarioId,
        int $asistenciaMinima,
        int $incidenciasMinimas,
    ): PreferenciasAlertaData {
        $ahora = now();
        DB::table('preferencias_alertas_reportes')->upsert(
            [[
                'user_id' => $usuarioId,
                'asistencia_minima' => $asistenciaMinima,
                'incidencias_minimas' => $incidenciasMinimas,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]],
            ['user_id'],
            ['asistencia_minima', 'incidencias_minimas', 'updated_at'],
        );

        return new PreferenciasAlertaData($asistenciaMinima, $incidenciasMinimas);
    }

    public function alertas(int $usuarioId): array
    {
        $filas = DB::table('alertas_reportes as alerta')
            ->join('examenes as examen', 'examen.id', '=', 'alerta.examen_id')
            ->join('asignaturas as asignatura', 'asignatura.id', '=', 'examen.asignatura_id')
            ->where('alerta.user_id', $usuarioId)
            ->orderByDesc('alerta.created_at')
            ->orderByDesc('alerta.id')
            ->limit(50)
            ->get([
                'alerta.id',
                'alerta.examen_id',
                'asignatura.nombre as asignatura',
                'examen.tipo as tipo_examen',
                'examen.fecha',
                'alerta.tipo',
                'alerta.titulo',
                'alerta.mensaje',
                'alerta.correo_estado',
                'alerta.leida_en',
                'alerta.created_at',
            ]);
        $alertas = [];

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);
            $alertas[] = new AlertaReporteData(
                id: $this->entero($columnas['id'] ?? null),
                examenId: $this->entero($columnas['examen_id'] ?? null),
                asignatura: $this->textoRequerido($columnas['asignatura'] ?? null),
                tipoExamen: $this->textoRequerido($columnas['tipo_examen'] ?? null),
                fecha: $this->textoRequerido($columnas['fecha'] ?? null),
                tipo: $this->textoRequerido($columnas['tipo'] ?? null),
                titulo: $this->textoRequerido($columnas['titulo'] ?? null),
                mensaje: $this->textoRequerido($columnas['mensaje'] ?? null),
                correoEstado: $this->textoRequerido($columnas['correo_estado'] ?? null),
                leidaEn: $this->texto($columnas['leida_en'] ?? null),
                creadaEn: $this->textoRequerido($columnas['created_at'] ?? null),
            );
        }

        return $alertas;
    }

    public function marcarLeida(int $usuarioId, int $alertaId): bool
    {
        return DB::table('alertas_reportes')
            ->where('user_id', $usuarioId)
            ->where('id', $alertaId)
            ->update(['leida_en' => now(), 'updated_at' => now()]) > 0;
    }

    public function procesarCierres(): int
    {
        $hoy = $this->hoy();
        $examenes = DB::table('examenes')
            ->where(static function (Builder $consulta) use ($hoy): void {
                $consulta->whereNotNull('finalizado_en')
                    ->orWhereDate('fecha', '<', $hoy);
            })
            ->whereNotExists(static function (Builder $consulta): void {
                $consulta->selectRaw('1')
                    ->from('alertas_reportes_procesadas')
                    ->whereColumn('alertas_reportes_procesadas.examen_id', 'examenes.id');
            })
            ->orderBy('fecha')
            ->limit(100)
            ->pluck('id');

        $procesados = 0;

        foreach ($examenes as $examenId) {
            $correos = $this->procesarExamen($this->entero($examenId), $hoy);
            if ($correos === null) {
                continue;
            }
            $procesados++;

            foreach ($correos as $correo) {
                try {
                    Mail::to($correo['email'])->queue(new AlertaReporteMail($correo));
                } catch (Throwable $error) {
                    DB::table('alertas_reportes')
                        ->where('id', $correo['id'])
                        ->update([
                            'correo_estado' => 'FALLIDO',
                            'correo_error' => mb_substr($error->getMessage(), 0, 4000),
                            'updated_at' => now(),
                        ]);
                }
            }
        }

        return $procesados;
    }

    /**
     * @return list<array{id: int, email: string, asignatura: string, tipo_examen: string, fecha: string, titulo: string, mensaje: string}>|null
     */
    private function procesarExamen(int $examenId, string $hoy): ?array
    {
        return DB::transaction(function () use ($examenId, $hoy): ?array {
            $filaExamen = DB::table('examenes as examen')
                ->join('asignaturas as asignatura', 'asignatura.id', '=', 'examen.asignatura_id')
                ->where('examen.id', $examenId)
                ->lockForUpdate()
                ->first([
                    'examen.id',
                    'examen.fecha',
                    'examen.finalizado_en',
                    'examen.tipo',
                    'asignatura.nombre as asignatura',
                ]);

            if ($filaExamen === null) {
                return null;
            }

            $examen = $this->columnas($filaExamen);
            $fecha = $this->textoRequerido($examen['fecha'] ?? null);
            if ($this->texto($examen['finalizado_en'] ?? null) === null && $fecha >= $hoy) {
                return null;
            }

            if (DB::table('alertas_reportes_procesadas')
                ->where('examen_id', $examenId)
                ->exists()) {
                return null;
            }

            $habilitados = DB::table('habilitaciones')
                ->where('examen_id', $examenId)
                ->where('habilitado', true)
                ->count();
            $presentes = DB::table('ingresos')
                ->where('examen_id', $examenId)
                ->count();
            $incidencias = count($this->reportes->incidenciasDeExamen($examenId));
            $docentes = DB::table('examen_grupo as eg')
                ->join('grupos as grupo', 'grupo.id', '=', 'eg.grupo_id')
                ->join('docentes as docente', 'docente.id', '=', 'grupo.docente_id')
                ->join('users as usuario', 'usuario.id', '=', 'docente.user_id')
                ->where('eg.examen_id', $examenId)
                ->where('usuario.is_active', true)
                ->select(['usuario.id', 'usuario.email'])
                ->distinct()
                ->get();
            $asignatura = $this->textoRequerido($examen['asignatura'] ?? null);
            $tipoExamen = $this->textoRequerido($examen['tipo'] ?? null);
            $correos = [];

            foreach ($docentes as $docente) {
                $columnasDocente = $this->columnas($docente);
                $docenteId = $this->entero($columnasDocente['id'] ?? null);
                $email = $this->textoRequerido($columnasDocente['email'] ?? null);
                $filaPreferencias = DB::table('preferencias_alertas_reportes')
                    ->where('user_id', $docenteId)
                    ->first();
                $preferencias = $filaPreferencias === null ? [] : $this->columnas($filaPreferencias);
                $asistenciaMinima = $filaPreferencias === null
                    ? 70
                    : $this->entero($preferencias['asistencia_minima'] ?? null);
                $incidenciasMinimas = $filaPreferencias === null
                    ? 5
                    : $this->entero($preferencias['incidencias_minimas'] ?? null);
                $candidatas = [];

                if ($habilitados > 0 && $presentes * 100 < $asistenciaMinima * $habilitados) {
                    $porcentaje = (int) round($presentes * 100 / $habilitados);
                    $candidatas[] = [
                        'tipo' => 'ASISTENCIA_BAJA',
                        'titulo' => 'Asistencia por debajo del umbral',
                        'mensaje' => sprintf(
                            '%s (%s): asistencia de %d%%, por debajo del umbral configurado de %d%%.',
                            $asignatura,
                            FechaLegible::dia($fecha),
                            $porcentaje,
                            $asistenciaMinima,
                        ),
                        'valor' => $porcentaje,
                        'umbral' => $asistenciaMinima,
                    ];
                }

                if ($incidencias >= $incidenciasMinimas) {
                    $candidatas[] = [
                        'tipo' => 'INCIDENCIAS_ALTAS',
                        'titulo' => 'Incidencias en el examen',
                        'mensaje' => sprintf(
                            '%s (%s): se registraron %d incidencias, alcanzando el umbral configurado de %d.',
                            $asignatura,
                            FechaLegible::dia($fecha),
                            $incidencias,
                            $incidenciasMinimas,
                        ),
                        'valor' => $incidencias,
                        'umbral' => $incidenciasMinimas,
                    ];
                }

                foreach ($candidatas as $candidata) {
                    $alertaId = DB::table('alertas_reportes')->insertGetId([
                        'user_id' => $docenteId,
                        'examen_id' => $examenId,
                        ...$candidata,
                        'correo_estado' => 'PENDIENTE',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $correos[] = [
                        'id' => $this->entero($alertaId),
                        'email' => $email,
                        'asignatura' => $asignatura,
                        'tipo_examen' => EloquentReporteGateway::TIPOS[$tipoExamen] ?? $tipoExamen,
                        'fecha' => FechaLegible::dia($fecha),
                        'titulo' => $candidata['titulo'],
                        'mensaje' => $candidata['mensaje'],
                    ];
                }
            }

            DB::table('alertas_reportes_procesadas')->insert([
                'examen_id' => $examenId,
                'procesado_en' => now(),
            ]);

            return $correos;
        }, 3);
    }

    /**
     * @return array<string, mixed>
     */
    private function columnas(object $fila): array
    {
        $columnas = [];
        foreach (get_object_vars($fila) as $nombre => $valor) {
            if (is_string($nombre)) {
                $columnas[$nombre] = $valor;
            }
        }

        return $columnas;
    }

    private function texto(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        if (is_string($valor)) {
            return $valor;
        }

        if (is_int($valor) || is_float($valor)) {
            return (string) $valor;
        }

        throw new LogicException('La columna no contiene un texto válido.');
    }

    private function textoRequerido(mixed $valor): string
    {
        $texto = $this->texto($valor);
        if ($texto === null) {
            throw new LogicException('La columna obligatoria no contiene un texto.');
        }

        return $texto;
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

    private function hoy(): string
    {
        $zona = config('umss.zona_horaria');

        return now(is_string($zona) ? $zona : 'America/La_Paz')->toDateString();
    }
}
