<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Persistence;

use App\Modules\Reportes\Application\Contracts\AlertaReporteGateway;
use App\Modules\Reportes\Application\Contracts\ReporteGateway;
use App\Modules\Reportes\Application\DTOs\AlertaReporteData;
use App\Modules\Reportes\Application\DTOs\PreferenciasAlertaData;
use App\Modules\Reportes\Infrastructure\Mail\AlertaReporteMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class EloquentAlertaReporteGateway implements AlertaReporteGateway
{
    public function __construct(private readonly ReporteGateway $reportes) {}

    public function preferencias(int $usuarioId): PreferenciasAlertaData
    {
        $fila = DB::table('preferencias_alertas_reportes')
            ->where('user_id', $usuarioId)
            ->first();

        return new PreferenciasAlertaData(
            asistenciaMinima: $fila === null ? 70 : (int) $fila->asistencia_minima,
            incidenciasMinimas: $fila === null ? 5 : (int) $fila->incidencias_minimas,
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
        return DB::table('alertas_reportes as alerta')
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
            ])
            ->map(static fn (object $fila): AlertaReporteData => new AlertaReporteData(
                id: (int) $fila->id,
                examenId: (int) $fila->examen_id,
                asignatura: (string) $fila->asignatura,
                tipoExamen: (string) $fila->tipo_examen,
                fecha: (string) $fila->fecha,
                tipo: (string) $fila->tipo,
                titulo: (string) $fila->titulo,
                mensaje: (string) $fila->mensaje,
                correoEstado: (string) $fila->correo_estado,
                leidaEn: $fila->leida_en === null ? null : (string) $fila->leida_en,
                creadaEn: (string) $fila->created_at,
            ))
            ->all();
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
            ->where(static function ($consulta) use ($hoy): void {
                $consulta->whereNotNull('finalizado_en')
                    ->orWhereDate('fecha', '<', $hoy);
            })
            ->whereNotExists(static function ($consulta): void {
                $consulta->selectRaw('1')
                    ->from('alertas_reportes_procesadas')
                    ->whereColumn('alertas_reportes_procesadas.examen_id', 'examenes.id');
            })
            ->orderBy('fecha')
            ->limit(100)
            ->pluck('id');

        $procesados = 0;

        foreach ($examenes as $examenId) {
            $correos = DB::transaction(function () use ($examenId, $hoy): array {
                $examen = DB::table('examenes as examen')
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

                if ($examen === null || (
                    $examen->finalizado_en === null && (string) $examen->fecha >= $hoy
                )) {
                    return [];
                }

                if (DB::table('alertas_reportes_procesadas')
                    ->where('examen_id', $examenId)
                    ->exists()) {
                    return [];
                }

                $habilitados = DB::table('habilitaciones')
                    ->where('examen_id', $examenId)
                    ->where('habilitado', true)
                    ->count();
                $presentes = DB::table('ingresos')
                    ->where('examen_id', $examenId)
                    ->count();
                $incidencias = count($this->reportes->incidenciasDeExamen((int) $examenId));
                $docentes = DB::table('examen_grupo as eg')
                    ->join('grupos as grupo', 'grupo.id', '=', 'eg.grupo_id')
                    ->join('docentes as docente', 'docente.id', '=', 'grupo.docente_id')
                    ->join('users as usuario', 'usuario.id', '=', 'docente.user_id')
                    ->where('eg.examen_id', $examenId)
                    ->where('usuario.is_active', true)
                    ->select(['usuario.id', 'usuario.email'])
                    ->distinct()
                    ->get();
                $correos = [];

                foreach ($docentes as $docente) {
                    $preferencias = DB::table('preferencias_alertas_reportes')
                        ->where('user_id', $docente->id)
                        ->first();
                    $asistenciaMinima = $preferencias === null
                        ? 70
                        : (int) $preferencias->asistencia_minima;
                    $incidenciasMinimas = $preferencias === null
                        ? 5
                        : (int) $preferencias->incidencias_minimas;
                    $candidatas = [];

                    if ($habilitados > 0 && $presentes * 100 < $asistenciaMinima * $habilitados) {
                        $porcentaje = (int) round($presentes * 100 / $habilitados);
                        $candidatas[] = [
                            'tipo' => 'ASISTENCIA_BAJA',
                            'titulo' => 'Asistencia por debajo del umbral',
                            'mensaje' => sprintf(
                                '%s (%s): asistencia de %d%%, por debajo del umbral configurado de %d%%.',
                                $examen->asignatura,
                                $examen->fecha,
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
                                $examen->asignatura,
                                $examen->fecha,
                                $incidencias,
                                $incidenciasMinimas,
                            ),
                            'valor' => $incidencias,
                            'umbral' => $incidenciasMinimas,
                        ];
                    }

                    foreach ($candidatas as $candidata) {
                        $alertaId = DB::table('alertas_reportes')->insertGetId([
                            'user_id' => $docente->id,
                            'examen_id' => $examenId,
                            ...$candidata,
                            'correo_estado' => 'PENDIENTE',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $correos[] = [
                            'id' => $alertaId,
                            'email' => (string) $docente->email,
                            'asignatura' => (string) $examen->asignatura,
                            'tipo_examen' => (string) $examen->tipo,
                            'fecha' => (string) $examen->fecha,
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

    private function hoy(): string
    {
        $zona = config('umss.zona_horaria');

        return now(is_string($zona) ? $zona : 'America/La_Paz')->toDateString();
    }
}
