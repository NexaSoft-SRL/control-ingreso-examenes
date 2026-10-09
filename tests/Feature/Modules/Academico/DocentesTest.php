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
                'facultades' => [],
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
                'cuenta' => 'activa',
            ])
            ->assertJsonPath('data.2.cuenta', 'temporal')
            ->assertJsonPath('meta', [
                'total' => 3,
                'pagina' => 1,
                'por_pagina' => 20,
                'conteos' => [
                    'todas' => 3,
                    'FCyT' => 1,
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
        $this->docenteSinCuenta('Arce Rojas Ana');

        $this->grupo($enDos, null, '1', null, $fcyt);
        $this->grupo($enDos, null, '2', null, $fce);
        $this->grupo($soloFce, null, '3', null, $fce);

        $sesion = $this->actingAs($this->usuarioConPermisos(['aulas_docentes']));

        $sesion->getJson('/api/docentes?facultad=fcyt')
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['Blanco Coca Leticia'])
            ->assertJsonPath('meta.total', 1)
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
        $cuenta = UserFactory::new()->createOne(['usuario' => 'leticia.blanco', 'correo' => null]);
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
                'cuenta' => ['estado' => 'activa', 'usuario' => 'leticia.blanco', 'correo' => null],
                'usuario_sugerido' => 'leticia.blanco',
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
            ->assertJsonPath('data.usuario_sugerido', 'leticia.blanco');

        // Si ya esta tomado, lleva sufijo.
        UserFactory::new()->createOne(['usuario' => 'leticia.blanco']);

        $sesion->getJson("/api/docentes/{$docente->id}")
            ->assertOk()
            ->assertJsonPath('data.usuario_sugerido', 'leticia.blanco2');
    }

    public function test_teacher_detail_tells_a_temporary_password_apart(): void
    {
        $cuenta = UserFactory::new()->createOne([
            'usuario' => 'corina.flores',
            'correo' => 'corina@umss.edu.bo',
            'password_changed_at' => null,
        ]);
        $docente = $this->docenteConCuenta($cuenta, 'Flores Villarroel Corina');

        $this->actingAs($this->usuarioConPermisos(['aulas_docentes']))
            ->getJson("/api/docentes/{$docente->id}")
            ->assertOk()
            ->assertJsonPath('data.cuenta', [
                'estado' => 'temporal',
                'usuario' => 'corina.flores',
                'correo' => 'corina@umss.edu.bo',
            ]);
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
            ->postJson("/api/docentes/{$docente->id}/cuenta", ['usuario' => 'Leticia.Blanco', 'correo' => null])
            ->assertCreated()
            ->assertJsonPath('data.usuario', 'leticia.blanco')
            ->assertJsonPath('data.enviada_a', null)
            ->assertJsonPath('message', 'Cuenta activada.')
            ->assertJsonStructure(['data' => ['usuario', 'contrasena_temporal', 'enviada_a', 'caduca_en'], 'message']);

        $temporal = $respuesta->json('data.contrasena_temporal');
        $this->assertIsString($temporal);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{3}-[A-Za-z0-9]{4}-[A-Za-z0-9]{3}$/', $temporal);

        $cuenta = User::where('usuario', 'leticia.blanco')->firstOrFail();

        $this->assertSame('Blanco Coca Leticia', $cuenta->nombre);
        $this->assertNull($cuenta->correo);
        $this->assertNull($cuenta->password_changed_at);
        $this->assertTrue(Hash::check($temporal, $cuenta->password));
        $this->assertSame('Docente', $cuenta->role?->name);
        $this->assertSame($cuenta->id, $docente->refresh()->user_id);
        Mail::assertNothingSent();

        // La temporal no se vuelve a mostrar: el detalle solo dice el estado.
        $this->getJson("/api/docentes/{$docente->id}")
            ->assertOk()
            ->assertJsonPath('data.cuenta', ['estado' => 'temporal', 'usuario' => 'leticia.blanco', 'correo' => null])
            ->assertJsonMissingPath('data.contrasena_temporal');
    }

    public function test_activating_an_account_with_an_email_also_sends_the_credentials(): void
    {
        Mail::fake();
        $docente = $this->docenteSinCuenta('Blanco Coca Leticia');

        $this->actingAs($this->usuarioConRol('Administrador'))
            ->postJson("/api/docentes/{$docente->id}/cuenta", [
                'usuario' => 'leticia.blanco',
                'correo' => 'Leticia.Blanco@umss.edu.bo',
            ])
            ->assertCreated()
            ->assertJsonPath('data.enviada_a', 'leticia.blanco@umss.edu.bo');

        $this->assertDatabaseHas('usuarios', ['usuario' => 'leticia.blanco', 'correo' => 'leticia.blanco@umss.edu.bo']);
        Mail::assertSent(CredencialesInicialesMail::class);
    }

    public function test_activating_an_account_is_recorded_in_the_log(): void
    {
        $administrador = $this->usuarioConRol('Administrador');
        $docente = $this->docenteSinCuenta('Blanco Coca Leticia');

        $this->actingAs($administrador)
            ->postJson("/api/docentes/{$docente->id}/cuenta", ['usuario' => 'leticia.blanco'])
            ->assertCreated();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $administrador->id,
            'operacion' => 'docente.activar_cuenta',
            'tabla_afectada' => 'docentes',
            'registro_id' => $docente->id,
        ]);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $administrador->id,
            'operacion' => 'usuario.registrar',
        ]);
    }

    public function test_a_teacher_with_an_account_cannot_get_another_one(): void
    {
        $docente = $this->docenteConCuenta(null, 'Blanco Coca Leticia');
        $cuentas = User::count();

        $this->actingAs($this->usuarioConRol('Administrador'))
            ->postJson("/api/docentes/{$docente->id}/cuenta", ['usuario' => 'leticia.blanco'])
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
        UserFactory::new()->createOne(['usuario' => 'leticia.blanco', 'correo' => 'leticia@umss.edu.bo']);
        $docente = $this->docenteSinCuenta('Blanco Coca Leticia');
        $sesion = $this->actingAs($this->usuarioConRol('Administrador'));

        $sesion->postJson("/api/docentes/{$docente->id}/cuenta", ['usuario' => 'LETICIA.BLANCO'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.usuario.0', 'Ya existe una cuenta con ese usuario.');

        $sesion->postJson("/api/docentes/{$docente->id}/cuenta", [
            'usuario' => 'leticia.blanco2',
            'correo' => 'leticia@umss.edu.bo',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.correo.0', 'Ya existe una cuenta con ese correo.');

        $this->assertNull($docente->refresh()->user_id);
        $this->assertDatabaseMissing('usuarios', ['usuario' => 'leticia.blanco2']);
    }

    public function test_activating_an_account_validates_the_username_and_email(): void
    {
        $docente = $this->docenteSinCuenta();
        $sesion = $this->actingAs($this->usuarioConRol('Administrador'));

        $sesion->postJson("/api/docentes/{$docente->id}/cuenta", [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.usuario.0', 'El campo usuario es obligatorio.');

        $sesion->postJson("/api/docentes/{$docente->id}/cuenta", ['usuario' => 'leticia blanco', 'correo' => 'no-es-correo'])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.usuario.0',
                'El usuario solo admite letras minúsculas, números, punto, guion y guion bajo.',
            )
            ->assertJsonPath('errors.correo.0', 'El correo no tiene un formato válido.');

        $this->assertNull($docente->refresh()->user_id);
    }

    public function test_activating_an_account_handles_unknown_teacher_session_and_permission(): void
    {
        $docente = $this->docenteSinCuenta();
        $cuerpo = ['usuario' => 'leticia.blanco'];

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
}
