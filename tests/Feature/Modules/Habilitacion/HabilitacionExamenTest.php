<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Habilitacion;

use App\Modules\Academico\Domain\Models\Docente;
use App\Modules\Academico\Domain\Models\Grupo;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Estudiantes\Domain\Enums\OrigenEstudiante;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use App\Modules\Estudiantes\Domain\Models\Inscripcion;
use App\Modules\Habilitacion\Application\Contracts\HabilitacionGateway;
use App\Modules\Habilitacion\Application\DTOs\HabilitacionData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * Rutas 54 y 55: la lista de habilitacion de un examen y el cambio de
 * condicion en lote. Tambien el contrato que lee la puerta.
 */
final class HabilitacionExamenTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;
    use UsuarioConPermisos;

    private User $cuenta;

    private Docente $docente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cuenta = $this->usuarioConPermisos(['habilitacion']);
        $this->docente = $this->docenteConCuenta($this->cuenta);
    }

    // ----- Ruta 54: la lista -------------------------------------------

    public function test_the_list_is_the_enrolled_students_of_the_exam_groups_not_the_whole_roll(): void
    {
        $grupo = $this->grupo($this->docente);
        $otroGrupo = $this->grupo($this->docente);
        $inscritos = $this->estudiantesInscritos($grupo, 3);
        $this->estudiantesInscritos($otroGrupo, 2);
        $examen = $this->examen($this->docente, [$grupo]);

        $respuesta = $this->actingAs($this->cuenta)
            ->getJson("/api/examenes/{$examen->id}/habilitaciones")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.cifras.inscritos', 3);

        $this->assertEqualsCanonicalizing(
            array_map(static fn (Estudiante $e): int => $e->id, $inscritos),
            array_column($this->filas($respuesta->json('data')), 'estudiante_id'),
        );
    }

    public function test_a_student_enrolled_in_two_groups_of_the_exam_appears_once_with_the_lowest_group(): void
    {
        $asignatura = $this->asignatura();
        $grupoB = $this->grupo($this->docente, $asignatura, '5');
        $grupoA = $this->grupo($this->docente, $asignatura, '2');
        [$estudiante] = $this->estudiantesInscritos($grupoB, 1);
        $this->inscribir($estudiante, $grupoA);
        $examen = $this->examen($this->docente, [$grupoB, $grupoA]);

        $this->actingAs($this->cuenta)
            ->getJson("/api/examenes/{$examen->id}/habilitaciones")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.estudiante_id', $estudiante->id)
            ->assertJsonPath('data.0.grupo', '2')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.cifras.inscritos', 1);
    }

    public function test_not_reviewed_is_the_absence_of_a_row(): void
    {
        $grupo = $this->grupo($this->docente);
        $this->estudiantesInscritos($grupo, 2);
        $examen = $this->examen($this->docente, [$grupo]);

        $this->actingAs($this->cuenta)
            ->getJson("/api/examenes/{$examen->id}/habilitaciones")
            ->assertOk()
            ->assertJsonPath('data.0.estado', 'pendiente')
            ->assertJsonPath('data.0.aula', null)
            ->assertJsonPath('data.0.motivo', null)
            ->assertJsonPath('meta.cifras.sin_revisar', 2)
            ->assertJsonPath('meta.condiciones.pendiente', 2);

        $this->assertDatabaseCount('habilitaciones', 0);
    }

    public function test_each_row_carries_the_student_its_group_its_condition_room_and_reason(): void
    {
        $grupo = $this->grupo($this->docente, null, '7');
        $aula = $this->aula('691B');
        [$uno, $dos] = $this->estudiantesInscritos($grupo, 2);
        $uno->update(['apellidos' => 'Aguilar Cossío', 'nombres' => 'Mariana']);
        $dos->update(['apellidos' => 'Zurita Paz', 'nombres' => 'Omar']);
        $examen = $this->examen($this->docente, [$grupo], [$aula]);
        $this->habilitar($examen, [$uno], $aula);
        $this->inhabilitar($examen, [$dos], 'Adeuda la matrícula');

        $this->actingAs($this->cuenta)
            ->getJson("/api/examenes/{$examen->id}/habilitaciones")
            ->assertOk()
            ->assertJsonPath('data.0', [
                'estudiante_id' => $uno->id,
                'codigo' => $uno->codigo_universitario,
                'nombre' => 'Aguilar Cossío, Mariana',
                'documento' => $uno->documento_identidad,
                'grupo' => '7',
                'estado' => 'habilitado',
                'aula' => '691B',
                'motivo' => null,
            ])
            ->assertJsonPath('data.1.estado', 'no')
            ->assertJsonPath('data.1.aula', null)
            ->assertJsonPath('data.1.motivo', 'Adeuda la matrícula');
    }

    public function test_the_list_is_ordered_by_surnames_and_names(): void
    {
        $grupo = $this->grupo($this->docente);
        [$a, $b, $c] = $this->estudiantesInscritos($grupo, 3);
        $a->update(['apellidos' => 'Rojas', 'nombres' => 'Ana']);
        $b->update(['apellidos' => 'Aguilar', 'nombres' => 'Zoe']);
        $c->update(['apellidos' => 'Aguilar', 'nombres' => 'Beto']);
        $examen = $this->examen($this->docente, [$grupo]);

        $respuesta = $this->actingAs($this->cuenta)
            ->getJson("/api/examenes/{$examen->id}/habilitaciones")
            ->assertOk();

        $this->assertSame(
            [$c->id, $b->id, $a->id],
            array_column($this->filas($respuesta->json('data')), 'estudiante_id'),
        );
    }

    public function test_the_list_is_paginated_on_the_server(): void
    {
        $grupo = $this->grupo($this->docente);
        $this->estudiantesInscritos($grupo, 30);
        $examen = $this->examen($this->docente, [$grupo]);

        $this->actingAs($this->cuenta)
            ->getJson("/api/examenes/{$examen->id}/habilitaciones")
            ->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.total', 30)
            ->assertJsonPath('meta.pagina', 1)
            ->assertJsonPath('meta.por_pagina', 25);

        $this->actingAs($this->cuenta)
            ->getJson("/api/examenes/{$examen->id}/habilitaciones?pagina=2&por_pagina=20")
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 30)
            ->assertJsonPath('meta.pagina', 2)
            ->assertJsonPath('meta.por_pagina', 20);
    }

    public function test_the_list_filters_by_group(): void
    {
        $asignatura = $this->asignatura();
        $grupoUno = $this->grupo($this->docente, $asignatura, '1');
        $grupoDos = $this->grupo($this->docente, $asignatura, '2');
        $this->estudiantesInscritos($grupoUno, 2);
        $this->estudiantesInscritos($grupoDos, 3);
        $examen = $this->examen($this->docente, [$grupoUno, $grupoDos]);

        $this->actingAs($this->cuenta)
            ->getJson("/api/examenes/{$examen->id}/habilitaciones?grupo={$grupoDos->id}")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.grupo', '2')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.condiciones.todos', 3)
            ->assertJsonPath('meta.cifras.inscritos', 5);
    }

    public function test_the_list_filters_by_room(): void
    {
        $grupo = $this->grupo($this->docente);
        $aulaUno = $this->aula();
        $aulaDos = $this->aula();
        $estudiantes = $this->estudiantesInscritos($grupo, 5);
        $examen = $this->examen($this->docente, [$grupo], [$aulaUno, $aulaDos]);
        $this->habilitar($examen, array_slice($estudiantes, 0, 2), $aulaUno);
        $this->habilitar($examen, array_slice($estudiantes, 2, 1), $aulaDos);

        $this->actingAs($this->cuenta)
            ->getJson("/api/examenes/{$examen->id}/habilitaciones?aula={$aulaUno->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.condiciones', ['todos' => 2, 'habilitado' => 2, 'no' => 0, 'pendiente' => 0]);
    }

    public function test_the_list_filters_by_condition_and_counts_every_condition_without_that_filter(): void
    {
        $grupo = $this->grupo($this->docente);
        $estudiantes = $this->estudiantesInscritos($grupo, 6);
        $examen = $this->examen($this->docente, [$grupo]);
        $this->habilitar($examen, array_slice($estudiantes, 0, 3));
        $this->inhabilitar($examen, array_slice($estudiantes, 3, 2));

        $esperado = ['todos' => 6, 'habilitado' => 3, 'no' => 2, 'pendiente' => 1];

        foreach (['habilitado' => 3, 'no' => 2, 'pendiente' => 1] as $condicion => $cantidad) {
            $this->actingAs($this->cuenta)
                ->getJson("/api/examenes/{$examen->id}/habilitaciones?condicion={$condicion}")
                ->assertOk()
                ->assertJsonCount($cantidad, 'data')
                ->assertJsonPath('data.0.estado', $condicion)
                ->assertJsonPath('meta.total', $cantidad)
                ->assertJsonPath('meta.condiciones', $esperado);
        }
    }

    public function test_the_search_ignores_case_and_accents_and_looks_at_name_code_and_document(): void
    {
        $grupo = $this->grupo($this->docente);
        [$uno, $dos] = $this->estudiantesInscritos($grupo, 2);
        $uno->update([
            'apellidos' => 'Aguilar Cossío',
            'nombres' => 'Mariana',
            'codigo_universitario' => '202105877',
            'documento_identidad' => '7361424',
        ]);
        $dos->update([
            'apellidos' => 'Peña Rocha',
            'nombres' => 'Óscar',
            'codigo_universitario' => '201900011',
            'documento_identidad' => '9988776',
        ]);
        $examen = $this->examen($this->docente, [$grupo]);

        $casos = [
            'cossio' => $uno->id,
            'MARIANA aguilar' => $uno->id,
            'Aguilar Cossío, Mariana' => $uno->id,
            'pena' => $dos->id,
            'oscar' => $dos->id,
            '2021058' => $uno->id,
            '9988776' => $dos->id,
        ];

        foreach ($casos as $texto => $esperado) {
            $this->actingAs($this->cuenta)
                ->getJson("/api/examenes/{$examen->id}/habilitaciones?buscar=".urlencode((string) $texto))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.estudiante_id', $esperado)
                ->assertJsonPath('meta.condiciones.todos', 1);
        }

        $this->actingAs($this->cuenta)
            ->getJson("/api/examenes/{$examen->id}/habilitaciones?buscar=nadie")
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_the_search_treats_wildcards_as_plain_text(): void
    {
        $grupo = $this->grupo($this->docente);
        $this->estudiantesInscritos($grupo, 2);
        $examen = $this->examen($this->docente, [$grupo]);

        $this->actingAs($this->cuenta)
            ->getJson("/api/examenes/{$examen->id}/habilitaciones?buscar=".urlencode('%'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_the_figures_and_the_rooms_are_of_the_whole_exam_whatever_the_filters(): void
    {
        $grupo = $this->grupo($this->docente);
        $aulaUno = $this->aula('691A');
        $aulaDos = $this->aula('691B');
        $estudiantes = $this->estudiantesInscritos($grupo, 7);
        $examen = $this->examen($this->docente, [$grupo], [$aulaUno, $aulaDos]);
        $this->habilitar($examen, array_slice($estudiantes, 0, 2), $aulaUno);
        $this->habilitar($examen, array_slice($estudiantes, 2, 1), $aulaDos);
        $this->habilitar($examen, array_slice($estudiantes, 3, 1));
        $this->inhabilitar($examen, array_slice($estudiantes, 4, 2));

        $this->actingAs($this->cuenta)
            ->getJson("/api/examenes/{$examen->id}/habilitaciones?condicion=no&buscar=nadie")
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.cifras', [
                'inscritos' => 7,
                'habilitados' => 4,
                'no_habilitados' => 2,
                'sin_revisar' => 1,
                'sin_aula' => 1,
            ])
            ->assertJsonPath('meta.por_aula', [
                ['aula_id' => $aulaUno->id, 'nombre' => '691A', 'asignados' => 2],
                ['aula_id' => $aulaDos->id, 'nombre' => '691B', 'asignados' => 1],
            ]);
    }

    public function test_the_groups_of_the_exam_say_which_ones_belong_to_the_teacher(): void
    {
        $asignatura = $this->asignatura();
        $propio = $this->grupo($this->docente, $asignatura, '2');
        $ajeno = $this->grupo($this->docenteConCuenta(), $asignatura, '1');
        $sinDocente = $this->grupo(null, $asignatura, '3');
        $examen = $this->examen($this->docente, [$propio, $ajeno, $sinDocente]);

        $this->actingAs($this->cuenta)
            ->getJson("/api/examenes/{$examen->id}/habilitaciones")
            ->assertOk()
            ->assertJsonPath('meta.grupos', [
                ['id' => $ajeno->id, 'codigo' => '1', 'propio' => false],
                ['id' => $propio->id, 'codigo' => '2', 'propio' => true],
                ['id' => $sinDocente->id, 'codigo' => '3', 'propio' => false],
            ]);
    }

    public function test_the_filters_of_the_list_are_validated(): void
    {
        $examen = $this->examen($this->docente, [$this->grupo($this->docente)]);

        $this->actingAs($this->cuenta)
            ->getJson("/api/examenes/{$examen->id}/habilitaciones?condicion=quizas&por_pagina=101&pagina=0&grupo=x")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['condicion', 'por_pagina', 'pagina', 'grupo'])
            ->assertJsonPath('errors.condicion.0', 'La condición debe ser habilitado, no o pendiente.')
            ->assertJsonPath('errors.por_pagina.0', 'Entre 1 y 100 por página.');
    }

    public function test_the_exam_of_the_list_must_exist(): void
    {
        $this->actingAs($this->cuenta)
            ->getJson('/api/examenes/999999/habilitaciones')
            ->assertNotFound()
            ->assertJsonPath('message', 'Examen no encontrado.');
    }

    public function test_a_guest_cannot_see_the_list(): void
    {
        $examen = $this->examen($this->docente, [$this->grupo($this->docente)]);

        $this->getJson("/api/examenes/{$examen->id}/habilitaciones")->assertUnauthorized();
    }

    public function test_a_role_without_the_permission_cannot_see_the_list(): void
    {
        $examen = $this->examen($this->docente, [$this->grupo($this->docente)]);

        $this->actingAs($this->usuarioConRol('Auxiliar'))
            ->getJson("/api/examenes/{$examen->id}/habilitaciones")
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'habilitacion');
    }

    public function test_a_teacher_that_is_not_of_the_exam_cannot_see_the_list(): void
    {
        $grupo = $this->grupo($this->docente);
        $this->estudiantesInscritos($grupo, 1);
        $examen = $this->examen($this->docente, [$grupo]);

        $otra = $this->usuarioConPermisos(['habilitacion']);
        $this->docenteConCuenta($otra);

        $this->actingAs($otra)
            ->getJson("/api/examenes/{$examen->id}/habilitaciones")
            ->assertForbidden()
            ->assertExactJson(['message' => 'Este examen no es tuyo.', 'alcance' => true]);
    }

    public function test_the_teacher_of_an_included_group_works_on_an_exam_registered_by_another(): void
    {
        $asignatura = $this->asignatura();
        $otra = $this->usuarioConPermisos(['habilitacion']);
        $colega = $this->docenteConCuenta($otra);
        $grupoPropio = $this->grupo($this->docente, $asignatura, '1');
        $grupoColega = $this->grupo($colega, $asignatura, '2');
        [$estudiante] = $this->estudiantesInscritos($grupoPropio, 1);
        $examen = $this->examen($this->docente, [$grupoPropio, $grupoColega]);

        $this->actingAs($otra)
            ->getJson("/api/examenes/{$examen->id}/habilitaciones")
            ->assertOk()
            ->assertJsonPath('meta.grupos.0.propio', false)
            ->assertJsonPath('meta.grupos.1.propio', true);

        $this->actingAs($otra)
            ->postJson("/api/examenes/{$examen->id}/habilitaciones", [
                'habilitado' => true,
                'estudiantes' => [$estudiante->id],
            ])
            ->assertOk();

        $this->assertDatabaseHas('habilitaciones', [
            'examen_id' => $examen->id,
            'estudiante_id' => $estudiante->id,
            'habilitado' => true,
            'registrada_por' => $otra->id,
        ]);
    }

    // ----- Ruta 55: habilitar / inhabilitar ----------------------------

    public function test_a_batch_of_students_is_enabled_by_their_ids(): void
    {
        $grupo = $this->grupo($this->docente);
        [$uno, $dos, $tres] = $this->estudiantesInscritos($grupo, 3);
        $examen = $this->examen($this->docente, [$grupo]);

        $this->actingAs($this->cuenta)
            ->postJson("/api/examenes/{$examen->id}/habilitaciones", [
                'habilitado' => true,
                'estudiantes' => [$uno->id, $dos->id],
            ])
            ->assertOk()
            ->assertExactJson([
                'message' => '2 habilitados.',
                'afectados' => 2,
                'cifras' => [
                    'inscritos' => 3,
                    'habilitados' => 2,
                    'no_habilitados' => 0,
                    'sin_revisar' => 1,
                    'sin_aula' => 2,
                ],
            ]);

        foreach ([$uno, $dos] as $estudiante) {
            $this->assertDatabaseHas('habilitaciones', [
                'examen_id' => $examen->id,
                'estudiante_id' => $estudiante->id,
                'habilitado' => true,
                'aula_id' => null,
                'motivo' => null,
                'registrada_por' => $this->cuenta->id,
            ]);
        }

        $this->assertDatabaseMissing('habilitaciones', ['estudiante_id' => $tres->id]);
    }

    public function test_everything_the_filters_leave_is_enabled_at_once(): void
    {
        $asignatura = $this->asignatura();
        $grupoUno = $this->grupo($this->docente, $asignatura, '1');
        $grupoDos = $this->grupo($this->docente, $asignatura, '2');
        $delUno = $this->estudiantesInscritos($grupoUno, 30);
        $delDos = $this->estudiantesInscritos($grupoDos, 4);
        $examen = $this->examen($this->docente, [$grupoUno, $grupoDos]);
        $this->inhabilitar($examen, [$delUno[0]]);

        // Mas de una pagina: la seleccion vale para todo lo filtrado.
        $this->actingAs($this->cuenta)
            ->postJson("/api/examenes/{$examen->id}/habilitaciones", [
                'habilitado' => true,
                'todos' => true,
                'filtros' => ['grupo' => $grupoUno->id, 'aula' => null, 'condicion' => 'pendiente', 'buscar' => ''],
            ])
            ->assertOk()
            ->assertJsonPath('message', '29 habilitados.')
            ->assertJsonPath('afectados', 29)
            ->assertJsonPath('cifras.habilitados', 29)
            ->assertJsonPath('cifras.no_habilitados', 1)
            ->assertJsonPath('cifras.sin_revisar', 4);

        $this->assertDatabaseHas('habilitaciones', ['estudiante_id' => $delUno[0]->id, 'habilitado' => false]);
        $this->assertDatabaseMissing('habilitaciones', ['estudiante_id' => $delDos[0]->id]);
    }

    public function test_all_without_filters_reaches_the_whole_list(): void
    {
        $grupo = $this->grupo($this->docente);
        $this->estudiantesInscritos($grupo, 4);
        $examen = $this->examen($this->docente, [$grupo]);

        $this->actingAs($this->cuenta)
            ->postJson("/api/examenes/{$examen->id}/habilitaciones", ['habilitado' => true, 'todos' => true])
            ->assertOk()
            ->assertJsonPath('afectados', 4)
            ->assertJsonPath('cifras.sin_revisar', 0);
    }

    public function test_a_student_is_disabled_with_the_reason_and_loses_the_room(): void
    {
        $grupo = $this->grupo($this->docente);
        $aula = $this->aula();
        [$uno, $dos] = $this->estudiantesInscritos($grupo, 2);
        $examen = $this->examen($this->docente, [$grupo], [$aula]);
        $this->habilitar($examen, [$uno, $dos], $aula);

        $this->actingAs($this->cuenta)
            ->postJson("/api/examenes/{$examen->id}/habilitaciones", [
                'habilitado' => false,
                'motivo' => 'Adeuda la matrícula del semestre',
                'estudiantes' => [$uno->id],
            ])
            ->assertOk()
            ->assertJsonPath('message', '1 inhabilitado.')
            ->assertJsonPath('afectados', 1)
            ->assertJsonPath('cifras.habilitados', 1)
            ->assertJsonPath('cifras.no_habilitados', 1);

        $this->assertDatabaseHas('habilitaciones', [
            'examen_id' => $examen->id,
            'estudiante_id' => $uno->id,
            'habilitado' => false,
            'aula_id' => null,
            'motivo' => 'Adeuda la matrícula del semestre',
            'registrada_por' => $this->cuenta->id,
        ]);
        $this->assertDatabaseHas('habilitaciones', [
            'estudiante_id' => $dos->id,
            'habilitado' => true,
            'aula_id' => $aula->id,
        ]);
    }

    public function test_enabling_does_not_touch_the_room_of_who_was_already_enabled(): void
    {
        $grupo = $this->grupo($this->docente);
        $aula = $this->aula();
        [$ubicado, $nuevo] = $this->estudiantesInscritos($grupo, 2);
        $examen = $this->examen($this->docente, [$grupo], [$aula]);
        $this->habilitar($examen, [$ubicado], $aula);

        $this->actingAs($this->cuenta)
            ->postJson("/api/examenes/{$examen->id}/habilitaciones", [
                'habilitado' => true,
                'estudiantes' => [$ubicado->id, $nuevo->id],
            ])
            ->assertOk()
            ->assertJsonPath('message', '1 habilitado.')
            ->assertJsonPath('afectados', 1);

        $this->assertDatabaseHas('habilitaciones', [
            'estudiante_id' => $ubicado->id,
            'habilitado' => true,
            'aula_id' => $aula->id,
        ]);
        $this->assertDatabaseHas('habilitaciones', [
            'estudiante_id' => $nuevo->id,
            'habilitado' => true,
            'aula_id' => null,
        ]);
    }

    public function test_the_condition_of_a_student_is_replaced_not_duplicated(): void
    {
        $grupo = $this->grupo($this->docente);
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);
        $examen = $this->examen($this->docente, [$grupo]);
        $ruta = "/api/examenes/{$examen->id}/habilitaciones";

        $this->actingAs($this->cuenta)
            ->postJson($ruta, ['habilitado' => false, 'motivo' => 'Sin matrícula', 'estudiantes' => [$estudiante->id]])
            ->assertOk();
        $this->actingAs($this->cuenta)
            ->postJson($ruta, ['habilitado' => false, 'motivo' => 'Sin boleta de pago', 'estudiantes' => [$estudiante->id]])
            ->assertOk()
            ->assertJsonPath('afectados', 1);

        $this->assertDatabaseCount('habilitaciones', 1);
        $this->assertDatabaseHas('habilitaciones', ['habilitado' => false, 'motivo' => 'Sin boleta de pago']);

        $this->actingAs($this->cuenta)
            ->postJson($ruta, ['habilitado' => true, 'estudiantes' => [$estudiante->id]])
            ->assertOk()
            ->assertJsonPath('afectados', 1);

        $this->assertDatabaseCount('habilitaciones', 1);
        $this->assertDatabaseHas('habilitaciones', [
            'estudiante_id' => $estudiante->id,
            'habilitado' => true,
            'motivo' => null,
        ]);
    }

    public function test_disabling_requires_a_reason_of_at_least_five_characters(): void
    {
        $grupo = $this->grupo($this->docente);
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);
        $examen = $this->examen($this->docente, [$grupo]);

        foreach ([[], ['motivo' => ''], ['motivo' => 'No'], ['motivo' => '    abc   ']] as $motivo) {
            $this->actingAs($this->cuenta)
                ->postJson("/api/examenes/{$examen->id}/habilitaciones", [
                    'habilitado' => false,
                    'estudiantes' => [$estudiante->id],
                ] + $motivo)
                ->assertUnprocessable()
                ->assertJsonPath('errors.motivo.0', 'Mínimo 5 caracteres');
        }

        $this->actingAs($this->cuenta)
            ->postJson("/api/examenes/{$examen->id}/habilitaciones", [
                'habilitado' => false,
                'motivo' => str_repeat('a', 1001),
                'estudiantes' => [$estudiante->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo']);

        $this->assertDatabaseCount('habilitaciones', 0);
    }

    public function test_the_reason_is_ignored_when_enabling(): void
    {
        $grupo = $this->grupo($this->docente);
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);
        $examen = $this->examen($this->docente, [$grupo]);

        $this->actingAs($this->cuenta)
            ->postJson("/api/examenes/{$examen->id}/habilitaciones", [
                'habilitado' => true,
                'motivo' => 'x',
                'estudiantes' => [$estudiante->id],
            ])
            ->assertOk();

        $this->assertDatabaseHas('habilitaciones', [
            'estudiante_id' => $estudiante->id,
            'habilitado' => true,
            'motivo' => null,
        ]);
    }

    public function test_the_body_of_the_change_is_validated(): void
    {
        $examen = $this->examen($this->docente, [$this->grupo($this->docente)]);
        $ruta = "/api/examenes/{$examen->id}/habilitaciones";

        $this->actingAs($this->cuenta)
            ->postJson($ruta, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['habilitado', 'estudiantes'])
            ->assertJsonPath('errors.estudiantes.0', 'Selecciona al menos un estudiante.');

        $this->actingAs($this->cuenta)
            ->postJson($ruta, ['habilitado' => true, 'estudiantes' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['estudiantes']);

        $this->actingAs($this->cuenta)
            ->postJson($ruta, ['habilitado' => true, 'estudiantes' => ['abc']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['estudiantes.0']);

        $this->actingAs($this->cuenta)
            ->postJson($ruta, ['habilitado' => true, 'todos' => true, 'filtros' => ['condicion' => 'quizas']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['filtros.condicion']);
    }

    public function test_students_outside_the_groups_of_the_exam_are_rejected_and_nothing_is_saved(): void
    {
        $grupo = $this->grupo($this->docente);
        [$inscrito] = $this->estudiantesInscritos($grupo, 1);
        [$ajeno] = $this->estudiantesInscritos($this->grupo($this->docente), 1);
        $examen = $this->examen($this->docente, [$grupo]);

        $this->actingAs($this->cuenta)
            ->postJson("/api/examenes/{$examen->id}/habilitaciones", [
                'habilitado' => true,
                'estudiantes' => [$inscrito->id, $ajeno->id, 999999],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.estudiantes.0', '2 estudiantes no están inscritos en ningún grupo del examen.');

        $this->assertDatabaseCount('habilitaciones', 0);
        $this->assertDatabaseMissing('bitacora_operaciones', ['operacion' => 'habilitacion.habilitar']);
    }

    public function test_a_student_that_already_entered_cannot_be_disabled(): void
    {
        $grupo = $this->grupo($this->docente);
        $aula = $this->aula();
        [$adentro, $afuera] = $this->estudiantesInscritos($grupo, 2);
        $examen = $this->examen($this->docente, [$grupo], [$aula]);
        $this->habilitar($examen, [$adentro, $afuera], $aula);
        $this->ingresar($examen, $adentro, $aula);

        $this->actingAs($this->cuenta)
            ->postJson("/api/examenes/{$examen->id}/habilitaciones", [
                'habilitado' => false,
                'motivo' => 'Adeuda la matrícula',
                'estudiantes' => [$adentro->id, $afuera->id],
            ])
            ->assertConflict()
            ->assertJsonPath('codigo', 'ESTUDIANTE_CON_INGRESO');

        // El lote entero se rechaza: nadie cambia.
        $this->assertDatabaseMissing('habilitaciones', ['examen_id' => $examen->id, 'habilitado' => false]);
        $this->assertDatabaseMissing('bitacora_operaciones', ['operacion' => 'habilitacion.inhabilitar']);
    }

    public function test_selecting_everything_does_not_disable_who_already_entered_either(): void
    {
        $grupo = $this->grupo($this->docente);
        $aula = $this->aula();
        [$adentro, $afuera] = $this->estudiantesInscritos($grupo, 2);
        $examen = $this->examen($this->docente, [$grupo], [$aula]);
        $this->habilitar($examen, [$adentro, $afuera], $aula);
        // En este sprint nadie registra ingresos: la fila se inserta a mano.
        $this->ingresar($examen, $adentro);

        $this->actingAs($this->cuenta)
            ->postJson("/api/examenes/{$examen->id}/habilitaciones", [
                'habilitado' => false,
                'motivo' => 'Adeuda la matrícula',
                'todos' => true,
            ])
            ->assertConflict()
            ->assertJsonPath('codigo', 'ESTUDIANTE_CON_INGRESO')
            ->assertJsonPath(
                'message',
                'Un estudiante de la selección ya ingresó al examen y no se puede inhabilitar.',
            );

        $this->assertDatabaseHas('habilitaciones', [
            'examen_id' => $examen->id,
            'estudiante_id' => $adentro->id,
            'habilitado' => true,
            'aula_id' => $aula->id,
        ]);
        $this->assertDatabaseMissing('habilitaciones', ['examen_id' => $examen->id, 'habilitado' => false]);

        // Quien no ingreso si se puede inhabilitar.
        $this->actingAs($this->cuenta)
            ->postJson("/api/examenes/{$examen->id}/habilitaciones", [
                'habilitado' => false,
                'motivo' => 'Adeuda la matrícula',
                'estudiantes' => [$afuera->id],
            ])
            ->assertOk()
            ->assertJsonPath('afectados', 1);
    }

    public function test_each_batch_leaves_one_row_in_the_audit_log_with_the_amount(): void
    {
        $grupo = $this->grupo($this->docente);
        $estudiantes = $this->estudiantesInscritos($grupo, 3);
        $examen = $this->examen($this->docente, [$grupo]);
        $ids = array_map(static fn (Estudiante $e): int => $e->id, $estudiantes);
        $ruta = "/api/examenes/{$examen->id}/habilitaciones";

        $this->actingAs($this->cuenta)
            ->postJson($ruta, ['habilitado' => true, 'estudiantes' => $ids])
            ->assertOk();
        $this->actingAs($this->cuenta)
            ->postJson($ruta, ['habilitado' => false, 'motivo' => 'Sin matrícula', 'estudiantes' => [$ids[0], $ids[1]]])
            ->assertOk();

        $this->assertDatabaseCount('bitacora_operaciones', 2);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $this->cuenta->id,
            'operacion' => 'habilitacion.habilitar',
            'tabla_afectada' => 'habilitaciones',
            'registro_id' => $examen->id,
            'descripcion' => "3 estudiante(s) habilitado(s) en el examen {$examen->id}.",
        ]);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $this->cuenta->id,
            'operacion' => 'habilitacion.inhabilitar',
            'tabla_afectada' => 'habilitaciones',
            'registro_id' => $examen->id,
            'descripcion' => "2 estudiante(s) inhabilitado(s) en el examen {$examen->id}.",
        ]);
    }

    public function test_a_batch_that_changes_nobody_leaves_no_audit_row(): void
    {
        $grupo = $this->grupo($this->docente);
        $estudiantes = $this->estudiantesInscritos($grupo, 2);
        $examen = $this->examen($this->docente, [$grupo]);
        $this->habilitar($examen, $estudiantes);

        $this->actingAs($this->cuenta)
            ->postJson("/api/examenes/{$examen->id}/habilitaciones", ['habilitado' => true, 'todos' => true])
            ->assertOk()
            ->assertJsonPath('message', '0 habilitados.')
            ->assertJsonPath('afectados', 0);

        $this->assertDatabaseCount('bitacora_operaciones', 0);
    }

    public function test_the_exam_of_the_change_must_exist(): void
    {
        $this->actingAs($this->cuenta)
            ->postJson('/api/examenes/999999/habilitaciones', ['habilitado' => true, 'estudiantes' => [1]])
            ->assertNotFound()
            ->assertJsonPath('message', 'Examen no encontrado.');
    }

    public function test_a_guest_cannot_change_conditions(): void
    {
        $examen = $this->examen($this->docente, [$this->grupo($this->docente)]);

        $this->postJson("/api/examenes/{$examen->id}/habilitaciones", ['habilitado' => true, 'todos' => true])
            ->assertUnauthorized();
    }

    public function test_a_role_without_the_permission_cannot_change_conditions(): void
    {
        $grupo = $this->grupo($this->docente);
        $this->estudiantesInscritos($grupo, 1);
        $examen = $this->examen($this->docente, [$grupo]);

        $this->actingAs($this->usuarioConRol('Auxiliar'))
            ->postJson("/api/examenes/{$examen->id}/habilitaciones", ['habilitado' => true, 'todos' => true])
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'habilitacion');

        $this->assertDatabaseCount('habilitaciones', 0);
    }

    public function test_a_teacher_that_is_not_of_the_exam_cannot_change_conditions(): void
    {
        $grupo = $this->grupo($this->docente);
        $this->estudiantesInscritos($grupo, 1);
        $examen = $this->examen($this->docente, [$grupo]);

        $otra = $this->usuarioConPermisos(['habilitacion']);
        $this->docenteConCuenta($otra);

        $this->actingAs($otra)
            ->postJson("/api/examenes/{$examen->id}/habilitaciones", ['habilitado' => true, 'todos' => true])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Este examen no es tuyo.', 'alcance' => true]);

        $this->assertDatabaseCount('habilitaciones', 0);
    }

    // ----- Contrato publico: lo que lee la puerta ----------------------

    public function test_the_gateway_answers_null_for_a_student_not_reviewed(): void
    {
        $grupo = $this->grupo($this->docente);
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);
        $examen = $this->examen($this->docente, [$grupo]);

        $this->assertNull(
            $this->app->make(HabilitacionGateway::class)->condicionDe($examen->id, $estudiante->id),
        );
    }

    public function test_the_gateway_answers_the_condition_the_room_and_who_registered_it(): void
    {
        $grupo = $this->grupo($this->docente);
        $aula = $this->aula('617');
        [$habilitado, $inhabilitado] = $this->estudiantesInscritos($grupo, 2);
        $examen = $this->examen($this->docente, [$grupo], [$aula]);
        $otroExamen = $this->examen($this->docente, [$grupo], [], ['tipo' => 'FINAL']);
        $this->habilitar($examen, [$habilitado], $aula);
        $this->inhabilitar($examen, [$inhabilitado], 'Adeuda la matrícula');

        $gateway = $this->app->make(HabilitacionGateway::class);

        $condicion = $gateway->condicionDe($examen->id, $habilitado->id);
        $this->assertInstanceOf(HabilitacionData::class, $condicion);
        $this->assertSame($examen->id, $condicion->examenId);
        $this->assertSame($habilitado->id, $condicion->estudianteId);
        $this->assertTrue($condicion->habilitado);
        $this->assertSame($aula->id, $condicion->aulaId);
        $this->assertSame('617', $condicion->aulaNombre);
        $this->assertNull($condicion->motivo);

        $condicion = $gateway->condicionDe($examen->id, $inhabilitado->id);
        $this->assertInstanceOf(HabilitacionData::class, $condicion);
        $this->assertFalse($condicion->habilitado);
        $this->assertNull($condicion->aulaId);
        $this->assertSame('Adeuda la matrícula', $condicion->motivo);
        $this->assertSame($this->cuenta->id, $condicion->registradaPorId);
        $this->assertSame($this->cuenta->name, $condicion->registradaPor);
        $this->assertNotNull($condicion->registradaEn);
        $this->assertStringEndsWith('-04:00', $condicion->registradaEn);

        // La condicion es de ese examen, no del estudiante.
        $this->assertNull($gateway->condicionDe($otroExamen->id, $habilitado->id));
    }

    private function inscribir(Estudiante $estudiante, Grupo $grupo): void
    {
        Inscripcion::create([
            'estudiante_id' => $estudiante->id,
            'grupo_id' => $grupo->id,
            'via' => OrigenEstudiante::Administracion,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function filas(mixed $datos): array
    {
        $filas = [];

        foreach (is_array($datos) ? $datos : [] as $fila) {
            if (is_array($fila)) {
                /** @var array<string, mixed> $fila */
                $filas[] = $fila;
            }
        }

        return $filas;
    }
}
