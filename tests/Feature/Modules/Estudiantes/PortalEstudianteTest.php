<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Estudiantes;

use App\Modules\Estudiantes\Domain\Models\Estudiante;
use App\Modules\Estudiantes\Infrastructure\Mail\CodigoAccesoMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\Ingreso\EscenarioDePuerta;
use Tests\TestCase;

/**
 * El estudiante sin cuenta: codigo al correo institucional, sus examenes
 * y su foto.
 */
final class PortalEstudianteTest extends TestCase
{
    use EscenarioDePuerta;
    use RefreshDatabase;

    private Estudiante $estudiante;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        $this->armarExamen();

        [$this->estudiante] = $this->habilitados(1);
    }

    /**
     * Pide el codigo y devuelve el que llego al correo.
     */
    private function codigoEnviado(?string $codigoUniversitario = null): string
    {
        $this->postJson('/api/auth/estudiante/codigo', [
            'codigo_universitario' => $codigoUniversitario ?? $this->estudiante->codigo_universitario,
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
            'desde' => '2026-10-01',
            'hasta' => '2026-10-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_the_code_goes_to_the_institutional_mail_and_the_answer_hides_the_address(): void
    {
        $codigo = $this->estudiante->codigo_universitario;

        $this->postJson('/api/auth/estudiante/codigo', ['codigo_universitario' => $codigo])
            ->assertOk()
            ->assertJsonPath('correo', substr($codigo, 0, 4).str_repeat('•', strlen($codigo) - 6).substr($codigo, -2).'@est.umss.edu')
            ->assertJsonPath('minutos', 10)
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

    public function test_a_wrong_code_counts_and_five_failures_void_it(): void
    {
        $codigo = $this->codigoEnviado();
        $malo = $codigo === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/estudiante', [
                'codigo_universitario' => $this->estudiante->codigo_universitario,
                'codigo' => $malo,
            ])->assertStatus(422);
        }

        $this->postJson('/api/auth/estudiante', [
            'codigo_universitario' => $this->estudiante->codigo_universitario,
            'codigo' => $codigo,
        ])->assertStatus(422);
    }

    public function test_the_code_expires_after_ten_minutes(): void
    {
        $codigo = $this->codigoEnviado();
        $this->travel(11)->minutes();

        $this->postJson('/api/auth/estudiante', [
            'codigo_universitario' => $this->estudiante->codigo_universitario,
            'codigo' => $codigo,
        ])->assertStatus(422);
    }

    public function test_the_student_sees_the_time_and_room_of_each_exam(): void
    {
        $otro = $this->examen($this->docente, [$this->grupo], [$this->aula]);
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
        $this->assertSame('NO_HABILITADO', $porId[$otro->id]['estado']);
        $this->assertSame('Debe la práctica 3.', $porId[$otro->id]['motivo']);
        $this->assertNull($porId[$otro->id]['aula']);
    }

    public function test_the_student_downloads_only_his_own_issued_qr(): void
    {
        [$companero] = $this->habilitados(1);
        $sinCodigo = $this->examen($this->docente, [$this->grupo], [$this->aula]);
        $this->habilitar($sinCodigo, [$this->estudiante], $this->aula);
        $inhabilitado = $this->examen($this->docente, [$this->grupo], [$this->aula]);
        $this->inhabilitar($inhabilitado, [$this->estudiante]);
        $this->emitirCodigo($this->estudiante);
        $this->emitirCodigo($companero);
        $this->emitirCodigo($this->estudiante, $inhabilitado);
        $this->entrar();

        /** @var array<int, array<string, mixed>> $porId */
        $porId = array_column((array) $this->getJson('/api/estudiante/examenes')->json('data'), null, 'id');
        $this->assertTrue($porId[$this->examen->id]['qr']);
        $this->assertFalse($porId[$sinCodigo->id]['qr']);
        $this->assertFalse($porId[$inhabilitado->id]['qr']);

        $respuesta = $this->get("/api/estudiante/examenes/{$this->examen->id}/qr")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader(
                'Content-Disposition',
                'attachment; filename="qr-'.$this->estudiante->codigo_universitario.'-2026-10-12.png"',
            );
        $this->assertStringStartsWith("\x89PNG", (string) $respuesta->getContent());

        // Sin codigo emitido, inhabilitado o examen ajeno: nada.
        $this->getJson("/api/estudiante/examenes/{$sinCodigo->id}/qr")->assertNotFound();
        $this->getJson("/api/estudiante/examenes/{$inhabilitado->id}/qr")->assertNotFound();
        $this->getJson('/api/estudiante/examenes/999999/qr')->assertNotFound();
        $this->deleteJson('/api/auth/estudiante')->assertNoContent();
        $this->getJson("/api/estudiante/examenes/{$this->examen->id}/qr")->assertUnauthorized();
    }

    public function test_without_a_session_nothing_of_the_student_opens(): void
    {
        $this->getJson('/api/estudiante/examenes')->assertUnauthorized();
        $this->postJson('/api/estudiante/foto')->assertUnauthorized();
    }

    public function test_the_photo_is_uploaded_once_within_the_period_and_reduced(): void
    {
        $this->entrar();
        $foto = fn () => ['foto' => UploadedFile::fake()->image('yo.jpg', 1200, 1600)];

        $this->post('/api/estudiante/foto', $foto(), ['Accept' => 'application/json'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Fuera del plazo de fotos.');

        $this->abrirPlazo();

        $this->post('/api/estudiante/foto', $foto(), ['Accept' => 'application/json'])
            ->assertCreated();

        $ruta = DB::table('estudiantes')->where('id', $this->estudiante->id)->value('foto');
        self::assertIsString($ruta);
        $tamano = getimagesizefromstring(Storage::disk('local')->get($ruta) ?? '');
        self::assertIsArray($tamano);
        $this->assertSame([360, 480], [$tamano[0], $tamano[1]]);

        $this->get('/api/estudiante/foto')->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        $this->post('/api/estudiante/foto', $foto(), ['Accept' => 'application/json'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'La foto ya fue registrada.');
    }

    public function test_a_teacher_enables_the_student_to_change_the_photo_once_a_year(): void
    {
        $habilitar = fn (array $datos = []) => $this->actingAs($this->cuentaDocente)
            ->postJson("/api/docente/grupos/{$this->grupo->id}/inscritos/{$this->estudiante->id}/foto", $datos);
        $foto = fn () => ['foto' => UploadedFile::fake()->image('yo.jpg', 600, 800)];

        // Sin foto y con el plazo cerrado, el docente le habilita subirla.
        $habilitar()->assertOk()->assertJsonPath('hasta', '2026-10-19');
        $this->app['auth']->forgetGuards();
        $this->entrar();
        $this->getJson('/api/estudiante/sesion')
            ->assertJsonPath('foto.puede_subir', true)
            ->assertJsonPath('foto.habilitada_hasta', '2026-10-19')
            ->assertJsonPath('foto.habilitada_por', $this->cuentaDocente->name);
        $this->post('/api/estudiante/foto', $foto(), ['Accept' => 'application/json'])->assertCreated();
        $this->getJson('/api/estudiante/sesion')->assertJsonPath('foto.puede_subir', false);

        // Con la foto recien subida, otro cambio espera un año.
        $habilitar()
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'FOTO_RECIENTE')
            ->assertJsonPath('message', 'Foto del 12 oct 2026: el cambio se puede habilitar desde el 12 oct 2027.');

        // El docente elige el dia, y despues lo cambia.
        $this->travelTo('2027-10-12 08:00:00');
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

    public function test_after_the_general_deadline_without_photo_the_student_is_told_he_is_not_enabled(): void
    {
        DB::table('plazos_foto')->insert([
            'desde' => '2026-10-01',
            'hasta' => '2026-10-11',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->entrar();

        $this->getJson('/api/estudiante/sesion')
            ->assertJsonPath('plazo_foto.abierto', false)
            ->assertJsonPath('foto.puede_subir', false)
            ->assertJsonPath('foto.sin_foto_vencido', true)
            ->assertJsonMissingPath('foto.plazo_grupo')
            ->assertJsonMissingPath('foto.vencido_en');

        // Un docente le habilita subirla: al subirla deja de estar vencido.
        $this->actingAs($this->cuentaDocente)
            ->postJson("/api/docente/grupos/{$this->grupo->id}/inscritos/{$this->estudiante->id}/foto")
            ->assertOk();
        $this->app['auth']->forgetGuards();
        $this->entrar();
        $this->post('/api/estudiante/foto', ['foto' => UploadedFile::fake()->image('yo.jpg', 600, 800)], ['Accept' => 'application/json'])
            ->assertCreated();
        $this->getJson('/api/estudiante/sesion')
            ->assertJsonPath('foto.puede_subir', false)
            ->assertJsonPath('foto.sin_foto_vencido', false);
    }

    public function test_only_a_teacher_of_the_group_enables_the_photo(): void
    {
        $ajeno = $this->usuarioConPermisos(['mis_grupos']);

        $this->actingAs($ajeno)
            ->postJson("/api/docente/grupos/{$this->grupo->id}/inscritos/{$this->estudiante->id}/foto")
            ->assertForbidden();
        $this->assertDatabaseHas('estudiantes', ['id' => $this->estudiante->id, 'foto_habilitada_hasta' => null]);
    }

    public function test_only_images_are_accepted(): void
    {
        $this->abrirPlazo();
        $this->entrar();

        $this->post('/api/estudiante/foto', ['foto' => UploadedFile::fake()->create('yo.pdf', 5, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['foto']);
    }

    public function test_the_photo_period_says_its_limits_and_whether_it_has_started(): void
    {
        $admin = $this->usuarioConPermisos(['padron_estudiantes']);
        $hoy = now()->toDateString();
        $dia = static fn (int $dias): string => now()->addDays($dias)->toDateString();
        DB::table('periodos')->update(['fecha_inicio' => $dia(-30), 'fecha_fin' => $dia(60)]);

        $this->actingAs($admin)
            ->putJson('/api/estudiantes/plazo-fotos', ['desde' => $hoy, 'hasta' => $dia(181)])
            ->assertJsonPath('errors.hasta.0', 'Máximo 180 días');

        $this->putJson('/api/estudiantes/plazo-fotos', ['desde' => $hoy, 'hasta' => $dia(90)])
            ->assertStatus(422)
            ->assertJsonPath('errors.hasta.0', fn (string $texto): bool => str_starts_with($texto, 'Fuera del período'));

        $this->putJson('/api/estudiantes/plazo-fotos', ['desde' => $dia(3), 'hasta' => $dia(20)])
            ->assertOk()
            ->assertJsonPath('data.abierto', false)
            ->assertJsonPath('data.estado', 'proximo');
    }

    public function test_the_administration_sets_the_period_and_replaces_a_photo(): void
    {
        $admin = $this->usuarioConPermisos(['padron_estudiantes']);

        $this->actingAs($admin)
            ->putJson('/api/estudiantes/plazo-fotos', ['desde' => '2026-10-20', 'hasta' => '2026-10-10'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['hasta']);

        $this->actingAs($admin)
            ->putJson('/api/estudiantes/plazo-fotos', ['desde' => '2026-10-01', 'hasta' => '2026-10-20'])
            ->assertOk()
            ->assertJsonPath('data.abierto', true);

        $this->actingAs($admin)
            ->post("/api/estudiantes/{$this->estudiante->id}/foto", ['foto' => UploadedFile::fake()->image('a.png', 300, 400)], ['Accept' => 'application/json'])
            ->assertOk();
        $primera = DB::table('estudiantes')->where('id', $this->estudiante->id)->value('foto');
        self::assertIsString($primera);

        $this->actingAs($admin)
            ->post("/api/estudiantes/{$this->estudiante->id}/foto", ['foto' => UploadedFile::fake()->image('b.png', 300, 400)], ['Accept' => 'application/json'])
            ->assertOk();
        Storage::disk('local')->assertMissing($primera);

        $this->actingAs($admin)->get("/api/estudiantes/{$this->estudiante->id}/foto")->assertOk();
        $this->actingAs($admin)->getJson("/api/estudiantes/{$this->estudiante->id}")->assertJsonPath('data.con_foto', true);
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
        $this->verificar($this->cuentaDocente, $this->estudiante->codigo_universitario)
            ->assertJsonPath('estudiante.foto', null);

        $this->actingAs($this->usuarioConPermisos(['padron_estudiantes']))
            ->post("/api/estudiantes/{$this->estudiante->id}/foto", ['foto' => UploadedFile::fake()->image('a.png', 300, 400)], ['Accept' => 'application/json'])
            ->assertOk();

        $foto = $this->verificar($this->cuentaDocente, $this->estudiante->codigo_universitario)
            ->assertJsonPath('veredicto', 'ya_ingreso')
            ->json('estudiante.foto');
        self::assertIsString($foto);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $foto);
    }

    public function test_a_tiny_photo_is_rejected(): void
    {
        $this->actingAs($this->usuarioConPermisos(['padron_estudiantes']))
            ->post("/api/estudiantes/{$this->estudiante->id}/foto", ['foto' => UploadedFile::fake()->image('a.png', 1, 1)], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('errors.foto.0', 'Resolución insuficiente · mínimo 240 px');
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

    public function test_a_wrong_code_says_how_many_attempts_remain(): void
    {
        $codigo = $this->codigoEnviado();
        $malo = $codigo === '000000' ? '111111' : '000000';
        $cuerpo = ['codigo_universitario' => $this->estudiante->codigo_universitario, 'codigo' => $malo];

        $this->postJson('/api/auth/estudiante', $cuerpo)
            ->assertStatus(422)
            ->assertJsonPath('errors.codigo.0', 'Código incorrecto · 4 intentos restantes');

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/auth/estudiante', $cuerpo);
        }

        $this->postJson('/api/auth/estudiante', $cuerpo)
            ->assertJsonPath('errors.codigo.0', 'Código bloqueado · pida otro');
    }

    public function test_a_short_student_code_is_still_masked(): void
    {
        $this->postJson('/api/auth/estudiante/codigo', ['codigo_universitario' => '12345'])
            ->assertJsonPath('correo', '12•45@est.umss.edu');
    }

    public function test_each_exam_tells_when_the_student_entered(): void
    {
        $this->entrar();
        $this->getJson('/api/estudiante/examenes')->assertJsonPath('data.0.ingreso', null);

        $this->verificar($this->cuentaDocente, $this->estudiante->codigo_universitario)
            ->assertJsonPath('veredicto', 'admitido');

        $this->getJson('/api/estudiante/examenes')->assertJsonPath('data.0.ingreso', '08:20');
    }

    public function test_an_unknown_student_code_answers_like_a_known_one_with_a_wrong_code(): void
    {
        $this->postJson('/api/auth/estudiante/codigo', ['codigo_universitario' => '199900001'])->assertOk();

        $this->postJson('/api/auth/estudiante', ['codigo_universitario' => '199900001', 'codigo' => '123456'])
            ->assertStatus(422)
            ->assertJsonPath('errors.codigo.0', 'Código incorrecto · 4 intentos restantes');
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
}
