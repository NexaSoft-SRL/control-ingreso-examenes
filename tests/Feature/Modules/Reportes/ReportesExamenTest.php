<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use App\Modules\Academico\Domain\Enums\TipoPeriodo;
use App\Modules\Academico\Domain\Models\Periodo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Modules\Examenes\ArmaExamenes;
use Tests\TestCase;

final class ReportesExamenTest extends TestCase
{
    use ArmaExamenes;
    use RefreshDatabase;

    public function test_the_teacher_sees_only_current_period_exams_in_which_they_are_involved_and_the_list_is_paginated_by_eight(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes_examenes']);
        $docente = $this->docenteConCuenta($usuario);
        $otro = $this->docenteConCuenta($this->usuarioConPermisos(['reportes_examenes']));
        $asignatura = $this->asignatura('Introducción a la Programación', '2010010');
        $grupoPropio = $this->grupo($docente, $asignatura);
        $grupoAjeno = $this->grupo($otro, $asignatura);

        for ($i = 0; $i < 9; $i++) {
            $this->examen($otro, [$grupoAjeno, $grupoPropio], [], [
                'fecha' => now()->addDays($i + 1)->toDateString(),
                'hora_inicio' => '08:00:00',
            ]);
        }

        $this->examen($otro, [$grupoAjeno], [], [
            'fecha' => now()->addDays(2)->toDateString(),
            'hora_inicio' => '08:00:00',
        ]);

        $respuesta = $this->actingAs($usuario)
            ->getJson('/api/reportes?pagina=2')
            ->assertOk()
            ->assertJsonPath('meta.total', 9)
            ->assertJsonPath('meta.pagina', 2)
            ->assertJsonPath('meta.por_pagina', 8)
            ->assertJsonPath('meta.paginas', 2)
            ->assertJsonPath('periodos.0.codigo', $this->periodoVigente()->codigo);

        $datos = $respuesta->json('data');
        $this->assertIsArray($datos);
        $this->assertCount(1, $datos);
        $this->assertSame('Introducción a la Programación', $respuesta->json('data.0.asignatura.nombre'));
    }

    public function test_assignment_options_are_limited_to_the_selected_current_period(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes_examenes']);
        $docente = $this->docenteConCuenta($usuario);
        $periodoActual = $this->periodoVigente();
        $fechaInicio = now()->subDays(30);
        $periodoAnterior = Periodo::create([
            'codigo' => '1/'.now()->year,
            'anio' => now()->year,
            'numero' => 1,
            'tipo' => TipoPeriodo::Semestre1,
            'fecha_inicio' => $fechaInicio->toDateString(),
            'fecha_fin' => now()->addDays(30)->toDateString(),
        ]);
        $asignaturaActual = $this->asignatura('Asignatura actual');
        $asignaturaAnterior = $this->asignatura('Asignatura del otro período');
        $grupoActual = $this->grupo($docente, $asignaturaActual, periodo: $periodoActual);
        $grupoAnterior = $this->grupo($docente, $asignaturaAnterior, periodo: $periodoAnterior);
        $this->examen($docente, [$grupoActual]);
        $this->examen($docente, [$grupoAnterior]);

        $respuesta = $this->actingAs($usuario)
            ->getJson("/api/reportes?periodo={$periodoActual->id}")
            ->assertOk()
            ->assertJsonPath('data.0.asignatura.nombre', 'Asignatura actual');

        $asignaturas = $respuesta->json('asignaturas');
        $this->assertIsArray($asignaturas);
        $this->assertSame(['Asignatura actual'], array_column($asignaturas, 'nombre'));
    }

    public function test_exam_summary_and_downloads_include_attendance_absences_and_incidents(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes_examenes', 'seguimiento_vivo']);
        $docente = $this->docenteConCuenta($usuario);
        $grupo = $this->grupo($docente, $this->asignatura('Cálculo I', '0002040'), '2');
        $aula = $this->aula('624');
        $estudiantes = $this->estudiantesInscritos($grupo, 4);
        $examen = $this->examen($docente, [$grupo], [$aula]);
        $this->habilitar($examen, array_slice($estudiantes, 0, 3), $aula);
        $this->inhabilitar($examen, [$estudiantes[3]]);
        $ingresoId = $this->ingresar($examen, $estudiantes[0], $aula);
        DB::table('ingresos')->where('id', $ingresoId)->update(['medio_identificacion' => 'codigo']);
        DB::table('incidencias')->insert([
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'estudiante_id' => $estudiantes[1]->id,
            'tipo' => 'INTENTO_NO_AUTORIZADO',
            'origen' => 'SISTEMA',
            'causa' => 'NO_HABILITADO',
            'detalle' => 'El estudiante no está habilitado.',
            'ocurrida_en' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($usuario)
            ->getJson("/api/reportes/examenes/{$examen->id}")
            ->assertOk()
            ->assertJsonPath('data.inscritos', 4)
            ->assertJsonPath('data.ingresaron', 1)
            ->assertJsonPath('data.habilitados', 3)
            ->assertJsonPath('data.ausentes', 2)
            ->assertJsonPath('data.no_habilitados', 1)
            ->assertJsonPath('data.incidencias', 1)
            ->assertJsonPath('data.muestras.asistencia.0.medio', 'Código SIS')
            ->assertJsonPath('data.muestras.asistencia.0.codigo', '202100001')
            ->assertJsonPath('data.muestras.incidencias', []);

        $this->actingAs($usuario)
            ->getJson("/api/reportes/examenes/{$examen->id}/incidencias")
            ->assertOk()
            ->assertJsonPath('data.0.registrado_por', 'Sistema');

        $this->actingAs($usuario)
            ->get("/api/reportes/examenes/{$examen->id}/descargas/asistencia/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="asistencia '.$examen->fecha->format('Y-m-d').'.pdf"');

        $this->actingAs($usuario)
            ->get("/api/reportes/examenes/{$examen->id}/descargas/ausentes/xlsx")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('Content-Disposition', 'attachment; filename="estudiantes ausentes '.$examen->fecha->format('Y-m-d').'.xlsx"');
    }

    public function test_permission_exam_scope_and_start_time_are_checked_for_reports_and_downloads(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $examen = $this->examen($docente, [$grupo], [], [
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '23:59:00',
        ]);

        $this->actingAs($this->cuenta($docente))
            ->getJson('/api/reportes')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'reportes_examenes');

        $usuarioAjeno = $this->usuarioConPermisos(['reportes_examenes']);
        $this->actingAs($usuarioAjeno)
            ->getJson("/api/reportes/examenes/{$examen->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Este examen no está a su cargo.');

    }

    public function test_incident_details_require_tracking_permission_even_with_report_access(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes_examenes']);
        $docente = $this->docenteConCuenta($usuario);
        $grupo = $this->grupo($docente);
        $examen = $this->examen($docente, [$grupo]);

        $this->actingAs($usuario)
            ->getJson("/api/reportes/examenes/{$examen->id}/incidencias")
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'seguimiento_vivo');
    }

    public function test_download_of_a_future_exam_is_rejected_with_an_explicit_message(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes_examenes']);
        $docente = $this->docenteConCuenta($usuario);
        $grupo = $this->grupo($docente);
        $examen = $this->examen($docente, [$grupo], [], [
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '08:00:00',
        ]);

        $this->actingAs($usuario)
            ->getJson("/api/reportes/examenes/{$examen->id}/descargas/incidencias/pdf")
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'EXAMEN_NO_INICIADO')
            ->assertJsonPath('message', 'El examen todavía no se rindió.');
    }
}
