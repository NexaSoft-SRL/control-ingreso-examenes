<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Monitoreo;

use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Academico\Domain\Models\Docente;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use App\Modules\Estudiantes\Domain\Models\Inscripcion;
use App\Modules\Examenes\Domain\Models\Examen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La asistencia de un aula (ruta 72): quienes faltan y quienes ingresaron,
 * con busqueda y paginas. La ve un docente del examen, o quien atiende esa
 * aula y solo el dia del examen.
 */
final class AsistenciaDeAulaTest extends TestCase
{
    use EscenarioDeSeguimiento;
    use RefreshDatabase;

    private Docente $docente;

    private Examen $examenDelDia;

    private Aula $aula;

    private Aula $otraAula;

    /**
     * @var list<Estudiante>
     */
    private array $estudiantes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->detenerReloj();

        $this->docente = $this->docenteQueSigue();
        $grupo = $this->grupo($this->docente, $this->asignatura('Cálculo I'), '2');
        $this->aula = $this->aula('691A');
        $this->otraAula = $this->aula('691B');
        $this->examenDelDia = $this->examen($this->docente, [$grupo], [$this->aula, $this->otraAula]);
        $this->estudiantes = $this->estudiantesInscritos($grupo, 6);

        foreach ([
            ['Mariana', 'Aguilar Cossío'],
            ['Fabiola', 'Rocha Peredo'],
            ['José María', 'Ñáñez Zúñiga'],
            ['Kevin', 'Céspedes Lima'],
            ['Andrea', 'Bustamante Roca'],
            ['Luis', 'Zenteno Paz'],
        ] as $i => [$nombres, $apellidos]) {
            $this->estudiantes[$i]->update(['nombres' => $nombres, 'apellidos' => $apellidos]);
        }

