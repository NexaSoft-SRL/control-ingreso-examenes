<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Modules\Examenes\ArmaExamenes;
use Tests\TestCase;

final class BitacoraExamenTest extends TestCase
{
    use ArmaExamenes;
    use RefreshDatabase;

    public function test_timeline_combines_entries_rejections_and_administrative_operations_without_writing_audit_records(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes_examenes']);
        $docente = $this->docenteConCuenta($usuario);
        $grupo = $this->grupo($docente);
        $aula = $this->aula('624');
        $estudiante = $this->estudiantesInscritos($grupo, 1)[0];
        DB::table('estudiantes')->where('id', $estudiante->id)->update([
            'nombres' => 'José',
            'apellidos' => 'Álvarez',
        ]);
        $examen = $this->examen($docente, [$grupo], [$aula]);
        $this->ingresar($examen, $estudiante, $aula);
        DB::table('incidencias')->insert([
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'estudiante_id' => $estudiante->id,
            'tipo' => 'INTENTO_NO_AUTORIZADO',
            'origen' => 'SISTEMA',
            'causa' => 'NO_HABILITADO',
            'detalle' => 'No estaba habilitado.',
            'ocurrida_en' => now()->addSecond(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('bitacora_operaciones')->insert([
            'usuario_id' => $usuario->id,
            'operacion' => 'qr.emitir',
            'tabla_afectada' => 'examenes',
            'registro_id' => $examen->id,
            'descripcion' => '1 código QR emitido para el examen.',
            'fecha_operacion' => now()->addMinutes(2),
        ]);
        $auditoriaInicial = DB::table('bitacora_operaciones')->count();

        $respuesta = $this->actingAs($usuario)
            ->getJson("/api/reportes/examenes/{$examen->id}/bitacora?buscar=jose%20alvarez")
            ->assertOk()
            ->assertJsonPath('meta.total_examen', 3)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('categorias.0.valor', 'CODIGO_QR')
            ->assertJsonPath('data.0.suceso', 'Ingreso rechazado')
            ->assertJsonPath('data.0.estudiante', 'Álvarez José');

        $this->assertSame(
            $auditoriaInicial,
            DB::table('bitacora_operaciones')->count(),
            'Consultar la bitácora no debe crear registros de auditoría.',
        );
        $datos = $respuesta->json('data');
        $this->assertIsArray($datos);
        $this->assertSame(['RECHAZO', 'INGRESO'], array_column($datos, 'categoria'));
    }

    public function test_category_and_classroom_filters_and_accent_insensitive_search_are_applied(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes_examenes']);
        $docente = $this->docenteConCuenta($usuario);
        $grupo = $this->grupo($docente);
        $aula = $this->aula('691A');
        $estudiante = $this->estudiantesInscritos($grupo, 1)[0];
        DB::table('estudiantes')->where('id', $estudiante->id)->update([
            'nombres' => 'Inés',
            'apellidos' => 'Muñoz',
        ]);
        $examen = $this->examen($docente, [$grupo], [$aula]);
        DB::table('incidencias')->insert([
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'estudiante_id' => $estudiante->id,
            'tipo' => 'INCIDENTE_GENERAL',
            'origen' => 'PERSONA',
            'detalle' => 'Se reportó una observación.',
            'ocurrida_en' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($usuario)
            ->getJson("/api/reportes/examenes/{$examen->id}/bitacora?buscar=ines%20munoz&categoria=INCIDENCIA&aula={$aula->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.suceso', 'Incidencia reportada')
            ->assertJsonPath('data.0.aula', '691A');
    }

    public function test_only_users_with_report_permission_and_exam_scope_can_open_the_timeline(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $examen = $this->examen($docente, [$grupo]);

        $this->getJson("/api/reportes/examenes/{$examen->id}/bitacora")->assertUnauthorized();

        $ajeno = $this->usuarioConPermisos(['reportes_examenes']);
        $this->actingAs($ajeno)
            ->getJson("/api/reportes/examenes/{$examen->id}/bitacora")
            ->assertForbidden()
            ->assertJsonPath('message', 'Este examen no está a su cargo.');

        $administrador = $this->usuarioConPermisos(['reportes_universidad']);
        $this->actingAs($administrador)
            ->getJson("/api/reportes/examenes/{$examen->id}/bitacora")
            ->assertOk();
    }

    public function test_exam_selector_scopes_teachers_and_allows_university_reports_users(): void
    {
        $docenteUsuario = $this->usuarioConPermisos(['reportes_examenes']);
        $docente = $this->docenteConCuenta($docenteUsuario);
        $grupo = $this->grupo($docente);
        $examen = $this->examen($docente, [$grupo]);
        $ajeno = $this->docente();
        $otroGrupo = $this->grupo($ajeno);
        $otroExamen = $this->examen($ajeno, [$otroGrupo]);

        $this->actingAs($docenteUsuario)
            ->getJson('/api/reportes/bitacora/examenes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $examen->id);

        $administrador = $this->usuarioConPermisos(['reportes_universidad']);
        $this->actingAs($administrador)
            ->getJson('/api/reportes/bitacora/examenes')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_pagination_returns_at_most_one_hundred_events_and_export_ignores_screen_filters(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes_examenes']);
        $docente = $this->docenteConCuenta($usuario);
        $grupo = $this->grupo($docente);
        $examen = $this->examen($docente, [$grupo]);
        $eventos = [];

        for ($indice = 0; $indice < 101; $indice++) {
            $eventos[] = [
                'usuario_id' => $usuario->id,
                'operacion' => 'qr.emitir',
                'tabla_afectada' => 'examenes',
                'registro_id' => $examen->id,
                'descripcion' => sprintf('Evento %03d.', $indice),
                'fecha_operacion' => now()->addSeconds($indice),
            ];
        }

        DB::table('bitacora_operaciones')->insert($eventos);

        $this->actingAs($usuario)
            ->getJson("/api/reportes/examenes/{$examen->id}/bitacora?categoria=CODIGO_QR&pagina=1")
            ->assertOk()
            ->assertJsonCount(100, 'data')
            ->assertJsonPath('meta.total', 101)
            ->assertJsonPath('meta.pagina', 1)
            ->assertJsonPath('meta.paginas', 2);

        $this->actingAs($usuario)
            ->getJson("/api/reportes/examenes/{$examen->id}/bitacora?categoria=CODIGO_QR&pagina=2")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($usuario)
            ->get("/api/reportes/examenes/{$examen->id}/bitacora/descargas/xlsx?categoria=INCIDENCIA")
            ->assertOk()
            ->assertHeader(
                'Content-Type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            )
            ->assertHeader(
                'Content-Disposition',
                sprintf(
                    'attachment; filename="bitacora %s.xlsx"; filename*=UTF-8\'\'bit%%C3%%A1cora%%20%s.xlsx',
                    $examen->fecha->format('Y-m-d'),
                    $examen->fecha->format('Y-m-d'),
                ),
            );

        $this->actingAs($usuario)
            ->get("/api/reportes/examenes/{$examen->id}/bitacora/descargas/pdf?categoria=INCIDENCIA")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader(
                'Content-Disposition',
                sprintf(
                    'attachment; filename="bitacora %s.pdf"; filename*=UTF-8\'\'bit%%C3%%A1cora%%20%s.pdf',
                    $examen->fecha->format('Y-m-d'),
                    $examen->fecha->format('Y-m-d'),
                ),
            );
    }

    public function test_empty_timeline_disables_export_at_the_api_boundary(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes_examenes']);
        $docente = $this->docenteConCuenta($usuario);
        $grupo = $this->grupo($docente);
        $examen = $this->examen($docente, [$grupo]);

        $this->actingAs($usuario)
            ->getJson("/api/reportes/examenes/{$examen->id}/bitacora")
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total_examen', 0);

        $this->actingAs($usuario)
            ->getJson("/api/reportes/examenes/{$examen->id}/bitacora/descargas/pdf")
            ->assertConflict()
            ->assertJsonPath('message', 'No hay eventos para descargar.');
    }
}
