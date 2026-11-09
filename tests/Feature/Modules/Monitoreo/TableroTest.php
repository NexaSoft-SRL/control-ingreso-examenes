<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Monitoreo;

use App\Modules\Examenes\Domain\Models\ExamenAula;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «En curso» de un examen (ruta 71): cifras por aula, quien atiende cada
 * una, aula compartida, incidencias sin atender y la ventana del examen.
 * Solo para un docente del examen, y solo lectura.
 */
final class TableroTest extends TestCase
{
    use EscenarioDeSeguimiento;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->detenerReloj();
    }

    public function test_en_curso_counts_entries_and_assigned_students_per_room(): void
    {
        $docente = $this->docenteQueSigue();
        $grupo = $this->grupo($docente);
        $primera = $this->aula('691A');
        $segunda = $this->aula('691B');
        $examen = $this->examen($docente, [$grupo], [$primera, $segunda]);
        $estudiantes = $this->estudiantesInscritos($grupo, 7);

        $this->habilitar($examen, array_slice($estudiantes, 0, 3), $primera);
        $this->habilitar($examen, array_slice($estudiantes, 3, 2), $segunda);
        $this->habilitar($examen, [$estudiantes[5]]);
        $this->inhabilitar($examen, [$estudiantes[6]]);

        $this->ingresar($examen, $estudiantes[0], $primera);
        $this->ingresar($examen, $estudiantes[1], $primera);
        $this->ingresar($examen, $estudiantes[3], $segunda);

        $this->actingAs($this->cuentaDe($docente))
            ->getJson("/api/examenes/{$examen->id}/en-curso")
            ->assertOk()
            ->assertJsonPath('cifras.ingresaron', 3)
            ->assertJsonPath('cifras.faltan', 3)
            ->assertJsonPath('cifras.aulas', 2)
            ->assertJsonPath('cifras.incidencias_sin_atender', 0)
            ->assertJsonCount(2, 'aulas')
            ->assertJsonPath('aulas.0.aula_id', $primera->id)
            ->assertJsonPath('aulas.0.nombre', '691A')
            ->assertJsonPath('aulas.0.ingresados', 2)
            ->assertJsonPath('aulas.0.asignados', 3)
            ->assertJsonPath('aulas.0.faltan', 1)
            ->assertJsonPath('aulas.1.nombre', '691B')
            ->assertJsonPath('aulas.1.ingresados', 1)
            ->assertJsonPath('aulas.1.asignados', 2);
    }

    public function test_en_curso_tells_who_entered_through_the_door_of_another_room(): void
    {
        $docente = $this->docenteQueSigue();
        $grupo = $this->grupo($docente);
        $primera = $this->aula('691A');
        $segunda = $this->aula('691B');
        $examen = $this->examen($docente, [$grupo], [$primera, $segunda]);
        $estudiantes = $this->estudiantesInscritos($grupo, 3);

        $this->habilitar($examen, array_slice($estudiantes, 0, 2), $primera);
        $this->habilitar($examen, [$estudiantes[2]], $segunda);

        $this->ingresar($examen, $estudiantes[0], $primera);
        $this->ingresar($examen, $estudiantes[1], $segunda);

        $this->actingAs($this->cuentaDe($docente))
            ->getJson("/api/examenes/{$examen->id}/en-curso")
            ->assertOk()
            ->assertJsonPath('aulas.0.de_otra', 0)
            ->assertJsonPath('aulas.0.en_otra', 1)
            ->assertJsonPath('aulas.1.de_otra', 1)
            ->assertJsonPath('aulas.1.en_otra', 0);
    }

    public function test_en_curso_tells_the_last_entry_of_the_exam_and_of_each_room(): void
    {
        $docente = $this->docenteQueSigue();
        $grupo = $this->grupo($docente);
        $primera = $this->aula('691A');
        $segunda = $this->aula('691B');
        $vacia = $this->aula('691C');
        $examen = $this->examen($docente, [$grupo], [$primera, $segunda, $vacia]);
        $estudiantes = $this->estudiantesInscritos($grupo, 3);
        $this->habilitar($examen, $estudiantes, $primera);
        $ruta = "/api/examenes/{$examen->id}/en-curso";

        $this->actingAs($this->cuentaDe($docente))
            ->getJson($ruta)
            ->assertOk()
            ->assertJsonPath('ultimo_ingreso', null)
            ->assertJsonPath('aulas.0.ultimo_ingreso', null);

        $this->detenerReloj('10:02:41');
        $this->ingresar($examen, $estudiantes[0], $primera);
        $this->detenerReloj('10:14:52');
        $this->ingresar($examen, $estudiantes[1], $primera);
        $enLaPrimera = now()->format('Y-m-d\TH:i:sP');
        $this->detenerReloj('10:08:03');
        $this->ingresar($examen, $estudiantes[2], $segunda);
        $enLaSegunda = now()->format('Y-m-d\TH:i:sP');
        $this->detenerReloj('10:20:00');

        $this->actingAs($this->cuentaDe($docente))
            ->getJson($ruta)
            ->assertOk()
            ->assertJsonPath('ultimo_ingreso', $enLaPrimera)
            ->assertJsonPath('aulas.0.ultimo_ingreso', $enLaPrimera)
            ->assertJsonPath('aulas.1.ultimo_ingreso', $enLaSegunda)
            ->assertJsonPath('aulas.2.ultimo_ingreso', null);

        $this->assertStringContainsString('T10:14:52', $enLaPrimera);
    }

    public function test_en_curso_counts_arrivals_every_five_minutes_without_skipping_empty_ones(): void
    {
        $docente = $this->docenteQueSigue();
        $grupo = $this->grupo($docente);
        $aula = $this->aula('691A');
        $examen = $this->examen($docente, [$grupo], [$aula]);
        $estudiantes = $this->estudiantesInscritos($grupo, 4);
        $this->habilitar($examen, $estudiantes, $aula);
        $ruta = "/api/examenes/{$examen->id}/en-curso";

        $this->actingAs($this->cuentaDe($docente))->getJson($ruta)->assertJsonPath('llegadas', []);

        foreach (['10:01:10', '10:04:59', '10:16:00', '10:17:30'] as $i => $hora) {
            $this->detenerReloj($hora);
            $this->ingresar($examen, $estudiantes[$i], $aula);
        }

        $this->actingAs($this->cuentaDe($docente))
            ->getJson($ruta)
            ->assertOk()
            ->assertJsonPath('llegadas', [
                ['hora' => '10:00', 'ingresos' => 2],
                ['hora' => '10:05', 'ingresos' => 0],
                ['hora' => '10:10', 'ingresos' => 0],
                ['hora' => '10:15', 'ingresos' => 2],
            ]);
    }

    public function test_en_curso_returns_the_exam_and_the_server_time(): void
    {
        $docente = $this->docenteQueSigue();
        $asignatura = $this->asignatura('Introducción a la Programación');
        $edificio = $this->edificio();
        $aula = $this->aula('617', $edificio);
        $examen = $this->examen($docente, [$this->grupo($docente, $asignatura)], [$aula]);

        $this->actingAs($this->cuentaDe($docente))
            ->getJson("/api/examenes/{$examen->id}/en-curso")
            ->assertOk()
            ->assertJsonPath('examen.id', $examen->id)
            ->assertJsonPath('examen.asignatura', 'Introducción a la Programación')
            ->assertJsonPath('examen.tipo', 'Primer parcial')
            ->assertJsonPath('examen.fecha', now()->toDateString())
            ->assertJsonPath('examen.hora', '09:55')
            ->assertJsonPath('abierto', true)
            ->assertJsonPath('hora_servidor', now()->format('Y-m-d\TH:i:sP'))
            ->assertJsonPath('aulas.0.edificio_id', $edificio->id);
    }

    public function test_en_curso_tells_who_keeps_each_room_and_who_supports_it(): void
    {
        $docente = $this->docenteQueSigue('Salazar Serrudo Carla');
        $this->cuentaDe($docente)->forceFill(['name' => 'Salazar Serrudo Carla'])->save();
        $propia = $this->aula('691A');
        $delegada = $this->aula('691B');
        $examen = $this->examen($docente, [$this->grupo($docente)], [$propia, $delegada]);

        $encargado = $this->auxiliarQueSigue('Vargas Arce Sergio');
        $this->ponerACargo($examen, $delegada, $encargado);
        $this->ponerDeApoyo($examen, $delegada, $this->auxiliarQueSigue('Rojas Ledezma Andrea'));
        $this->ponerDeApoyo($examen, $delegada, $this->auxiliarQueSigue('Peredo Claros Lucía'));

        $this->actingAs($this->cuentaDe($docente))
            ->getJson("/api/examenes/{$examen->id}/en-curso")
            ->assertOk()
            ->assertJsonPath('aulas.0.encargado.nombre', 'Salazar Serrudo Carla')
            ->assertJsonPath('aulas.0.encargado.es_docente', true)
            ->assertJsonPath('aulas.0.apoyo', [])
            ->assertJsonPath('aulas.0.a_mi_cargo', true)
            ->assertJsonPath('aulas.1.encargado.nombre', 'Vargas Arce Sergio')
            ->assertJsonPath('aulas.1.encargado.es_docente', false)
            ->assertJsonPath('aulas.1.apoyo', ['Peredo Claros Lucía', 'Rojas Ledezma Andrea'])
            ->assertJsonPath('aulas.1.a_mi_cargo', false);
    }

    public function test_en_curso_warns_about_a_room_shared_with_an_overlapping_exam(): void
    {
        $docente = $this->docenteQueSigue();
        $otro = $this->docenteConCuenta();
        $compartida = $this->aula('691A');
        $libre = $this->aula('691B');
        $despues = $this->aula('617');
        $otroDia = $this->aula('624');
        $examen = $this->examen(
            $docente,
            [$this->grupo($docente)],
            [$compartida, $libre, $despues, $otroDia],
        );

        // Empieza antes de que este termine (09:55 + 90 min): se solapa.
        $this->examen(
            $otro,
            [$this->grupo($otro, $this->asignatura('Cálculo I'))],
            [$compartida],
            ['hora_inicio' => '11:00:00', 'duracion_minutos' => 60],
        );
        // Empieza justo cuando este termina: no se solapa.
        $this->examen(
            $otro,
            [$this->grupo($otro, $this->asignatura('Álgebra I'))],
            [$despues],
            ['hora_inicio' => '11:25:00', 'tipo' => 'FINAL'],
        );
        // Misma hora, otro dia.
        $this->examen(
            $otro,
            [$this->grupo($otro, $this->asignatura('Física I'))],
            [$otroDia],
            ['fecha' => now()->addDay()->toDateString()],
        );

        $this->actingAs($this->cuentaDe($docente))
            ->getJson("/api/examenes/{$examen->id}/en-curso")
            ->assertOk()
            ->assertJsonPath('aulas.0.compartida', 'Cálculo I · Primer parcial')
            ->assertJsonPath('aulas.1.compartida', null)
            ->assertJsonPath('aulas.2.compartida', null)
            ->assertJsonPath('aulas.3.compartida', null);
    }

    public function test_en_curso_tells_which_rooms_use_an_access_code(): void
    {
        $docente = $this->docenteQueSigue();
        $conCodigo = $this->aula();
        $sinCodigo = $this->aula();
        $examen = $this->examen($docente, [$this->grupo($docente)], [$conCodigo, $sinCodigo]);

        ExamenAula::where('examen_id', $examen->id)
            ->where('aula_id', $conCodigo->id)
            ->update(['codigo_acceso' => '482913', 'codigo_generado_en' => now()]);

        $this->actingAs($this->cuentaDe($docente))
            ->getJson("/api/examenes/{$examen->id}/en-curso")
            ->assertOk()
            ->assertJsonPath('aulas.0.usa_codigo', true)
            ->assertJsonPath('aulas.1.usa_codigo', false);
    }

    public function test_en_curso_counts_only_unattended_incidents_reported_by_people(): void
    {
        $docente = $this->docenteQueSigue();
        $primera = $this->aula();
        $segunda = $this->aula();
        $examen = $this->examen($docente, [$this->grupo($docente)], [$primera, $segunda]);

        $this->incidencia($examen, $primera);
        $this->incidencia($examen, $primera, ['tipo' => 'SUPLANTACION']);
        $this->incidencia($examen, $primera, [
            'atendida_en' => now(),
            'atendida_por' => $docente->user_id,
        ]);
        // El rechazo de la puerta no se atiende ni se cuenta.
        $this->incidencia($examen, $primera, [
            'tipo' => 'INTENTO_NO_AUTORIZADO',
            'origen' => 'SISTEMA',
            'causa' => 'NO_INSCRITO',
            'reportada_por' => null,
        ]);
        // Sin aula: cuenta para el examen, no para un aula.
        $this->incidencia($examen, null, ['tipo' => 'OTRA']);

        $this->actingAs($this->cuentaDe($docente))
            ->getJson("/api/examenes/{$examen->id}/en-curso")
            ->assertOk()
            ->assertJsonPath('cifras.incidencias_sin_atender', 3)
            ->assertJsonPath('aulas.0.incidencias_sin_atender', 2)
            ->assertJsonPath('aulas.1.incidencias_sin_atender', 0);
    }

    public function test_en_curso_is_open_from_an_hour_before_the_start_until_it_is_finalized(): void
    {
        $docente = $this->docenteQueSigue();
        $examen = $this->examen($docente, [$this->grupo($docente)], [$this->aula()], [
            'hora_inicio' => '14:00:00',
            'duracion_minutos' => 90,
        ]);
        $cuenta = $this->cuentaDe($docente);
        $ruta = "/api/examenes/{$examen->id}/en-curso";

        foreach ([
            '12:59:59' => false,
            '13:00:00' => true,
            '14:30:00' => true,
            '15:30:00' => true,
            // Se paso de su duracion: sigue abierto y se avisa.
            '15:30:01' => true,
        ] as $hora => $abierto) {
            $this->detenerReloj($hora);

            $this->actingAs($cuenta)
                ->getJson($ruta)
                ->assertOk()
                ->assertJsonPath('abierto', $abierto)
                ->assertJsonPath('fin', '15:30')
                ->assertJsonPath('excedido', $hora === '15:30:01');
        }

        // El docente lo finaliza (ruta de Examenes).
        $examen->forceFill(['finalizado_en' => now()->setTime(15, 30, 1)])->save();
        $this->detenerReloj('15:30:02');

        $this->actingAs($cuenta)
            ->getJson($ruta)
            ->assertOk()
            ->assertJsonPath('abierto', false)
            ->assertJsonPath('excedido', false);
    }

    public function test_an_exam_of_another_day_is_followed_but_is_not_open(): void
    {
        $docente = $this->docenteQueSigue();
        $examen = $this->examen($docente, [$this->grupo($docente)], [$this->aula()], [
            'fecha' => now()->addDay()->toDateString(),
        ]);

        $this->actingAs($this->cuentaDe($docente))
            ->getJson("/api/examenes/{$examen->id}/en-curso")
            ->assertOk()
            ->assertJsonPath('abierto', false);
    }

    public function test_a_teacher_of_an_included_group_follows_the_exam(): void
    {
        $registra = $this->docenteQueSigue();
        $sumado = $this->docenteQueSigue();
        $asignatura = $this->asignatura();
        $examen = $this->examen(
            $registra,
            [$this->grupo($registra, $asignatura), $this->grupo($sumado, $asignatura)],
            [$this->aula()],
        );

        $this->actingAs($this->cuentaDe($sumado))
            ->getJson("/api/examenes/{$examen->id}/en-curso")
            ->assertOk()
            ->assertJsonPath('examen.id', $examen->id)
            ->assertJsonPath('aulas.0.a_mi_cargo', false);
    }

    public function test_a_teacher_cannot_follow_the_exam_of_another_teacher(): void
    {
        $docente = $this->docenteQueSigue();
        $ajeno = $this->docenteQueSigue();
        $examen = $this->examen($docente, [$this->grupo($docente)], [$this->aula()]);

        $this->actingAs($this->cuentaDe($ajeno))
            ->getJson("/api/examenes/{$examen->id}/en-curso")
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Este examen no está a su cargo.',
                'alcance' => true,
            ]);
    }

    public function test_an_assistant_does_not_see_the_whole_exam(): void
    {
        $docente = $this->docenteQueSigue();
        $aula = $this->aula();
        $examen = $this->examen($docente, [$this->grupo($docente)], [$aula]);
        $auxiliar = $this->auxiliarQueSigue();
        $this->ponerACargo($examen, $aula, $auxiliar);

        $this->actingAs($auxiliar)
            ->getJson("/api/examenes/{$examen->id}/en-curso")
            ->assertForbidden()
            ->assertJsonPath('alcance', true);
    }

    public function test_an_unknown_exam_is_reported(): void
    {
        $this->actingAs($this->usuarioConPermisos(['seguimiento_vivo']))
            ->getJson('/api/examenes/999999/en-curso')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Examen no encontrado.']);
    }

    public function test_the_administrator_does_not_follow_exams(): void
    {
        $docente = $this->docenteQueSigue();
        $aula = $this->aula();
        $examen = $this->examen($docente, [$this->grupo($docente)], [$aula]);
        $administrador = $this->usuarioConRol('Administrador');

        foreach ($this->rutas($examen->id, $aula->id) as $ruta) {
            $this->actingAs($administrador)
                ->getJson($ruta)
                ->assertForbidden()
                ->assertJsonPath('permiso_requerido', 'seguimiento_vivo');
        }
    }

    public function test_the_teacher_and_the_assistant_roles_bring_the_permission(): void
    {
        $this->actingAs($this->usuarioConRol('Docente'))
            ->getJson('/api/en-curso')
            ->assertOk();

        $this->actingAs($this->usuarioConRol('Auxiliar'))
            ->getJson('/api/en-curso')
            ->assertOk();
    }

    public function test_a_guest_cannot_follow_exams(): void
    {
        $docente = $this->docenteQueSigue();
        $aula = $this->aula();
        $examen = $this->examen($docente, [$this->grupo($docente)], [$aula]);

        foreach ($this->rutas($examen->id, $aula->id) as $ruta) {
            $this->getJson($ruta)->assertUnauthorized();
        }
    }

    public function test_the_monitoring_routes_are_read_only(): void
    {
        $docente = $this->docenteQueSigue();
        $grupo = $this->grupo($docente);
        $aula = $this->aula();
        $examen = $this->examen($docente, [$grupo], [$aula]);
        $estudiantes = $this->estudiantesInscritos($grupo, 2);
        $this->habilitar($examen, $estudiantes, $aula);
        $this->ingresar($examen, $estudiantes[0], $aula);
        $cuenta = $this->cuentaDe($docente);

        foreach ($this->rutas($examen->id, $aula->id) as $ruta) {
            foreach (['postJson', 'putJson', 'patchJson', 'deleteJson'] as $verbo) {
                $this->actingAs($cuenta)->{$verbo}($ruta)->assertMethodNotAllowed();
            }

            $this->actingAs($cuenta)->getJson($ruta)->assertOk();
        }

        $this->assertDatabaseCount('ingresos', 1);
        $this->assertDatabaseCount('habilitaciones', 2);
        $this->assertDatabaseCount('incidencias', 0);
        $this->assertDatabaseCount('bitacora_operaciones', 0);
    }

    /**
     * @return list<string>
     */
    private function rutas(int $examenId, int $aulaId): array
    {
        return [
            '/api/en-curso',
            "/api/examenes/{$examenId}/en-curso",
            "/api/examenes/{$examenId}/aulas/{$aulaId}/asistencia",
        ];
    }
}