        // Cuatro en el aula, uno en la otra, uno inhabilitado.
        $this->habilitar($this->examenDelDia, array_slice($this->estudiantes, 0, 4), $this->aula);
        $this->habilitar($this->examenDelDia, [$this->estudiantes[4]], $this->otraAula);
        $this->inhabilitar($this->examenDelDia, [$this->estudiantes[5]]);
    }

    private function ruta(?Aula $aula = null, ?Examen $examen = null): string
    {
        $examen ??= $this->examenDelDia;
        $aula ??= $this->aula;

        return "/api/examenes/{$examen->id}/aulas/{$aula->id}/asistencia";
    }

    public function test_the_missing_list_brings_the_enabled_students_of_the_room_without_entry(): void
    {
        $this->ingresar($this->examenDelDia, $this->estudiantes[1], $this->aula);

        $respuesta = $this->actingAs($this->cuentaDe($this->docente))
            ->getJson($this->ruta())
            ->assertOk()
            ->assertJsonPath('examen.id', $this->examenDelDia->id)
            ->assertJsonPath('examen.asignatura', 'Cálculo I')
            ->assertJsonPath('examen.tipo', 'Primer parcial')
            ->assertJsonPath('examen.hora', '09:55')
            ->assertJsonPath('aula', ['id' => $this->aula->id, 'nombre' => '691A'])
            ->assertJsonPath('abierto', true)
            ->assertJsonPath('hora_servidor', now()->format('Y-m-d\TH:i:sP'))
            ->assertJsonPath('cifras', ['ingresaron' => 1, 'faltan' => 3, 'asignados' => 4, 'en_otra_aula' => 0, 'de_otra_aula' => 0])
            ->assertJsonPath('meta', ['total' => 3, 'pagina' => 1, 'por_pagina' => 25])
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0', [
                'codigo' => $this->estudiantes[0]->codigo_universitario,
                'nombre' => 'Aguilar Cossío Mariana',
                'grupo' => '2',
            ])
            ->assertJsonPath('data.1.nombre', 'Céspedes Lima Kevin')
            ->assertJsonPath('data.2.nombre', 'Ñáñez Zúñiga José María');

        $this->assertArrayNotHasKey('hora', (array) $respuesta->json('data.0'));
    }

    public function test_the_entered_list_brings_the_entries_of_the_room_most_recent_first(): void
    {
        $this->detenerReloj('10:02:41');
        $this->ingresar($this->examenDelDia, $this->estudiantes[0], $this->aula);
        $this->detenerReloj('10:14:52');
        $this->ingresar($this->examenDelDia, $this->estudiantes[1], $this->aula);
        $this->ingresar($this->examenDelDia, $this->estudiantes[4], $this->otraAula);

        $this->actingAs($this->cuentaDe($this->docente))
            ->getJson($this->ruta().'?lista=ingresaron')
            ->assertOk()
            ->assertJsonPath('cifras', ['ingresaron' => 2, 'faltan' => 2, 'asignados' => 4, 'en_otra_aula' => 0, 'de_otra_aula' => 0])
            ->assertJsonPath('meta.total', 2)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0', [
                'codigo' => $this->estudiantes[1]->codigo_universitario,
                'nombre' => 'Rocha Peredo Fabiola',
                'grupo' => '2',
                'hora' => '10:14:52',
                'expulsado' => false,
            ])
            ->assertJsonPath('data.1.nombre', 'Aguilar Cossío Mariana')
            ->assertJsonPath('data.1.hora', '10:02:41');
    }

    public function test_an_expelled_student_stays_among_those_who_entered(): void
    {
        $this->ingresar($this->examenDelDia, $this->estudiantes[0], $this->aula);
        $this->expulsar($this->ingresar($this->examenDelDia, $this->estudiantes[1], $this->aula));

        $cuenta = $this->cuentaDe($this->docente);

        $this->actingAs($cuenta)
            ->getJson($this->ruta().'?lista=ingresaron&buscar=rocha')
            ->assertOk()
            ->assertJsonPath('cifras.ingresaron', 2)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Rocha Peredo Fabiola')
            ->assertJsonPath('data.0.expulsado', true);

        $this->actingAs($cuenta)
            ->getJson($this->ruta().'?lista=ingresaron&buscar=aguilar')
            ->assertOk()
            ->assertJsonPath('data.0.expulsado', false);

        $this->actingAs($cuenta)
            ->getJson($this->ruta())
            ->assertOk()
            ->assertJsonPath('cifras.faltan', 2)
            ->assertJsonMissing(['nombre' => 'Rocha Peredo Fabiola']);
    }

    public function test_a_student_admitted_through_another_room_is_no_longer_missing(): void
    {
        // Le tocaba la 691A y entro por la 691B (cambio de aula).
        $this->ingresar($this->examenDelDia, $this->estudiantes[0], $this->otraAula);

        $cuenta = $this->cuentaDe($this->docente);

        $this->actingAs($cuenta)
            ->getJson($this->ruta())
            ->assertOk()
            ->assertJsonPath('cifras', ['ingresaron' => 0, 'faltan' => 3, 'asignados' => 4, 'en_otra_aula' => 1, 'de_otra_aula' => 0]);

        $this->actingAs($cuenta)
            ->getJson($this->ruta($this->otraAula).'?lista=ingresaron')
            ->assertOk()
            ->assertJsonPath('cifras', ['ingresaron' => 1, 'faltan' => 1, 'asignados' => 1, 'en_otra_aula' => 0, 'de_otra_aula' => 1])
            ->assertJsonPath('data.0.nombre', 'Aguilar Cossío Mariana');
    }

    public function test_the_lists_are_searched_by_name_and_code_ignoring_case_and_accents(): void
    {
        $cuenta = $this->cuentaDe($this->docente);

        foreach ([
            'cossio' => 'Aguilar Cossío Mariana',
            'NANEZ' => 'Ñáñez Zúñiga José María',
            'jose maria ñáñez' => 'Ñáñez Zúñiga José María',
            'Céspedes Lima Kevin' => 'Céspedes Lima Kevin',
            $this->estudiantes[1]->codigo_universitario => 'Rocha Peredo Fabiola',
        ] as $buscar => $nombre) {
            $this->actingAs($cuenta)
                ->getJson($this->ruta().'?'.http_build_query(['buscar' => (string) $buscar]))
                ->assertOk()
                ->assertJsonPath('meta.total', 1)
                ->assertJsonPath('data.0.nombre', $nombre)
                // Las cifras son del aula entera, no de la busqueda.
                ->assertJsonPath('cifras.faltan', 4);
        }

        $this->actingAs($cuenta)
            ->getJson($this->ruta().'?buscar=100%25')
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('data', []);
    }

    public function test_the_lists_are_searched_by_identity_document_too(): void
    {
        $cuenta = $this->cuentaDe($this->docente);
        $this->estudiantes[2]->update(['documento_identidad' => '9412345-1K']);
        $this->estudiantes[3]->update(['documento_identidad' => null]);
        $this->ingresar($this->examenDelDia, $this->estudiantes[2], $this->aula);

        // Completo o por una parte, sin distinguir mayusculas.
        foreach (['9412345-1K', '12345-1k'] as $buscar) {
            $this->actingAs($cuenta)
                ->getJson($this->ruta().'?'.http_build_query(['lista' => 'ingresaron', 'buscar' => $buscar]))
                ->assertOk()
                ->assertJsonPath('meta.total', 1)
                ->assertJsonPath('data.0.nombre', 'Ñáñez Zúñiga José María');
        }

        $this->actingAs($cuenta)
            ->getJson($this->ruta().'?'.http_build_query(['buscar' => $this->estudiantes[1]->documento_identidad]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.nombre', 'Rocha Peredo Fabiola');

        // Quien no tiene documento sigue saliendo por nombre y sin filtro.
        $this->actingAs($cuenta)
            ->getJson($this->ruta().'?buscar=cespedes')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
        $this->actingAs($cuenta)->getJson($this->ruta())->assertOk()->assertJsonPath('meta.total', 3);
    }

    public function test_the_lists_are_paged_on_the_server(): void
    {
        $cuenta = $this->cuentaDe($this->docente);

        $this->actingAs($cuenta)
            ->getJson($this->ruta().'?por_pagina=3')
            ->assertOk()
            ->assertJsonPath('meta', ['total' => 4, 'pagina' => 1, 'por_pagina' => 3])
            ->assertJsonCount(3, 'data');

        $this->actingAs($cuenta)
            ->getJson($this->ruta().'?por_pagina=3&pagina=2')
            ->assertOk()
            ->assertJsonPath('meta', ['total' => 4, 'pagina' => 2, 'por_pagina' => 3])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Rocha Peredo Fabiola');
    }

    public function test_a_student_enrolled_in_two_groups_shows_the_lowest_group_code(): void
    {
        $asignatura = $this->asignatura();
        $grupoDos = $this->grupo($this->docente, $asignatura, '2');
        $grupoUno = $this->grupo(null, $asignatura, '1');
        $aula = $this->aula();
        $examen = $this->examen($this->docente, [$grupoDos, $grupoUno], [$aula]);
        [$estudiante] = $this->estudiantesInscritos($grupoDos, 1);

        Inscripcion::create([
            'estudiante_id' => $estudiante->id,
            'grupo_id' => $grupoUno->id,
            'via' => 'ADMINISTRACION',
        ]);
        $this->habilitar($examen, [$estudiante], $aula);

        $this->actingAs($this->cuentaDe($this->docente))
            ->getJson($this->ruta($aula, $examen))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.grupo', '1');
    }

    public function test_the_filters_are_validated(): void
    {
        $cuenta = $this->cuentaDe($this->docente);

        $this->actingAs($cuenta)
            ->getJson($this->ruta().'?lista=presentes&por_pagina=101&pagina=0')
            ->assertUnprocessable()
            ->assertJsonPath('errors.lista.0', 'La lista debe ser «faltan» o «ingresaron».')
            ->assertJsonPath('errors.por_pagina.0', 'El tamaño de página admite hasta 100.')
            ->assertJsonPath('errors.pagina.0', 'La página debe ser 1 o mayor.');

        $this->actingAs($cuenta)
            ->getJson($this->ruta().'?buscar='.str_repeat('a', 101))
            ->assertUnprocessable()
            ->assertJsonPath('errors.buscar.0', 'La búsqueda admite hasta 100 caracteres.');
    }

    public function test_an_assistant_follows_the_room_they_keep_or_support(): void
    {
        $encargado = $this->auxiliarQueSigue();
        $apoyo = $this->auxiliarQueSigue();
        $this->ponerACargo($this->examenDelDia, $this->aula, $encargado);
        $this->ponerDeApoyo($this->examenDelDia, $this->aula, $apoyo);

        foreach ([$encargado, $apoyo] as $cuenta) {
            $this->actingAs($cuenta)
                ->getJson($this->ruta())
                ->assertOk()
                ->assertJsonPath('cifras.asignados', 4);
        }
    }

    public function test_an_assistant_cannot_follow_another_room(): void
    {
        $auxiliar = $this->auxiliarQueSigue();
        $this->ponerACargo($this->examenDelDia, $this->aula, $auxiliar);

        $this->actingAs($auxiliar)
            ->getJson($this->ruta($this->otraAula))
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Esta aula no está a su cargo.',
                'alcance' => true,
            ]);
    }

    public function test_an_assistant_follows_their_room_only_on_the_day_of_the_exam(): void
    {
        $aula = $this->aula();
        $manana = $this->examen($this->docente, [$this->grupo($this->docente)], [$aula], [
            'fecha' => now()->addDay()->toDateString(),
        ]);
        $auxiliar = $this->auxiliarQueSigue();
        $this->ponerACargo($manana, $aula, $auxiliar);

        $this->actingAs($auxiliar)
            ->getJson($this->ruta($aula, $manana))
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'El aula solo se sigue el día del examen.',
                'alcance' => true,
            ]);

        // El docente del examen si la ve cualquier dia.
        $this->actingAs($this->cuentaDe($this->docente))
            ->getJson($this->ruta($aula, $manana))
            ->assertOk()
            ->assertJsonPath('abierto', false);

        $this->travel(1)->days();

        $this->actingAs($auxiliar)
            ->getJson($this->ruta($aula, $manana))
            ->assertOk();
    }

    public function test_a_teacher_cannot_follow_a_room_of_an_exam_of_another_teacher(): void
    {
        $this->actingAs($this->cuentaDe($this->docenteQueSigue()))
            ->getJson($this->ruta())
            ->assertForbidden()
            ->assertJsonPath('alcance', true);
    }

    public function test_an_unknown_exam_or_a_room_outside_the_exam_is_reported(): void
    {
        $cuenta = $this->cuentaDe($this->docente);

        $this->actingAs($cuenta)
            ->getJson("/api/examenes/999999/aulas/{$this->aula->id}/asistencia")
            ->assertNotFound()
            ->assertExactJson(['message' => 'Examen no encontrado.']);

        $this->actingAs($cuenta)
            ->getJson($this->ruta($this->aula('624')))
            ->assertNotFound()
            ->assertExactJson(['message' => 'Aula no encontrada.']);
    }
}
