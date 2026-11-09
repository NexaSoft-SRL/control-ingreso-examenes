<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Administracion\Infrastructure\Mail\CredencialesInicialesMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * Ruta 9: el alta de una cuenta. Sale con una contrasena temporal que se
 * devuelve una sola vez y, si hay correo, ademas se envia.
 */
final class CredencialesInicialesTest extends TestCase
{
    use RefreshDatabase;
    use UsuarioConPermisos;

    private const FORMATO = '/^[A-Z][a-z][2-9]-[A-Z][a-z]{2}[2-9]-[A-Z][a-z][2-9]$/';

    private User $administrador;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->administrador = $this->usuarioConRol('Administrador', [
            'usuario' => 'ana.rojas',
            'email' => 'admin@umss.edu.bo',
        ]);
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function cuerpo(array $cambios = []): array
    {
        return array_merge([
            'nombre' => 'Villarroel Soto Patricia',
            'usuario' => 'patricia.villarroel',
            'correo' => 'patricia@umss.edu.bo',
            'rol' => 'Docente',
        ], $cambios);
    }

    public function test_creating_an_account_returns_the_temporary_password_once(): void
    {
        $this->travelTo('2026-10-12 08:21:00');

        $respuesta = $this->actingAs($this->administrador)
            ->postJson('/api/usuarios', $this->cuerpo())
            ->assertCreated()
            ->assertJsonPath('message', 'Usuario creado.')
            ->assertJsonPath('data.nombre', 'Villarroel Soto Patricia')
            ->assertJsonPath('data.usuario', 'patricia.villarroel')
            ->assertJsonPath('data.correo', 'patricia@umss.edu.bo')
            ->assertJsonPath('data.rol', 'Docente')
            ->assertJsonPath('data.estado', 'temporal')
            ->assertJsonPath('enviada_a', 'patricia@umss.edu.bo')
            // 72 horas despues, en la hora de Cochabamba.
            ->assertJsonPath('caduca_en', '2026-10-15T08:21:00-04:00');

        $temporal = $respuesta->json('contrasena_temporal');

        self::assertIsString($temporal);
        $this->assertMatchesRegularExpression(self::FORMATO, $temporal);

        $creada = User::where('usuario', 'patricia.villarroel')->firstOrFail();

        $this->assertSame($creada->getKey(), $respuesta->json('data.id'));
        $this->assertTrue(Hash::check($temporal, $creada->password));
    }

    public function test_creating_an_account_emails_the_initial_credentials(): void
    {
        $this->actingAs($this->administrador)
            ->postJson('/api/usuarios', $this->cuerpo())
            ->assertCreated();

        Mail::assertSent(
            CredencialesInicialesMail::class,
            fn (CredencialesInicialesMail $correo): bool => $correo->hasTo('patricia@umss.edu.bo')
                && $correo->usuario === 'patricia.villarroel'
        );
    }

    public function test_the_sent_email_is_recorded(): void
    {
        $this->actingAs($this->administrador)
            ->postJson('/api/usuarios', $this->cuerpo())
            ->assertCreated();

        $this->assertDatabaseHas('envios_correo', [
            'tipo' => 'CREDENCIALES',
            'destinatario' => 'patricia@umss.edu.bo',
            'user_id' => User::where('usuario', 'patricia.villarroel')->value('id'),
            'estado' => 'ENVIADO',
            'solicitado_por' => $this->administrador->getKey(),
        ]);
    }

    public function test_an_account_without_email_is_created_and_nothing_is_sent(): void
    {
        $this->actingAs($this->administrador)
            ->postJson('/api/usuarios', $this->cuerpo(['correo' => null, 'rol' => 'Auxiliar']))
            ->assertCreated()
            ->assertJsonPath('data.correo', null)
            ->assertJsonPath('data.rol', 'Auxiliar')
            ->assertJsonPath('enviada_a', null);

        Mail::assertNothingSent();
        $this->assertDatabaseCount('envios_correo', 0);
    }

