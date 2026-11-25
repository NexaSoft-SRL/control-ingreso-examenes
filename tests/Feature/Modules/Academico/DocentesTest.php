<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Academico;

use App\Modules\Academico\Domain\Enums\TipoPeriodo;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Administracion\Infrastructure\Mail\CredencialesInicialesMail;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Support\DatosAcademicos;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

final class DocentesTest extends TestCase
{
    use DatosAcademicos;
    use OfertaDePrueba;
    use RefreshDatabase;
    use UsuarioConPermisos;

    public function test_teachers_are_listed_by_name_with_their_faculties_and_account_state(): void
    {
        $fcyt = $this->facultad();
        $fce = $this->facultad('fce');
        $periodo = $this->periodoVigente();

        $blanco = $this->docenteConCuenta(null, 'Blanco Coca Leticia');
        $temporal = $this->docenteConCuenta(
            UserFactory::new()->createOne(['password_changed_at' => null]),
            'Flores Villarroel Corina',
        );
        $sinCuenta = $this->docenteSinCuenta('Álvarez Rojas Pedro');
        // Solo dio clases en un período cerrado: no es de esta lista.
        $soloCerrado = $this->docenteSinCuenta('Zuleta Mamani Ruth');
        $anterior = $this->periodo('1/2020', -900, -800, TipoPeriodo::Semestre1);

        $this->grupo($sinCuenta, null, '5', $periodo, $fcyt);
        $this->grupo($soloCerrado, null, '6', $anterior, $fcyt);
        $this->grupo($blanco, null, '1', $periodo, $fcyt);
        $this->grupo($blanco, null, '2', $periodo, $fcyt);
        $this->grupo($blanco, null, '3', $periodo, $fce);
        $this->grupo($temporal, null, '4', $periodo, $fce);

        $this->actingAs($this->usuarioConPermisos(['aulas_docentes']))
            ->getJson('/api/docentes')
            ->assertOk()
            ->assertJsonPath('data.*.nombre', [
                'Álvarez Rojas Pedro',
                'Blanco Coca Leticia',
                'Flores Villarroel Corina',
            ])
            ->assertJsonPath('data.0', [
                'id' => $sinCuenta->id,
                'nombre' => 'Álvarez Rojas Pedro',
                'facultades' => [['sigla' => 'FCyT', 'grupos' => 1]],
                'cuenta' => 'sin_cuenta',
            ])
            // En dos facultades sale una sola vez.
            ->assertJsonPath('data.1', [
                'id' => $blanco->id,
                'nombre' => 'Blanco Coca Leticia',
                'facultades' => [
                    ['sigla' => 'FCyT', 'grupos' => 2],
                    ['sigla' => 'FCE', 'grupos' => 1],
                ],
                'cuenta' => 'activo',
            ])
            ->assertJsonPath('data.2.cuenta', 'temporal')
            ->assertJsonPath('meta', [
                'total' => 3,
                'pagina' => 1,
                'por_pagina' => 20,
                'conteos' => [
                    'todas' => 3,
                    'FCyT' => 2,
                    'FCE' => 2,
                    'sin_cuenta' => 1,
                    'varias_facultades' => 1,
                ],
            ]);
    }

    public function test_teachers_are_filtered_by_faculty_account_several_faculties_and_name(): void
    {
        $fcyt = $this->facultad();
        $fce = $this->facultad('fce');

        $enDos = $this->docenteConCuenta(null, 'Blanco Coca Leticia');
        $soloFce = $this->docenteSinCuenta('Núñez Vela Raúl');
        $arce = $this->docenteSinCuenta('Arce Rojas Ana');

        $this->grupo($arce, null, '4', null, $fcyt);
        $this->grupo($enDos, null, '1', null, $fcyt);
        $this->grupo($enDos, null, '2', null, $fce);
        $this->grupo($soloFce, null, '3', null, $fce);

        $sesion = $this->actingAs($this->usuarioConPermisos(['aulas_docentes']));

        $sesion->getJson('/api/docentes?facultad=fcyt')
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['Arce Rojas Ana', 'Blanco Coca Leticia'])
            ->assertJsonPath('meta.total', 2)
            // Los conteos no dependen del filtro elegido.
            ->assertJsonPath('meta.conteos.todas', 3)
            ->assertJsonPath('meta.conteos.FCE', 2);

