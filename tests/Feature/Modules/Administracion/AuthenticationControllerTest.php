<?php

namespace Tests\Feature\Modules\Administracion;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_login_with_valid_credentials(): void
    {
        $user = UserFactory::new()->createOne([
            'usuario' => 'active-user',
            'email' => 'active-user@example.invalid',
            'password' => 'CorrectPassword123!',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'usuario' => 'active-user',
            'password' => 'CorrectPassword123!',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Autenticación correcta.')
            ->assertJsonPath('user.id', $user->getKey())
            ->assertJsonPath('user.name', $user->name)
            ->assertJsonPath('user.email', $user->email)
            // El cliente arma su menu con el rol y sus permisos (HU-02).
            ->assertJsonPath('user.rol', 'Administrador')
            // Campos del contrato nuevo: los mismos de GET /api/auth/sesion.
            ->assertJsonPath('user.nombre', $user->nombre)
            ->assertJsonPath('user.usuario', $user->usuario)
            ->assertJsonPath('user.correo', 'active-user@example.invalid')
            ->assertJsonPath('user.docente_id', null)
            ->assertJsonPath('user.debe_cambiar_contrasena', false)
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id', 'nombre', 'usuario', 'correo', 'rol', 'permisos',
                    'debe_cambiar_contrasena', 'docente_id', 'name', 'email',
                ],
            ]);

        $permisos = $response->json('user.permisos');

        self::assertIsArray($permisos);
        self::assertContains('usuarios_roles', $permisos);

        $this->assertAuthenticatedAs($user);

        $this->assertNotNull(
            $user->fresh()?->last_login_at
        );
    }

    public function test_invalid_password_returns_generic_unauthorized_response(): void
    {
        UserFactory::new()->createOne([
            'usuario' => 'known-user',
            'email' => 'known-user@example.invalid',
            'password' => 'CorrectPassword123!',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'usuario' => 'known-user',
            'password' => 'IncorrectPassword123!',
        ]);

        $response
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Credenciales incorrectas.',
                'intentos_restantes' => 4,
            ]);

        $this->assertGuest();
    }

    public function test_unknown_username_returns_same_generic_unauthorized_response(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'usuario' => 'unknown-user',
            'password' => 'SomePassword123!',
        ]);

        $response
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Credenciales incorrectas.',
            ]);

        $this->assertGuest();
    }

    public function test_inactive_user_with_the_correct_password_is_told_the_account_is_locked(): void
    {
        UserFactory::new()->createOne([
            'usuario' => 'inactive-user',
            'email' => 'inactive-user@example.invalid',
            'password' => 'CorrectPassword123!',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'usuario' => 'inactive-user',
            'password' => 'CorrectPassword123!',
        ]);

        // Sin minutos: no es el bloqueo por intentos.
        $response
            ->assertStatus(423)
            ->assertExactJson([
                'code' => 'ACCOUNT_DISABLED',
                'message' => 'Cuenta bloqueada.',
            ]);

        $this->assertGuest();
    }

    public function test_inactive_user_with_a_wrong_password_gets_the_generic_response(): void
    {
        UserFactory::new()->createOne([
            'usuario' => 'inactive-user',
            'email' => 'inactive-user@example.invalid',
            'password' => 'CorrectPassword123!',
            'is_active' => false,
        ]);

        // Sin la contrasena no se revela que la cuenta existe y esta
        // desactivada: la misma respuesta que un usuario inexistente.
        $this->postJson('/api/auth/login', [
            'usuario' => 'inactive-user',
            'password' => 'WrongPassword123!',
        ])
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Credenciales incorrectas.',
            ]);

        $this->assertGuest();
    }

    public function test_login_validates_required_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'usuario',
                'password',
            ]);

        $this->assertGuest();
    }

    public function test_successful_login_regenerates_session_id(): void
    {
        UserFactory::new()->createOne([
            'usuario' => 'session-rotation',
            'email' => 'session-rotation@example.invalid',
            'password' => 'CorrectPassword123!',
            'is_active' => true,
        ]);

        $this->withSession([
            'session-probe' => 'present',
        ]);

        $previousSessionId = session()->getId();

        $this->postJson('/api/auth/login', [
            'usuario' => 'session-rotation',
            'password' => 'CorrectPassword123!',
        ])->assertOk();

        $this->assertNotSame(
            $previousSessionId,
            session()->getId()
        );
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = UserFactory::new()->createOne();

        $this->actingAs($user);

        $response = $this
            ->withSession([
                'private-data' => 'must-disappear',
            ])
            ->postJson('/api/auth/logout');

        $response->assertNoContent();

        $this->assertGuest();

        $response->assertSessionMissing('private-data');
    }

    public function test_guest_cannot_access_protected_logout_endpoint(): void
    {
        $this->postJson('/api/auth/logout')
            ->assertUnauthorized();

        $this->assertGuest();
    }
}