    public function test_the_new_account_must_define_its_own_password(): void
    {
        $this->actingAs($this->administrador)
            ->postJson('/api/usuarios', $this->cuerpo())
            ->assertCreated();

        $creada = User::where('usuario', 'patricia.villarroel')->firstOrFail();

        $this->assertNull($creada->getAttribute('password_changed_at'));
        $this->assertNotNull($creada->getAttribute('password_temporal_expira_en'));
    }

    public function test_the_username_and_the_email_are_stored_in_lowercase(): void
    {
        $this->actingAs($this->administrador)
            ->postJson('/api/usuarios', $this->cuerpo([
                'nombre' => '  Villarroel Soto Patricia ',
                'usuario' => ' Patricia.Villarroel ',
                'correo' => 'Patricia@UMSS.edu.bo',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.usuario', 'patricia.villarroel');

        $this->assertDatabaseHas('users', [
            'name' => 'Villarroel Soto Patricia',
            'usuario' => 'patricia.villarroel',
            'email' => 'patricia@umss.edu.bo',
        ]);
    }

    public function test_the_fields_are_validated(): void
    {
        $this->actingAs($this->administrador)
            ->postJson('/api/usuarios', [
                'nombre' => 'Pa',
                'usuario' => 'patricia/villarroel',
                'correo' => 'no-es-correo',
                'rol' => 'Responsable',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.nombre.0', 'Mínimo 3 caracteres')
            ->assertJsonPath('errors.usuario.0', 'Nombre y apellido, separados por un espacio')
            ->assertJsonPath('errors.correo.0', 'Correo no válido')
            ->assertJsonPath('errors.rol.0', 'El rol no existe');

        $this->actingAs($this->administrador)
            ->postJson('/api/usuarios', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.nombre.0', 'Obligatorio')
            ->assertJsonPath('errors.usuario.0', 'Obligatorio')
            ->assertJsonPath('errors.rol.0', 'Obligatorio')
            ->assertJsonMissingValidationErrors('correo');

        Mail::assertNothingSent();
    }

    public function test_the_username_must_not_be_already_taken(): void
    {
        $this->actingAs($this->administrador)
            ->postJson('/api/usuarios', $this->cuerpo(['usuario' => 'ANA.Rojas']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.usuario.0', 'Ya existe');

        $this->assertDatabaseMissing('users', ['email' => 'patricia@umss.edu.bo']);
    }

    public function test_the_email_must_not_be_already_registered(): void
    {
        $this->actingAs($this->administrador)
            ->postJson('/api/usuarios', $this->cuerpo(['correo' => 'Admin@umss.edu.bo']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.correo.0', 'Ya existe');
    }

    public function test_a_guest_cannot_create_accounts(): void
    {
        $this->postJson('/api/usuarios', $this->cuerpo())->assertUnauthorized();

        Mail::assertNothingSent();
        $this->assertDatabaseMissing('users', ['usuario' => 'patricia.villarroel']);
    }

    public function test_the_permission_is_required_to_create_accounts(): void
    {
        $this->actingAs($this->usuarioConRol('Docente'))
            ->postJson('/api/usuarios', $this->cuerpo())
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['usuario' => 'patricia.villarroel']);
    }

    public function test_the_creation_is_recorded_in_the_audit_log_without_the_password(): void
    {
        $respuesta = $this->actingAs($this->administrador)
            ->postJson('/api/usuarios', $this->cuerpo())
            ->assertCreated();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'usuario.crear',
            'usuario_id' => $this->administrador->getKey(),
            'tabla_afectada' => 'users',
            'registro_id' => $respuesta->json('data.id'),
        ]);

        $temporal = $respuesta->json('contrasena_temporal');

        self::assertIsString($temporal);
        $this->assertFalse(
            DB::table('bitacora_operaciones')
                ->where('descripcion', 'like', '%'.$temporal.'%')
                ->exists()
        );
    }
}