        $sesion->getJson('/api/docentes?sin_cuenta=1')
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['Arce Rojas Ana', 'Núñez Vela Raúl']);

        $sesion->getJson('/api/docentes?facultad=fce&sin_cuenta=1')
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['Núñez Vela Raúl']);

        $sesion->getJson('/api/docentes?varias_facultades=1')
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['Blanco Coca Leticia']);

        $sesion->getJson('/api/docentes?buscar=NUNEZ')
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['Núñez Vela Raúl']);
    }

    public function test_teacher_faculties_follow_the_requested_period(): void
    {
        $fcyt = $this->facultad();
        $fce = $this->facultad('fce');
        $vigente = $this->periodoVigente();
        $anterior = $this->periodo('1/2020', -900, -800, TipoPeriodo::Semestre1);

        $docente = $this->docenteSinCuenta('Blanco Coca Leticia');
        $this->grupo($docente, null, '1', $vigente, $fcyt);
        $this->grupo($docente, null, '2', $anterior, $fce);

        $sesion = $this->actingAs($this->usuarioConPermisos(['aulas_docentes']));

        // Sin periodo cuentan los vigentes: no dicta en dos facultades a la vez.
        $sesion->getJson('/api/docentes')
            ->assertOk()
            ->assertJsonPath('data.0.facultades', [['sigla' => 'FCyT', 'grupos' => 1]])
            ->assertJsonPath('meta.conteos.varias_facultades', 0)
            ->assertJsonPath('meta.conteos.FCE', 0);

        $sesion->getJson("/api/docentes?periodo={$anterior->id}")
            ->assertOk()
            ->assertJsonPath('data.0.facultades', [['sigla' => 'FCE', 'grupos' => 1]])
            ->assertJsonPath('meta.conteos.FCyT', 0);

        $sesion->getJson("/api/docentes?periodo={$anterior->id}&facultad=fcyt")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_teachers_are_paginated_and_filters_are_validated(): void
    {
        foreach (['A', 'B', 'C'] as $letra) {
            $this->docenteSinCuenta('Docente '.$letra);
        }

        $sesion = $this->actingAs($this->usuarioConPermisos(['aulas_docentes']));

        $sesion->getJson('/api/docentes?pagina=2&por_pagina=2')
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['Docente C'])
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.pagina', 2)
            ->assertJsonPath('meta.por_pagina', 2);

        $sesion->getJson('/api/docentes?sin_cuenta=tal-vez&facultad=xyz&periodo=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sin_cuenta', 'facultad', 'periodo']);
    }

    public function test_teachers_require_session_and_permission(): void
    {
        $this->getJson('/api/docentes')->assertUnauthorized();

        $this->actingAs($this->usuarioConPermisos(['periodo_oferta']))
            ->getJson('/api/docentes')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'aulas_docentes');
    }

    public function test_teacher_detail_shows_subjects_per_faculty_and_the_account(): void
    {
        $fcyt = $this->facultad();
        $fce = $this->facultad('fce');
        $cuenta = UserFactory::new()->createOne(['usuario' => 'leticia blanco', 'email' => null]);
        $docente = $this->docenteConCuenta($cuenta, 'Blanco Coca Leticia');

        $taller = $this->asignatura('Taller de Ingeniería de Software');
        $intro = $this->asignatura('Introducción a la Programación');
        $this->grupo($docente, $intro, '1', null, $fcyt);
        $this->grupo($docente, $intro, '2', null, $fcyt);
        $this->grupo($docente, $taller, '1', null, $fcyt);
        $this->grupo($docente, $this->asignatura('Estadística'), '1', null, $fce);
        $this->grupo($docente, $this->asignatura('Vieja'), '1', $this->periodo('1/2020', -900, -800, TipoPeriodo::Semestre1), $fcyt);

        $this->actingAs($this->usuarioConPermisos(['aulas_docentes']))
            ->getJson("/api/docentes/{$docente->id}")
            ->assertOk()
            ->assertExactJson(['data' => [
                'id' => $docente->id,
                'nombre' => 'Blanco Coca Leticia',
                'grupos' => 4,
                'facultades' => [
                    [
                        'sigla' => 'FCyT',
                        'grupos' => 3,
                        'materias' => ['Introducción a la Programación', 'Taller de Ingeniería de Software'],
                    ],
                    ['sigla' => 'FCE', 'grupos' => 1, 'materias' => ['Estadística']],
                ],
                'cuenta' => ['estado' => 'activo', 'usuario' => 'leticia blanco', 'correo' => null, 'caduca_en' => null],
                'usuario_sugerido' => 'leticia blanco',
            ]]);
    }

    public function test_teacher_detail_without_account_proposes_a_free_username(): void
    {
        $docente = $this->docenteSinCuenta('Blanco Coca Leticia');
        $sesion = $this->actingAs($this->usuarioConPermisos(['aulas_docentes']));

        $sesion->getJson("/api/docentes/{$docente->id}")
            ->assertOk()
            ->assertJsonPath('data.cuenta', null)
            ->assertJsonPath('data.grupos', 0)
            ->assertJsonPath('data.facultades', [])
            ->assertJsonPath('data.usuario_sugerido', 'leticia blanco');

        // Si ya esta tomado, lleva sufijo.
        UserFactory::new()->createOne(['usuario' => 'leticiablanco']);

        $sesion->getJson("/api/docentes/{$docente->id}")
            ->assertOk()
            ->assertJsonPath('data.usuario_sugerido', 'leticia blanco2');
    }

    public function test_teacher_detail_tells_a_temporary_password_apart(): void
    {
        $cuenta = UserFactory::new()->createOne([
            'usuario' => 'corina.flores',
            'email' => 'corina@umss.edu.bo',
            'password_changed_at' => null,
            'password_temporal_expira_en' => now()->addHours(10),
        ]);
        $docente = $this->docenteConCuenta($cuenta, 'Flores Villarroel Corina');

        $cuentaJson = $this->actingAs($this->usuarioConPermisos(['aulas_docentes']))
            ->getJson("/api/docentes/{$docente->id}")
            ->assertOk()
            ->assertJsonPath('data.cuenta.estado', 'temporal')
            ->assertJsonPath('data.cuenta.usuario', 'corina.flores')
            ->assertJsonPath('data.cuenta.correo', 'corina@umss.edu.bo')
            ->json('data.cuenta.caduca_en');

        // Hasta cuándo sirve la temporal, en ISO 8601 con zona.
        $this->assertIsString($cuentaJson);
        $this->assertEqualsWithDelta(now()->addHours(10)->getTimestamp(), strtotime($cuentaJson), 2);
    }

    public function test_teacher_detail_of_an_expired_temporary_password_says_when_it_expired(): void
    {
        $cuenta = UserFactory::new()->createOne([
            'usuario' => 'corina.flores',
            'email' => null,
            'password_changed_at' => null,
            'password_temporal_expira_en' => now()->subDays(2),
        ]);
        $docente = $this->docenteConCuenta($cuenta, 'Flores Villarroel Corina');

        $vencimiento = $this->actingAs($this->usuarioConPermisos(['aulas_docentes']))
            ->getJson("/api/docentes/{$docente->id}")
            ->assertOk()
            ->assertJsonPath('data.cuenta.estado', 'temporal')
            ->assertJsonPath('data.cuenta.correo', null)
            ->json('data.cuenta.caduca_en');

        $this->assertIsString($vencimiento);
        $this->assertLessThan(now()->getTimestamp(), strtotime($vencimiento));
    }

    public function test_teacher_detail_handles_unknown_teacher_session_and_permission(): void
    {
        $docente = $this->docenteSinCuenta();

        $this->getJson("/api/docentes/{$docente->id}")->assertUnauthorized();

        $this->actingAs($this->usuarioConPermisos(['aulas_docentes']))
            ->getJson('/api/docentes/999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Docente no encontrado.']);

        // `usuarioConPermisos` reutiliza un mismo rol de prueba: para el
        // rechazo hace falta un rol distinto.
        $this->actingAs($this->usuarioConRol('Docente'))
            ->getJson("/api/docentes/{$docente->id}")
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'aulas_docentes');
    }

    public function test_activating_an_account_returns_the_temporary_password_once_and_links_the_teacher(): void
    {
        Mail::fake();
        $administrador = $this->usuarioConRol('Administrador');
        $docente = $this->docenteSinCuenta('Blanco Coca Leticia');

        $respuesta = $this->actingAs($administrador)
            ->postJson("/api/docentes/{$docente->id}/cuenta", ['usuario' => 'LeticiaBlanco', 'correo' => 'leticia@umss.edu.bo'])
            ->assertCreated()
            ->assertJsonPath('data.usuario', 'leticiablanco')
            ->assertJsonPath('data.enviada_a', 'leticia@umss.edu.bo')
            ->assertJsonPath('message', 'Cuenta activada.')
            ->assertJsonStructure(['data' => ['usuario', 'contrasena_temporal', 'enviada_a', 'caduca_en'], 'message']);

        $temporal = $respuesta->json('data.contrasena_temporal');
        $this->assertIsString($temporal);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{3}-[A-Za-z0-9]{4}-[A-Za-z0-9]{3}$/', $temporal);

        $cuenta = User::where('usuario', 'leticiablanco')->firstOrFail();

        $this->assertSame('Blanco Coca Leticia', $cuenta->name);
        $this->assertSame('leticia@umss.edu.bo', $cuenta->email);
        $this->assertNull($cuenta->password_changed_at);
        $this->assertTrue(Hash::check($temporal, $cuenta->password));
        $this->assertSame('Docente', $cuenta->role?->name);
        $this->assertSame($cuenta->id, $docente->refresh()->user_id);
        Mail::assertSent(CredencialesInicialesMail::class);

        // La temporal no se vuelve a mostrar: el detalle solo dice el estado.
        $this->getJson("/api/docentes/{$docente->id}")
            ->assertOk()
            ->assertJsonPath('data.cuenta.estado', 'temporal')
            ->assertJsonPath('data.cuenta.usuario', 'leticiablanco')
            ->assertJsonPath('data.cuenta.correo', 'leticia@umss.edu.bo')
            ->assertJsonMissingPath('data.contrasena_temporal');
    }

    public function test_activating_an_account_with_an_email_also_sends_the_credentials(): void
    {
        Mail::fake();
        $docente = $this->docenteSinCuenta('Blanco Coca Leticia');

        $this->actingAs($this->usuarioConRol('Administrador'))
            ->postJson("/api/docentes/{$docente->id}/cuenta", [
                'usuario' => 'leticiablanco',
                'correo' => 'Leticia.Blanco@umss.edu.bo',
            ])
            ->assertCreated()
            ->assertJsonPath('data.enviada_a', 'leticia.blanco@umss.edu.bo');

        $this->assertDatabaseHas('users', ['usuario' => 'leticiablanco', 'email' => 'leticia.blanco@umss.edu.bo']);
        Mail::assertSent(CredencialesInicialesMail::class);
    }

    public function test_activating_an_account_is_recorded_in_the_log(): void
    {
        $administrador = $this->usuarioConRol('Administrador');
        $docente = $this->docenteSinCuenta('Blanco Coca Leticia');

        $this->actingAs($administrador)
            ->postJson("/api/docentes/{$docente->id}/cuenta", ['usuario' => 'leticiablanco', 'correo' => 'leticia@umss.edu.bo'])
            ->assertCreated();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $administrador->id,
            'operacion' => 'docente.activar_cuenta',
            'tabla_afectada' => 'docentes',
            'registro_id' => $docente->id,
        ]);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $administrador->id,
            'operacion' => 'usuario.crear',
        ]);
    }

    public function test_a_teacher_with_an_account_cannot_get_another_one(): void
    {
        $docente = $this->docenteConCuenta(null, 'Blanco Coca Leticia');
        $cuentas = User::count();

        $this->actingAs($this->usuarioConRol('Administrador'))
            ->postJson("/api/docentes/{$docente->id}/cuenta", ['usuario' => 'leticiablanco', 'correo' => 'leticia@umss.edu.bo'])
            ->assertConflict()
            ->assertExactJson([
                'message' => 'El docente ya tiene una cuenta.',
                'codigo' => 'YA_TIENE_CUENTA',
            ]);

        $this->assertSame($cuentas + 1, User::count());
        $this->assertDatabaseMissing('bitacora_operaciones', ['operacion' => 'docente.activar_cuenta']);
    }

    public function test_activating_an_account_rejects_a_taken_username_or_email(): void
    {
        UserFactory::new()->createOne(['usuario' => 'leticiablanco', 'email' => 'leticia@umss.edu.bo']);
        $docente = $this->docenteSinCuenta('Blanco Coca Leticia');
        $sesion = $this->actingAs($this->usuarioConRol('Administrador'));

        $sesion->postJson("/api/docentes/{$docente->id}/cuenta", ['usuario' => 'LETICIABLANCO', 'correo' => 'otra@umss.edu.bo'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.usuario.0', 'Ya existe una cuenta con ese usuario.');

        $sesion->postJson("/api/docentes/{$docente->id}/cuenta", [
            'usuario' => 'leticia blanco2',
            'correo' => 'leticia@umss.edu.bo',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.correo.0', 'Ya existe una cuenta con ese correo.');

        $this->assertNull($docente->refresh()->user_id);
        $this->assertDatabaseMissing('users', ['usuario' => 'leticia blanco2']);
    }

    public function test_activating_an_account_validates_the_username_and_email(): void
    {
        $docente = $this->docenteSinCuenta();
        $sesion = $this->actingAs($this->usuarioConRol('Administrador'));

        $sesion->postJson("/api/docentes/{$docente->id}/cuenta", [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.usuario.0', 'Obligatorio')
            ->assertJsonPath('errors.correo.0', 'Obligatorio');

        foreach (['leticia/blanco', 'leticia..blanco', '.leticia', 'leticia-'] as $usuario) {
            $sesion->postJson("/api/docentes/{$docente->id}/cuenta", ['usuario' => $usuario, 'correo' => 'no-es-correo'])
                ->assertUnprocessable()
                ->assertJsonPath('errors.usuario.0', 'Nombre y apellido, separados por un espacio')
                ->assertJsonPath('errors.correo.0', 'Correo no válido');
        }

        $this->assertNull($docente->refresh()->user_id);
    }

    public function test_activating_an_account_handles_unknown_teacher_session_and_permission(): void
    {
        $docente = $this->docenteSinCuenta();
        $cuerpo = ['usuario' => 'leticiablanco', 'correo' => 'leticia@umss.edu.bo'];

        $this->postJson("/api/docentes/{$docente->id}/cuenta", $cuerpo)->assertUnauthorized();

        $this->actingAs($this->usuarioConRol('Administrador'))
            ->postJson('/api/docentes/999/cuenta', $cuerpo)
            ->assertNotFound()
            ->assertExactJson(['message' => 'Docente no encontrado.']);

        $this->actingAs($this->usuarioConRol('Docente'))
            ->postJson("/api/docentes/{$docente->id}/cuenta", $cuerpo)
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'aulas_docentes');

        $this->assertNull($docente->refresh()->user_id);
    }

    public function test_activating_an_account_requires_an_email(): void
    {
        Mail::fake();
        $docente = $this->docenteSinCuenta('Blanco Coca Leticia');
        $sesion = $this->actingAs($this->usuarioConRol('Administrador'));
        $cuentas = User::count();

        // Ausente, nulo o en blanco: sin correo no se activa.
        foreach ([['usuario' => 'leticiablanco'], ['usuario' => 'leticiablanco', 'correo' => null], ['usuario' => 'leticiablanco', 'correo' => '   ']] as $cuerpo) {
            $sesion->postJson("/api/docentes/{$docente->id}/cuenta", $cuerpo)
                ->assertUnprocessable()
                ->assertJsonPath('errors.correo.0', 'Obligatorio');
        }

        $this->assertNull($docente->refresh()->user_id);
        $this->assertSame($cuentas, User::count());
        Mail::assertNothingSent();
        $this->assertDatabaseMissing('bitacora_operaciones', ['operacion' => 'docente.activar_cuenta']);
    }

    public function test_a_new_temporary_password_replaces_the_old_one_and_must_be_changed_on_entry(): void
    {
        Mail::fake();
        $administrador = $this->usuarioConRol('Administrador');
        $cuenta = UserFactory::new()->createOne([
            'usuario' => 'corina flores',
            'email' => 'corina@umss.edu.bo',
            'password' => 'Anterior-123',
            'password_changed_at' => null,
            'password_temporal_expira_en' => now()->subDay(),
        ]);
        $docente = $this->docenteConCuenta($cuenta, 'Flores Villarroel Corina');

        $respuesta = $this->actingAs($administrador)
            ->postJson("/api/docentes/{$docente->id}/contrasena-temporal")
            ->assertOk()
            ->assertJsonPath('data.usuario', 'corina flores')
            ->assertJsonPath('data.enviada_a', 'corina@umss.edu.bo')
            ->assertJsonPath('message', 'Contraseña temporal nueva.')
            ->assertJsonStructure(['data' => ['usuario', 'contrasena_temporal', 'enviada_a', 'caduca_en'], 'message']);

        $nueva = $respuesta->json('data.contrasena_temporal');
        $this->assertIsString($nueva);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{3}-[A-Za-z0-9]{4}-[A-Za-z0-9]{3}$/', $nueva);

        // Caduca en 72 horas.
        $caduca = $respuesta->json('data.caduca_en');
        $this->assertIsString($caduca);
        $this->assertEqualsWithDelta(now()->addHours(72)->getTimestamp(), strtotime($caduca), 5);

        $cuenta->refresh();
        $this->assertTrue(Hash::check($nueva, $cuenta->password));
        $this->assertFalse(Hash::check('Anterior-123', $cuenta->password));
        // Sigue pendiente de cambiarla al entrar.
        $this->assertNull($cuenta->password_changed_at);
        Mail::assertSent(CredencialesInicialesMail::class);

        // Queda anotado quién la pidió.
        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $administrador->id,
            'operacion' => 'usuario.restablecer_temporal',
            'registro_id' => $cuenta->id,
        ]);

        // La anterior ya no entra; la nueva sí y pide cambiarla.
        $this->postJson('/api/auth/login', ['usuario' => 'corina flores', 'password' => 'Anterior-123'])->assertUnauthorized();
        $this->postJson('/api/auth/login', ['usuario' => 'corina flores', 'password' => $nueva])
            ->assertOk()
            ->assertJsonPath('user.debe_cambiar_contrasena', true);
    }

    public function test_a_new_temporary_password_without_an_email_is_not_sent(): void
    {
        Mail::fake();
        $cuenta = UserFactory::new()->createOne(['usuario' => 'corina flores', 'email' => null, 'password_changed_at' => null]);
        $docente = $this->docenteConCuenta($cuenta, 'Flores Villarroel Corina');

        $this->actingAs($this->usuarioConRol('Administrador'))
            ->postJson("/api/docentes/{$docente->id}/contrasena-temporal")
            ->assertOk()
            ->assertJsonPath('data.enviada_a', null);

        Mail::assertNothingSent();
    }

    public function test_a_new_temporary_password_only_applies_to_an_account_still_on_its_temporary_one(): void
    {
        $sesion = $this->actingAs($this->usuarioConRol('Administrador'));

        $sinCuenta = $this->docenteSinCuenta();
        $sesion->postJson("/api/docentes/{$sinCuenta->id}/contrasena-temporal")
            ->assertConflict()
            ->assertExactJson(['message' => 'El docente no tiene cuenta.', 'codigo' => 'SIN_CUENTA']);

        $propia = UserFactory::new()->createOne(['password_changed_at' => now()->subDay()]);
        $conPropia = $this->docenteConCuenta($propia);
        $hash = $propia->password;
        $sesion->postJson("/api/docentes/{$conPropia->id}/contrasena-temporal")
            ->assertConflict()
            ->assertExactJson(['message' => 'La cuenta ya tiene contraseña propia.', 'codigo' => 'CONTRASENA_PROPIA']);
        $this->assertSame($hash, $propia->refresh()->password);

        $bloqueada = UserFactory::new()->createOne(['password_changed_at' => null, 'is_active' => false]);
        $conBloqueo = $this->docenteConCuenta($bloqueada);
        $sesion->postJson("/api/docentes/{$conBloqueo->id}/contrasena-temporal")
            ->assertConflict()
            ->assertJsonPath('codigo', 'BLOQUEADA');

        $this->assertDatabaseMissing('bitacora_operaciones', ['operacion' => 'usuario.restablecer_temporal']);
    }

    public function test_a_new_temporary_password_handles_unknown_teacher_session_and_permission(): void
    {
        $cuenta = UserFactory::new()->createOne(['password_changed_at' => null]);
        $docente = $this->docenteConCuenta($cuenta);
        $hash = $cuenta->password;

        $this->postJson("/api/docentes/{$docente->id}/contrasena-temporal")->assertUnauthorized();

        $this->actingAs($this->usuarioConRol('Administrador'))
            ->postJson('/api/docentes/999/contrasena-temporal')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Docente no encontrado.']);

        // Con el permiso de la pantalla Docentes basta: no hace falta el de Usuarios y roles.
        $this->actingAs($this->usuarioConRol('Docente'))
            ->postJson("/api/docentes/{$docente->id}/contrasena-temporal")
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'aulas_docentes');
        $this->assertSame($hash, $cuenta->refresh()->password);

        Mail::fake();
        $this->actingAs($this->usuarioConPermisos(['aulas_docentes']))
            ->postJson("/api/docentes/{$docente->id}/contrasena-temporal")
            ->assertOk();
    }
}
