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
            'email' => 'active-user@example.invalid',
            'password' => 'CorrectPassword123!',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'active-user@example.invalid',
            'password' => 'CorrectPassword123!',
        ]);

        $response
            ->assertOk()
            ->assertExactJson([
                'message' => 'Autenticación correcta.',
                'user' => [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);

        $this->assertAuthenticatedAs($user);

        $this->assertNotNull(
            $user->fresh()?->last_login_at
        );
    }

    public function test_invalid_password_returns_generic_unauthorized_response(): void
    {
        UserFactory::new()->createOne([
            'email' => 'known-user@example.invalid',
            'password' => 'CorrectPassword123!',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'known-user@example.invalid',
            'password' => 'IncorrectPassword123!',
        ]);

        $response
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Credenciales incorrectas.',
            ]);

        $this->assertGuest();
    }

    public function test_unknown_email_returns_same_generic_unauthorized_response(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'unknown-user@example.invalid',
            'password' => 'SomePassword123!',
        ]);

        $response
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Credenciales incorrectas.',
            ]);

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        UserFactory::new()->createOne([
            'email' => 'inactive-user@example.invalid',
            'password' => 'CorrectPassword123!',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'inactive-user@example.invalid',
            'password' => 'CorrectPassword123!',
        ]);

        $response
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
                'email',
                'password',
            ]);

        $this->assertGuest();
    }

    public function test_successful_login_regenerates_session_id(): void
    {
        UserFactory::new()->createOne([
            'email' => 'session-rotation@example.invalid',
            'password' => 'CorrectPassword123!',
            'is_active' => true,
        ]);

        $this->withSession([
            'session-probe' => 'present',
        ]);

        $previousSessionId = session()->getId();

        $this->postJson('/api/auth/login', [
            'email' => 'session-rotation@example.invalid',
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
