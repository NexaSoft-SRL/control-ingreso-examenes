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
            'password' => 'CorrectPassword123!',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'usuario' => 'active-user',
            'password' => 'CorrectPassword123!',
        ]);

        $response
            ->assertOk()
            ->assertExactJson([
                'message' => 'Autenticación correcta.',
                'user' => [
                    'id' => $user->getKey(),
                    'nombre' => $user->name,
                    'usuario' => 'active-user',
                    'correo' => $user->email,
                    'rol' => null,
                    'permisos' => [],
                    'debe_cambiar_contrasena' => false,
                    'docente_id' => null,
                ],
            ]);

        $this->assertAuthenticatedAs($user);

        $this->assertNotNull(
            $user->fresh()?->last_login_at
        );
    }

    public function test_the_user_name_is_not_case_sensitive(): void
    {
        $user = UserFactory::new()->createOne([
            'usuario' => 'leticia.blanco',
            'password' => 'CorrectPassword123!',
        ]);

        $this->postJson('/api/auth/login', [
            'usuario' => '  Leticia.Blanco ',
            'password' => 'CorrectPassword123!',
        ])
            ->assertOk()
            ->assertJsonPath('user.usuario', 'leticia.blanco');

        $this->assertAuthenticatedAs($user);
    }

    public function test_an_account_without_contact_email_can_login(): void
    {
        UserFactory::new()->createOne([
            'usuario' => 'sin.correo',
            'email' => null,
            'password' => 'CorrectPassword123!',
        ]);

        $this->postJson('/api/auth/login', [
            'usuario' => 'sin.correo',
            'password' => 'CorrectPassword123!',
        ])
            ->assertOk()
            ->assertJsonPath('user.correo', null);
    }

    public function test_the_email_no_longer_serves_as_login_identifier(): void
    {
        UserFactory::new()->createOne([
            'usuario' => 'con.correo',
            'email' => 'con.correo@umss.edu.bo',
            'password' => 'CorrectPassword123!',
        ]);

        $this->postJson('/api/auth/login', [
            'usuario' => 'con.correo@umss.edu.bo',
            'password' => 'CorrectPassword123!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['usuario']);

        $this->assertGuest();
    }

    public function test_invalid_password_reports_the_remaining_attempts(): void
    {
        UserFactory::new()->createOne([
            'usuario' => 'known-user',
            'password' => 'CorrectPassword123!',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'usuario' => 'known-user',
            'password' => 'IncorrectPassword123!',
        ]);

        $response
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Usuario o contraseña incorrectos.',
                'intentos_restantes' => 4,
            ]);

        $this->assertGuest();
    }

    public function test_unknown_user_gets_the_same_message_without_remaining_attempts(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'usuario' => 'unknown-user',
            'password' => 'SomePassword123!',
        ]);

        $response
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Usuario o contraseña incorrectos.',
            ]);

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        UserFactory::new()->createOne([
            'usuario' => 'inactive-user',
            'password' => 'CorrectPassword123!',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'usuario' => 'inactive-user',
            'password' => 'CorrectPassword123!',
        ]);

        $response
            ->assertStatus(423)
            ->assertExactJson([
                'message' => 'Cuenta bloqueada.',
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
