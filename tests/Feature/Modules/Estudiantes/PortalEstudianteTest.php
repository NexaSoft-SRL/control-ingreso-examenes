<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Estudiantes;

use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Academico\Domain\Models\Docente;
use App\Modules\Academico\Domain\Models\Grupo;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use App\Modules\Estudiantes\Infrastructure\Mail\CodigoAccesoMail;
use App\Modules\Examenes\Domain\Models\Examen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * El estudiante sin cuenta: codigo al correo institucional, sus examenes
 * y su foto; el plazo general de fotos, la foto en la puerta y el cambio
 * de foto que habilita el docente.
 */
final class PortalEstudianteTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;
    use UsuarioConPermisos;

    private User $cuentaDocente;

    private Docente $docente;

    private Grupo $grupo;

    private Aula $aula;

    private Examen $examen;

    private Estudiante $estudiante;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');

        $this->cuentaDocente = $this->usuarioConPermisos(['mis_grupos', 'punto_control']);
        $this->docente = $this->docenteConCuenta($this->cuentaDocente);
        $this->grupo = $this->grupo($this->docente, $this->asignatura('Introducción a la Programación'));
        $this->aula = $this->aula('691A', $this->edificio(null, 'Edificio Académico 2'), '1.er piso');
        [$this->estudiante] = $this->estudiantesInscritos($this->grupo, 1);
        $this->examen = $this->examen($this->docente, [$this->grupo], [$this->aula]);
        $this->habilitar($this->examen, [$this->estudiante], $this->aula);
    }

    /**
     * Pide el codigo y devuelve el que llego al correo.
     */
    private function codigoEnviado(): string
    {
        $this->postJson('/api/auth/estudiante/codigo', [
            'codigo_universitario' => $this->estudiante->codigo_universitario,
        ])->assertOk();

        $codigo = null;
        Mail::assertSent(CodigoAccesoMail::class, function (CodigoAccesoMail $correo) use (&$codigo): bool {
            $codigo = $correo->codigo;

            return true;
        });
        self::assertIsString($codigo);

        return $codigo;
    }

    private function entrar(): void
    {
        $this->postJson('/api/auth/estudiante', [
            'codigo_universitario' => $this->estudiante->codigo_universitario,
            'codigo' => $this->codigoEnviado(),
        ])->assertOk();
    }

    private function abrirPlazo(): void
    {
        DB::table('plazos_foto')->insert([
            'desde' => now()->subDays(5)->toDateString(),
            'hasta' => now()->addDays(5)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return TestResponse<JsonResponse>
     */
    private function verificarEnPuerta(): TestResponse
    {
        return $this->actingAs($this->cuentaDocente)->postJson(
            "/api/aulas/{$this->aula->id}/punto-control/ingresos",
            ['tipo' => 'codigo', 'identificador' => $this->estudiante->codigo_universitario],
        );
    }

    public function test_the_code_goes_to_the_institutional_mail_and_the_answer_hides_the_address(): void
    {
        $codigo = $this->estudiante->codigo_universitario;

        $this->postJson('/api/auth/estudiante/codigo', ['codigo_universitario' => $codigo])
            ->assertOk()
            ->assertJsonPath('correo', substr($codigo, 0, 4).str_repeat('•', strlen($codigo) - 6).substr($codigo, -2).'@est.umss.edu')
            ->assertJsonPath('minutos', 1440)
            ->assertJsonMissingPath('codigo_prueba');

        Mail::assertSent(CodigoAccesoMail::class, fn (CodigoAccesoMail $correo): bool => $correo->hasTo($this->estudiante->correo())
            && preg_match('/^\d{6}$/', $correo->codigo) === 1);
        // En la base queda el hash, nunca el codigo.
        $hash = DB::table('codigos_acceso_estudiante')->value('codigo_hash');
        self::assertIsString($hash);
        $this->assertSame(64, strlen($hash));
    }

    public function test_an_unknown_code_answers_the_same_and_sends_nothing(): void
    {
        $this->postJson('/api/auth/estudiante/codigo', ['codigo_universitario' => '199900001'])
            ->assertOk()
            ->assertJsonPath('message', 'Código enviado.');

        Mail::assertNothingSent();
    }

    public function test_the_student_enters_with_the_code_once(): void
    {
        $codigo = $this->codigoEnviado();
        $cuerpo = ['codigo_universitario' => $this->estudiante->codigo_universitario, 'codigo' => $codigo];

        $this->postJson('/api/auth/estudiante', $cuerpo)
            ->assertOk()
            ->assertJsonPath('estudiante.codigo', $this->estudiante->codigo_universitario)
            ->assertJsonPath('estudiante.nombre', trim($this->estudiante->apellidos.' '.$this->estudiante->nombres))
            ->assertJsonPath('estudiante.con_foto', false);

        $this->getJson('/api/estudiante/sesion')
            ->assertOk()
            ->assertJsonPath('estudiante.id', $this->estudiante->id)
            ->assertJsonPath('plazo_foto', null);

        $this->deleteJson('/api/auth/estudiante')->assertNoContent();
        $this->getJson('/api/estudiante/sesion')->assertUnauthorized();

        $this->postJson('/api/auth/estudiante', $cuerpo)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['codigo']);
    }

    public function test_a_wrong_code_says_how_many_attempts_remain_and_five_failures_void_it(): void
    {
        $codigo = $this->codigoEnviado();
        $malo = $codigo === '000000' ? '111111' : '000000';
        $cuerpo = ['codigo_universitario' => $this->estudiante->codigo_universitario, 'codigo' => $malo];

        $this->postJson('/api/auth/estudiante', $cuerpo)
            ->assertStatus(422)
            ->assertJsonPath('errors.codigo.0', 'Código incorrecto · 4 intentos restantes');

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/auth/estudiante', $cuerpo);
        }

        $this->postJson('/api/auth/estudiante', $cuerpo)
            ->assertJsonPath('errors.codigo.0', 'Código bloqueado · pida otro');

        $this->postJson('/api/auth/estudiante', [...$cuerpo, 'codigo' => $codigo])->assertStatus(422);
    }

    public function test_the_code_expires_after_twenty_four_hours(): void
    {
        $codigo = $this->codigoEnviado();
        $this->travel(24 * 60 + 1)->minutes();

        $this->postJson('/api/auth/estudiante', [
            'codigo_universitario' => $this->estudiante->codigo_universitario,
            'codigo' => $codigo,
        ])->assertStatus(422)
            ->assertJsonPath('errors.codigo.0', 'Código vencido · pida otro');
    }

    public function test_the_code_of_the_first_mail_after_resending_says_to_use_the_last_one(): void
    {
        $primero = $this->codigoEnviado();
        $this->travel(1)->minutes();
        $this->postJson('/api/auth/estudiante/codigo', ['codigo_universitario' => $this->estudiante->codigo_universitario])->assertOk();

        $this->postJson('/api/auth/estudiante', [
            'codigo_universitario' => $this->estudiante->codigo_universitario,
            'codigo' => $primero,
        ])->assertStatus(422)
            ->assertJsonPath('errors.codigo.0', 'Código reemplazado · use el del último correo');

        $this->assertDatabaseHas('codigos_acceso_estudiante', ['reemplazado_en' => null, 'intentos' => 0]);
    }

    public function test_an_unknown_student_code_answers_like_a_known_one_with_a_wrong_code(): void
    {
        $this->postJson('/api/auth/estudiante/codigo', ['codigo_universitario' => '199900001'])->assertOk();

        $this->postJson('/api/auth/estudiante', ['codigo_universitario' => '199900001', 'codigo' => '123456'])
            ->assertStatus(422)
            ->assertJsonPath('errors.codigo.0', 'Código incorrecto · 4 intentos restantes');
    }

    public function test_three_requests_per_student_code_but_classmates_on_the_same_network_go_on(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/auth/estudiante/codigo', ['codigo_universitario' => $this->estudiante->codigo_universitario])->assertOk();
        }

        $this->postJson('/api/auth/estudiante/codigo', ['codigo_universitario' => $this->estudiante->codigo_universitario])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Demasiados pedidos · espere 10 minutos');

        $this->postJson('/api/auth/estudiante/codigo', ['codigo_universitario' => '202199991'])->assertOk();
    }

    public function test_the_student_sees_the_time_and_room_of_each_exam_and_when_entered(): void
    {
        DB::table('examenes')->where('id', $this->examen->id)->update([
            'normas' => 'Presentar carnet universitario.',
        ]);
        DB::table('examen_norma')->insert([
            'examen_id' => $this->examen->id,
            'plantilla_id' => null,
            'texto' => 'No usar celular.',
            'orden' => 1,
        ]);
        $otro = $this->examen($this->docente, [$this->grupo], [$this->aula], [
            'fecha' => now()->addDays(8)->toDateString(),
            'hora_inicio' => '14:15:00',
        ]);
        $this->inhabilitar($otro, [$this->estudiante], 'Debe la práctica 3.');
        $this->entrar();

        $examenes = $this->getJson('/api/estudiante/examenes')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->json('data');
        self::assertIsArray($examenes);
        /** @var array<int, array<string, mixed>> $porId */
        $porId = array_column($examenes, null, 'id');

        $this->assertSame('HABILITADO', $porId[$this->examen->id]['estado']);
        $this->assertSame('691A', $porId[$this->examen->id]['aula']);
        $this->assertSame('Edificio Académico 2', $porId[$this->examen->id]['edificio']);
        $this->assertSame('Introducción a la Programación', $porId[$this->examen->id]['asignatura']);
        $this->assertSame('Primer parcial', $porId[$this->examen->id]['tipo']);
        $this->assertSame(
            ['Presentar carnet universitario.', 'No usar celular.'],
            $porId[$this->examen->id]['normas'],
        );
        $this->assertNull($porId[$this->examen->id]['ingreso']);
        $this->assertSame('NO_HABILITADO', $porId[$otro->id]['estado']);
        $this->assertSame('Debe la práctica 3.', $porId[$otro->id]['motivo']);
        $this->assertNull($porId[$otro->id]['aula']);

        $this->verificarEnPuerta()->assertCreated();
        $this->app['auth']->forgetGuards();
        $registrado = DB::table('ingresos')->value('registrado_en');
        self::assertIsString($registrado);
        $hora = substr($registrado, 11, 5);

        $this->getJson('/api/estudiante/examenes')->assertJsonPath('data.0.ingreso', $hora);
    }

    public function test_a_group_photo_deadline_is_available_without_an_open_general_deadline(): void
    {
        DB::table('plazos_foto_grupo')->insert([
            'grupo_id' => $this->grupo->id,
            'desde' => now()->subDay()->toDateString(),
            'hasta' => now()->addDay()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->entrar();
        $this->getJson('/api/estudiante/sesion')
            ->assertJsonPath('foto.puede_subir', true)
            ->assertJsonPath('plazos_foto_grupo.0.desde', now()->subDay()->toDateString())
            ->assertJsonPath('plazos_foto_grupo.0.hasta', now()->addDay()->toDateString());

    }

    public function test_an_expired_group_photo_deadline_does_not_allow_upload(): void
    {
        DB::table('plazos_foto_grupo')->insert([
            'grupo_id' => $this->grupo->id,
            'desde' => now()->subDays(5)->toDateString(),
            'hasta' => now()->subDay()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->entrar();
        $this->getJson('/api/estudiante/sesion')->assertJsonPath('foto.puede_subir', false);
    }

    public function test_without_a_session_nothing_of_the_student_opens(): void
    {
        $this->getJson('/api/estudiante/examenes')->assertUnauthorized();
        $this->postJson('/api/estudiante/foto')->assertUnauthorized();
        $this->getJson('/api/estudiante/foto')->assertUnauthorized();
    }

    public function test_the_photo_is_uploaded_once_within_the_period_and_reduced_on_the_private_disk(): void
    {
        $this->entrar();
        $foto = fn () => ['foto' => UploadedFile::fake()->image('yo.jpg', 1200, 1600)];

        $this->post('/api/estudiante/foto', $foto(), ['Accept' => 'application/json'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Fuera del plazo de fotos.');

        $this->abrirPlazo();
        $this->getJson('/api/estudiante/sesion')->assertJsonPath('foto.puede_subir', true);

        $this->post('/api/estudiante/foto', $foto(), ['Accept' => 'application/json'])
            ->assertCreated();

        $ruta = DB::table('estudiantes')->where('id', $this->estudiante->id)->value('foto');
        self::assertIsString($ruta);
        $this->assertStringStartsWith('imagenes/estudiantes/', $ruta);
        $tamano = getimagesizefromstring(Storage::disk('local')->get($ruta) ?? '');
        self::assertIsArray($tamano);
        $this->assertSame([360, 480], [$tamano[0], $tamano[1]]);

        $this->get('/api/estudiante/foto')->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        $this->post('/api/estudiante/foto', $foto(), ['Accept' => 'application/json'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'La foto ya fue registrada.');
        $this->assertDatabaseHas('bitacora_operaciones', ['operacion' => 'estudiante.foto', 'registro_id' => $this->estudiante->id]);
    }

    public function test_only_images_of_enough_size_are_accepted(): void
    {
        $this->abrirPlazo();
        $this->entrar();

        $this->post('/api/estudiante/foto', ['foto' => UploadedFile::fake()->create('yo.pdf', 5, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['foto']);

        $this->post('/api/estudiante/foto', ['foto' => UploadedFile::fake()->image('yo.png', 100, 100)], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('errors.foto.0', 'Resolución insuficiente · mínimo 240 px');
    }

    public function test_a_teacher_enables_the_student_to_change_the_photo_once_a_year(): void
    {
        $habilitar = fn (array $datos = []) => $this->actingAs($this->cuentaDocente)
            ->postJson("/api/docente/grupos/{$this->grupo->id}/inscritos/{$this->estudiante->id}/foto", $datos);
        $foto = fn () => ['foto' => UploadedFile::fake()->image('yo.jpg', 600, 800)];
        $this->travelTo('2026-10-12 08:15:00');

        // Sin foto y con el plazo cerrado, el docente le habilita subirla.
        $habilitar()->assertOk()->assertJsonPath('hasta', '2026-10-19');
        $this->getJson("/api/docente/grupos/{$this->grupo->id}/inscritos")
            ->assertJsonPath('data.0.foto', ['subida_en' => null, 'habilitada_hasta' => '2026-10-19']);
        $this->app['auth']->forgetGuards();
        $this->entrar();
        $this->getJson('/api/estudiante/sesion')
            ->assertJsonPath('foto.puede_subir', true)
            ->assertJsonPath('foto.habilitada_hasta', '2026-10-19')
            ->assertJsonPath('foto.habilitada_por', $this->cuentaDocente->nombre);
        $this->post('/api/estudiante/foto', $foto(), ['Accept' => 'application/json'])->assertCreated();
        $this->getJson('/api/estudiante/sesion')->assertJsonPath('foto.puede_subir', false);

        // Con la foto recien subida, otro cambio espera un año.
        $habilitar()
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'FOTO_RECIENTE')
            ->assertJsonPath('message', 'Foto del 12 oct 2026: el cambio se puede habilitar desde el 12 oct 2027.');

        // El docente elige el dia, y despues lo cambia.
        $this->travelTo('2027-10-12 08:00:00');
        $this->flushSession();
        $habilitar(['hasta' => '2027-10-25'])->assertOk()->assertJsonPath('hasta', '2027-10-25');
        $habilitar(['hasta' => '2027-10-30'])->assertOk()->assertJsonPath('hasta', '2027-10-30');
        $this->assertDatabaseHas('estudiantes', ['id' => $this->estudiante->id, 'foto_habilitada_hasta' => '2027-10-30']);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'estudiante.foto_habilitar',
            'descripcion' => 'Foto de '.$this->estudiante->apellidos.' '.$this->estudiante->nombres.' · '
                .$this->estudiante->codigo_universitario.': plazo del 25 oct 2027 → 30 oct 2027.',
        ]);

        // Ni antes de hoy ni a mas de un mes.
        $habilitar(['hasta' => '2027-10-11'])->assertUnprocessable()->assertJsonValidationErrors('hasta');
        $habilitar(['hasta' => '2027-11-12'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.hasta.0', 'Máximo 30 días');
    }

    public function test_only_a_teacher_of_the_group_enables_the_photo(): void
    {
        $ajeno = $this->usuarioConPermisos(['mis_grupos']);
        $otro = $this->estudiantesInscritos($this->grupo($this->docenteConCuenta()), 1)[0];

        $this->actingAs($ajeno)
            ->postJson("/api/docente/grupos/{$this->grupo->id}/inscritos/{$this->estudiante->id}/foto")
            ->assertForbidden();
        $this->actingAs($this->cuentaDocente)
            ->postJson("/api/docente/grupos/{$this->grupo->id}/inscritos/{$otro->id}/foto")
            ->assertNotFound();
        $this->assertDatabaseHas('estudiantes', ['id' => $this->estudiante->id, 'foto_habilitada_hasta' => null]);
    }

    public function test_the_photo_period_is_at_most_180_days_and_within_the_current_period(): void
    {
        $admin = $this->usuarioConPermisos(['padron_estudiantes']);
        $dia = static fn (int $dias): string => now()->addDays($dias)->toDateString();
        DB::table('periodos')->update(['fecha_inicio' => $dia(-30), 'fecha_fin' => $dia(60)]);

        $this->actingAs($admin)
            ->putJson('/api/estudiantes/plazo-fotos', ['desde' => $dia(0), 'hasta' => $dia(181)])
            ->assertJsonPath('errors.hasta.0', 'Máximo 180 días');

        $this->putJson('/api/estudiantes/plazo-fotos', ['desde' => $dia(0), 'hasta' => $dia(90)])
            ->assertStatus(422)
            ->assertJsonPath('errors.hasta.0', fn (string $texto): bool => str_starts_with($texto, 'Fuera del período'));

        $this->putJson('/api/estudiantes/plazo-fotos', ['desde' => $dia(5), 'hasta' => $dia(2)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['hasta']);

        $this->putJson('/api/estudiantes/plazo-fotos', ['desde' => $dia(3), 'hasta' => $dia(20)])
            ->assertOk()
            ->assertJsonPath('data.abierto', false)
            ->assertJsonPath('data.estado', 'proximo');

        $this->getJson('/api/estudiantes/plazo-fotos')
            ->assertOk()
            ->assertJsonPath('data.desde', $dia(3))
            ->assertJsonPath('data.hasta', $dia(20));
        $this->assertDatabaseHas('plazos_foto', ['fijado_por' => $admin->id]);
        $this->assertDatabaseHas('bitacora_operaciones', ['operacion' => 'estudiante.plazo_fotos']);
    }

    public function test_administration_can_set_and_remove_a_group_photo_deadline(): void
    {
        $admin = $this->usuarioConPermisos(['padron_estudiantes']);
        $desde = now()->toDateString();
        $hasta = now()->addDays(15)->toDateString();

        $this->actingAs($admin)
            ->getJson('/api/estudiantes/plazo-fotos/grupos')
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->grupo->id)
            ->assertJsonPath('data.0.asignatura', 'Introducción a la Programación');

        $this->putJson("/api/estudiantes/plazo-fotos/grupos/{$this->grupo->id}", [
            'desde' => $desde,
            'hasta' => $hasta,
        ])->assertOk()->assertJsonPath('data.desde', $desde);

        $this->assertDatabaseHas('plazos_foto_grupo', [
            'grupo_id' => $this->grupo->id,
            'desde' => $desde,
            'hasta' => $hasta,
        ]);

        $this->deleteJson("/api/estudiantes/plazo-fotos/grupos/{$this->grupo->id}")
            ->assertNoContent();
        $this->assertDatabaseMissing('plazos_foto_grupo', ['grupo_id' => $this->grupo->id]);
    }

    public function test_the_administration_replaces_a_photo_and_photos_are_protected_by_permission(): void
    {
        $admin = $this->usuarioConPermisos(['padron_estudiantes']);

        $this->actingAs($admin)
            ->post("/api/estudiantes/{$this->estudiante->id}/foto", ['foto' => UploadedFile::fake()->image('a.png', 300, 400)], ['Accept' => 'application/json'])
            ->assertOk();
        $primera = DB::table('estudiantes')->where('id', $this->estudiante->id)->value('foto');
        self::assertIsString($primera);

        $this->post("/api/estudiantes/{$this->estudiante->id}/foto", ['foto' => UploadedFile::fake()->image('b.png', 300, 400)], ['Accept' => 'application/json'])
            ->assertOk();
        Storage::disk('local')->assertMissing($primera);

        $this->get("/api/estudiantes/{$this->estudiante->id}/foto")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
        $this->getJson("/api/estudiantes/{$this->estudiante->id}")
            ->assertJsonPath('data.con_foto', true)
            ->assertJsonPath('data.foto_subida_en', fn (?string $fecha): bool => $fecha !== null);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $admin->id,
            'operacion' => 'estudiante.foto.reemplazar',
            'registro_id' => $this->estudiante->id,
        ]);

        $this->actingAs($this->usuarioConRol('Docente'))
            ->getJson("/api/estudiantes/{$this->estudiante->id}/foto")
            ->assertForbidden();
    }

    public function test_the_door_shows_the_photo_with_the_verdict(): void
    {
        $this->verificarEnPuerta()
            ->assertCreated()
            ->assertJsonPath('data.estudiante.foto', null);

        $this->actingAs($this->usuarioConPermisos(['padron_estudiantes']))
            ->post("/api/estudiantes/{$this->estudiante->id}/foto", ['foto' => UploadedFile::fake()->image('a.png', 300, 400)], ['Accept' => 'application/json'])
            ->assertOk();

        $foto = $this->verificarEnPuerta()
            ->assertJsonPath('data.veredicto', 'YA_INGRESO')
            ->json('data.estudiante.foto');
        self::assertIsString($foto);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $foto);
    }
}
