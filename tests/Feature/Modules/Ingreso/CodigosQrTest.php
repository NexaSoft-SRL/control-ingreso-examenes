<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Estudiantes\Domain\Models\Estudiante;
use App\Modules\Examenes\Domain\Models\ExamenGrupo;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Rutas 57 a 59 y 61: lista de habilitados con su codigo, emision,
 * credencial y hoja imprimible.
 */
final class CodigosQrTest extends TestCase
{
    use EscenarioDePuerta;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->armarExamen();
    }

    private function ruta(string $resto = ''): string
    {
        return "/api/examenes/{$this->examen->id}/codigos-qr{$resto}";
    }

    public function test_the_list_shows_only_enabled_students_with_the_state_of_their_code(): void
    {
        [$primero, $segundo] = $this->habilitados(2);
        [$inhabilitado, $sinRevisar] = $this->estudiantesInscritos($this->grupo, 2);
        $this->inhabilitar($this->examen, [$inhabilitado]);
        $this->emitirCodigo($primero);

        $this->actingAs($this->cuentaDocente)
            ->getJson($this->ruta())
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.estudiante_id', $primero->id)
            ->assertJsonPath('data.0.codigo', $primero->codigo_universitario)
            ->assertJsonPath('data.0.nombre', $primero->apellidos.' '.$primero->nombres)
            ->assertJsonPath('data.0.grupo', '2')
            ->assertJsonPath('data.0.aula', '691A')
            ->assertJsonPath('data.0.emitido', true)
            ->assertJsonPath('data.0.enviado_en', null)
            ->assertJsonPath('data.1.estudiante_id', $segundo->id)
            ->assertJsonPath('data.1.emitido', false)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.pagina', 1)
            ->assertJsonPath('meta.por_pagina', 20)
            ->assertJsonPath('meta.habilitados', 2)
            ->assertJsonPath('meta.emitidos', 1)
            ->assertJsonPath('meta.enviados', 0)
            ->assertJsonPath('meta.estado', 'sin_emitir')
            ->assertJsonPath('meta.grupos.0.codigo', '2')
            ->assertJsonPath('meta.aulas.0.nombre', '691A');

        $this->assertNotSame($sinRevisar->id, $primero->id);
    }

    public function test_the_list_is_paginated_and_filtered_by_group_room_and_text(): void
    {
        $otra = $this->aula('617');
        $this->sumarAula($otra);
        $otroGrupo = $this->grupo($this->docente, null, '3');
        ExamenGrupo::create([
            'examen_id' => $this->examen->id,
            'grupo_id' => $otroGrupo->id,
        ]);
        $delDos = $this->habilitados(3);
        $delTres = $this->habilitados(2, $otra, null, $otroGrupo);
        $delDos[1]->update(['apellidos' => 'Aguilar Cossío', 'nombres' => 'Mariana']);

        $this->actingAs($this->cuentaDocente)
            ->getJson($this->ruta('?por_pagina=2&pagina=2'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.pagina', 2)
            ->assertJsonPath('meta.por_pagina', 2);

        $this->actingAs($this->cuentaDocente)
            ->getJson($this->ruta('?grupo='.$otroGrupo->id))
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.habilitados', 5)
            ->assertJsonPath('data.0.grupo', '3')
            ->assertJsonPath('data.0.estudiante_id', $delTres[0]->id);

        $this->actingAs($this->cuentaDocente)
            ->getJson($this->ruta('?aula='.$this->aula->id))
            ->assertOk()
            ->assertJsonPath('meta.total', 3);

        $this->actingAs($this->cuentaDocente)
            ->getJson($this->ruta('?buscar=COSSIO'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.nombre', 'Aguilar Cossío Mariana');

        $this->actingAs($this->cuentaDocente)
            ->getJson($this->ruta('?buscar='.$delDos[2]->codigo_universitario))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.estudiante_id', $delDos[2]->id);
    }

    public function test_the_page_size_is_validated(): void
    {
        $this->actingAs($this->cuentaDocente)
            ->getJson($this->ruta('?por_pagina=500'))
            ->assertStatus(422)
            ->assertJsonPath('errors.por_pagina.0', 'Entre 1 y 100 por página.');
    }

    public function test_issuing_gives_a_code_to_each_enabled_student(): void
    {
        $this->habilitados(3);
        [$inhabilitado] = $this->estudiantesInscritos($this->grupo, 1);
        $this->inhabilitar($this->examen, [$inhabilitado]);

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->ruta())
            ->assertOk()
            ->assertJsonPath('message', '3 códigos emitidos.')
            ->assertJsonPath('emitidos', 3)
            ->assertJsonPath('total', 3);

        $this->assertDatabaseCount('codigos_qr', 3);
        $this->assertDatabaseMissing('codigos_qr', ['estudiante_id' => $inhabilitado->id]);
        $this->assertDatabaseHas('codigos_qr', [
            'examen_id' => $this->examen->id,
            'emitido_por' => $this->cuentaDocente->id,
            'emitido_en' => '2026-10-12 08:20:00',
            'enviado_en' => null,
        ]);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $this->cuentaDocente->id,
            'operacion' => 'qr.emitir',
            'tabla_afectada' => 'codigos_qr',
            'registro_id' => $this->examen->id,
        ]);

        $this->actingAs($this->cuentaDocente)
            ->getJson($this->ruta())
            ->assertJsonPath('meta.estado', 'emitido');
    }

    public function test_issuing_again_keeps_the_codes_and_only_adds_the_missing_ones(): void
    {
        $this->habilitados(2);

        $this->actingAs($this->cuentaDocente)->postJson($this->ruta())->assertJsonPath('emitidos', 2);
        $antes = DB::table('codigos_qr')->orderBy('id')->pluck('token')->all();

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->ruta())
            ->assertOk()
            ->assertJsonPath('message', 'Los códigos ya estaban emitidos.')
            ->assertJsonPath('emitidos', 0)
            ->assertJsonPath('total', 2);

        $this->habilitados(1);

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->ruta())
            ->assertJsonPath('message', '1 código emitido.')
            ->assertJsonPath('emitidos', 1)
            ->assertJsonPath('total', 3);

        $this->assertSame($antes, DB::table('codigos_qr')->orderBy('id')->limit(2)->pluck('token')->all());
        $this->assertSame(2, DB::table('bitacora_operaciones')->where('operacion', 'qr.emitir')->count());
    }

    public function test_issuing_without_enabled_students_is_a_conflict(): void
    {
        $this->estudiantesInscritos($this->grupo, 2);

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->ruta())
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'SIN_HABILITADOS');

        $this->assertDatabaseCount('codigos_qr', 0);
    }

    public function test_issuing_with_enabled_students_without_room_is_a_conflict(): void
    {
        $estudiantes = $this->estudiantesInscritos($this->grupo, 2);
        $this->habilitar($this->examen, $estudiantes);

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->ruta())
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'SIN_AULA')
            ->assertJsonPath('message', '2 habilitados sin aula.');

        $this->assertDatabaseCount('codigos_qr', 0);
    }

    public function test_the_token_is_opaque_and_carries_no_personal_data(): void
    {
        [$estudiante] = $this->habilitados(1);

        $this->actingAs($this->cuentaDocente)->postJson($this->ruta())->assertOk();

        $token = DB::table('codigos_qr')->value('token');

        self::assertIsString($token);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{48}$/', $token);
        $this->assertStringNotContainsString($estudiante->codigo_universitario, $token);
        $this->assertStringNotContainsString((string) $estudiante->documento_identidad, $token);
        $this->assertStringNotContainsString((string) $this->examen->id.'-', $token);
    }

    public function test_the_credential_says_where_the_student_sits(): void
    {
        [$estudiante] = $this->habilitados(1);
        $this->emitirCodigo($estudiante);

        $respuesta = $this->actingAs($this->cuentaDocente)
            ->getJson($this->ruta('/'.$estudiante->id))
            ->assertOk()
            ->assertJsonPath('data.facultad', 'FCyT')
            ->assertJsonPath('data.examen', 'Primer parcial · Introducción a la Programación')
            ->assertJsonPath('data.fecha', '2026-10-12')
            ->assertJsonPath('data.hora', '08:15')
            ->assertJsonPath('data.duracion', 90)
            ->assertJsonPath('data.estudiante', $estudiante->apellidos.' '.$estudiante->nombres)
            ->assertJsonPath('data.codigo', $estudiante->codigo_universitario)
            ->assertJsonPath('data.aula.nombre', '691A')
            ->assertJsonPath('data.aula.edificio', 'Edificio Académico 2')
            ->assertJsonPath('data.aula.piso', '1° Piso')
            ->assertJsonPath('data.aula.edificio_id', $this->aula->edificio_id)
            ->assertJsonPath('data.emitido', true);

        $qr = $respuesta->json('data.qr');
        self::assertIsString($qr);
        $this->assertStringStartsWith('data:image/png;base64,', $qr);
    }

    public function test_a_credential_without_code_or_room_says_so(): void
    {
        [$estudiante] = $this->estudiantesInscritos($this->grupo, 1);
        $this->habilitar($this->examen, [$estudiante]);

        $this->actingAs($this->cuentaDocente)
            ->getJson($this->ruta('/'.$estudiante->id))
            ->assertOk()
            ->assertJsonPath('data.aula', null)
            ->assertJsonPath('data.emitido', false)
            ->assertJsonPath('data.qr', null);
    }

    public function test_there_is_no_credential_for_a_student_who_is_not_enabled(): void
    {
        [$inhabilitado, $sinRevisar] = $this->estudiantesInscritos($this->grupo, 2);
        $this->inhabilitar($this->examen, [$inhabilitado]);

        foreach ([$inhabilitado, $sinRevisar] as $estudiante) {
            $this->actingAs($this->cuentaDocente)
                ->getJson($this->ruta('/'.$estudiante->id))
                ->assertNotFound();
        }
    }

    public function test_the_printable_sheet_has_one_credential_per_issued_code(): void
    {
        [$conCodigo, $sinCodigo] = $this->habilitados(2);
        $conCodigo->update(['apellidos' => 'Aguilar Cossío', 'nombres' => 'Mariana']);
        $sinCodigo->update(['apellidos' => 'Zurita Rojas', 'nombres' => 'Pablo']);
        $this->emitirCodigo($conCodigo);

        $respuesta = $this->actingAs($this->cuentaDocente)
            ->get($this->ruta('/hoja'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertSee('Aguilar Cossío Mariana')
            ->assertSee($conCodigo->codigo_universitario)
            ->assertSee('Primer parcial · Introducción a la Programación')
            ->assertSee('2026-10-12 · 08:15')
            ->assertSee('Aula 691A')
            ->assertSee('Edificio Académico 2 · 1° Piso')
            ->assertSee('@media print', false)
            ->assertDontSee('Zurita Rojas');

        $this->assertSame(1, substr_count((string) $respuesta->getContent(), 'data:image/png;base64,'));
    }

    public function test_the_printable_sheet_accepts_the_filters_of_the_list(): void
    {
        $otra = $this->aula('617');
        $this->sumarAula($otra);
        [$aqui] = $this->habilitados(1);
        [$alla] = $this->habilitados(1, $otra);
        $aqui->update(['apellidos' => 'Aguilar Cossío']);
        $alla->update(['apellidos' => 'Zurita Rojas']);
        $this->emitirCodigo($aqui);
        $this->emitirCodigo($alla);

        $this->actingAs($this->cuentaDocente)
            ->get($this->ruta('/hoja?aula='.$otra->id))
            ->assertOk()
            ->assertSee('Zurita Rojas')
            ->assertSee('Aula 617')
            ->assertDontSee('Aguilar Cossío');

        $this->actingAs($this->cuentaDocente)
            ->get($this->ruta('/hoja?buscar=aguilar'))
            ->assertOk()
            ->assertSee('Aguilar Cossío')
            ->assertDontSee('Zurita Rojas');
    }

    public function test_every_qr_route_requires_a_session(): void
    {
        $this->getJson($this->ruta())->assertUnauthorized();
        $this->postJson($this->ruta())->assertUnauthorized();
        $this->getJson($this->ruta('/1'))->assertUnauthorized();
        $this->getJson($this->ruta('/hoja'))->assertUnauthorized();
        $this->postJson($this->ruta('/envios'))->assertUnauthorized();
    }

    public function test_every_qr_route_requires_the_permission(): void
    {
        $sinPermiso = UserFactory::new()->createOne();

        foreach ([['getJson', ''], ['postJson', ''], ['getJson', '/1'], ['getJson', '/hoja'], ['postJson', '/envios']] as [$verbo, $resto]) {
            $this->actingAs($sinPermiso)
                ->{$verbo}($this->ruta($resto))
                ->assertForbidden()
                ->assertJsonPath('permiso_requerido', 'codigos_qr');
        }
    }

    public function test_a_teacher_cannot_touch_the_codes_of_someone_elses_exam(): void
    {
        [$estudiante] = $this->habilitados(1);
        $ajeno = $this->docenteAjeno();

        foreach ([['getJson', ''], ['postJson', ''], ['getJson', '/'.$estudiante->id], ['getJson', '/hoja'], ['postJson', '/envios']] as [$verbo, $resto]) {
            $this->actingAs($ajeno)
                ->{$verbo}($this->ruta($resto))
                ->assertForbidden()
                ->assertJsonPath('alcance', true)
                ->assertJsonPath('message', 'Este examen no está a su cargo.');
        }

        $this->assertDatabaseCount('codigos_qr', 0);
    }

    public function test_the_teacher_of_an_included_group_issues_codes_too(): void
    {
        $colega = $this->usuarioConPermisos(['codigos_qr']);
        $grupoDelColega = $this->grupo($this->docenteConCuenta($colega), null, '3');
        ExamenGrupo::create([
            'examen_id' => $this->examen->id,
            'grupo_id' => $grupoDelColega->id,
        ]);
        $this->habilitados(2);

        $this->actingAs($colega)
            ->postJson($this->ruta())
            ->assertOk()
            ->assertJsonPath('emitidos', 2);
    }

    public function test_an_unknown_exam_is_not_found(): void
    {
        foreach ([['getJson', ''], ['postJson', ''], ['getJson', '/1'], ['getJson', '/hoja'], ['postJson', '/envios']] as [$verbo, $resto]) {
            $this->actingAs($this->cuentaDocente)
                ->{$verbo}('/api/examenes/999999/codigos-qr'.$resto)
                ->assertNotFound()
                ->assertJsonPath('message', 'Examen no encontrado.');
        }
    }

    public function test_a_student_in_two_groups_of_the_exam_is_listed_once_with_the_lowest_group(): void
    {
        $grupoUno = $this->grupo($this->docente, null, '1');
        ExamenGrupo::create([
            'examen_id' => $this->examen->id,
            'grupo_id' => $grupoUno->id,
        ]);
        [$estudiante] = $this->habilitados(1);
        $this->inscribir($estudiante, $grupoUno->id);

        $this->actingAs($this->cuentaDocente)
            ->getJson($this->ruta())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.grupo', '1');
    }

    private function inscribir(Estudiante $estudiante, int $grupoId): void
    {
        DB::table('inscripciones')->insert([
            'estudiante_id' => $estudiante->id,
            'grupo_id' => $grupoId,
            'via' => 'ADMINISTRACION',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
