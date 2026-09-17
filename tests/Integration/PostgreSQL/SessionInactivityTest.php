<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSQL;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class SessionInactivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('session.driver', 'database');
        config()->set('session.lifetime', 5);
        config()->set('session.table', 'sessions');

        // La expiración se prueba durante read(); desactivamos GC aleatorio
        // para que la eliminación física no afecte la prueba.
        config()->set('session.lottery', [0, 100]);

        $this->forgetRequestAuthenticationState();
    }

    public function test_authenticated_session_expires_after_inactivity(): void
    {
        $user = UserFactory::new()->createOne([
            'email' => 'expired-session@example.invalid',
            'password' => 'CorrectPassword123!',
            'is_active' => true,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'expired-session@example.invalid',
            'password' => 'CorrectPassword123!',
        ])->assertOk();

        $sessionId = $this->sessionIdForUser(
            $this->userId($user)
        );

        DB::table('sessions')
            ->where('id', $sessionId)
            ->update([
                'last_activity' => now()
                    ->subMinutes(6)
                    ->getTimestamp(),
            ]);

        $this->forgetRequestAuthenticationState();

        $this->withCredentials()
            ->withCookie(
                $this->sessionCookieName(),
                $sessionId
            )
            ->postJson('/api/auth/logout')
            ->assertUnauthorized();

        $this->assertGuest();
    }

    public function test_authenticated_session_remains_valid_within_lifetime(): void
    {
        $user = UserFactory::new()->createOne([
            'email' => 'active-session@example.invalid',
            'password' => 'CorrectPassword123!',
            'is_active' => true,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'active-session@example.invalid',
            'password' => 'CorrectPassword123!',
        ])->assertOk();

        $sessionId = $this->sessionIdForUser(
            $this->userId($user)
        );

        DB::table('sessions')
            ->where('id', $sessionId)
            ->update([
                'last_activity' => now()
                    ->subMinutes(4)
                    ->getTimestamp(),
            ]);

        $this->forgetRequestAuthenticationState();

        $this->withCredentials()
            ->withCookie(
                $this->sessionCookieName(),
                $sessionId
            )
            ->postJson('/api/auth/logout')
            ->assertNoContent();

        $this->assertGuest();
    }

    public function test_logged_out_session_cannot_be_reused(): void
    {
        $user = UserFactory::new()->createOne([
            'email' => 'logout-replay@example.invalid',
            'password' => 'CorrectPassword123!',
            'is_active' => true,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'logout-replay@example.invalid',
            'password' => 'CorrectPassword123!',
        ])->assertOk();

        $sessionId = $this->sessionIdForUser(
            $this->userId($user)
        );

        $this->assertTrue(
            DB::table('sessions')
                ->where('id', $sessionId)
                ->exists()
        );

        $this->forgetRequestAuthenticationState();

        $this->withCredentials()
            ->withCookie(
                $this->sessionCookieName(),
                $sessionId
            )
            ->postJson('/api/auth/logout')
            ->assertNoContent();

        $this->assertGuest();

        $this->assertFalse(
            DB::table('sessions')
                ->where('id', $sessionId)
                ->exists()
        );

        $this->forgetRequestAuthenticationState();

        $this->withCredentials()
            ->withCookie(
                $this->sessionCookieName(),
                $sessionId
            )
            ->postJson('/api/auth/logout')
            ->assertUnauthorized();

        $this->assertGuest();
    }

    private function forgetRequestAuthenticationState(): void
    {
        Auth::forgetGuards();

        /** @var SessionManager $sessionManager */
        $sessionManager = app('session');

        $sessionManager->forgetDrivers();

        $this->app->forgetInstance('session.store');
    }

    private function sessionIdForUser(int $userId): string
    {
        $sessionId = DB::table('sessions')
            ->where('user_id', $userId)
            ->value('id');

        if (! is_string($sessionId) || $sessionId === '') {
            throw new RuntimeException(
                'No se encontró la sesión database del usuario.'
            );
        }

        return $sessionId;
    }

    private function sessionCookieName(): string
    {
        $cookieName = config('session.cookie');

        if (! is_string($cookieName) || $cookieName === '') {
            throw new RuntimeException(
                'El nombre de cookie de sesión no es válido.'
            );
        }

        return $cookieName;
    }

    private function userId(object $user): int
    {
        if (! method_exists($user, 'getKey')) {
            throw new RuntimeException(
                'El usuario no expone getKey().'
            );
        }

        $userId = $user->getKey();

        if (! is_int($userId)) {
            throw new RuntimeException(
                'El identificador de usuario no es entero.'
            );
        }

        return $userId;
    }
}
