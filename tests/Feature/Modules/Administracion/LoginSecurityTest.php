<?php

namespace Tests\Feature\Modules\Administracion;

use Database\Factories\UserFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LoginSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'auth_security.max_failed_attempts',
            3
        );

        config()->set(
            'auth_security.lockout_minutes',
            15
        );
    }

    public function test_the_username_can_be_typed_as_name_and_surname_or_all_together(): void
    {
        UserFactory::new()->createOne([
            'usuario' => 'leticia blanco',
            'password' => 'CorrectPassword123!',
            'is_active' => true,
        ]);

        // Con tildes, mayusculas y espacios de mas; y junto, como antes.
        foreach (['  Letícia  Blanco ', 'leticiablanco'] as $escrito) {
            $this->postJson('/api/auth/login', [
                'usuario' => $escrito,
                'password' => 'CorrectPassword123!',
            ])->assertOk();

            $this->postJson('/api/auth/logout');
            $this->app['auth']->forgetGuards();
        }
    }

    public function test_two_accounts_cannot_differ_only_in_the_space(): void
    {
        UserFactory::new()->createOne(['usuario' => 'leticia blanco']);

        $this->expectException(QueryException::class);
        UserFactory::new()->createOne(['usuario' => 'leticiablanco']);
    }

    public function test_failed_logins_increment_counter_and_lock_at_threshold(): void
    {
        $user = UserFactory::new()->createOne([
            'usuario' => 'lock-test',
            'password' => 'CorrectPassword123!',
            'is_active' => true,
        ]);

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $this->postJson('/api/auth/login', [
                'usuario' => 'lock-test',
                'password' => 'IncorrectPassword123!',
            ])
                ->assertUnauthorized()
                ->assertJsonPath('intentos_restantes', 3 - $attempt);
        }

        $this->assertSame(
            2,
            DB::table('users')
                ->where('id', $user->getKey())
                ->value('failed_login_attempts')
        );

        $this->assertNull(
            DB::table('users')
                ->where('id', $user->getKey())
                ->value('locked_until')
        );

        // El intento que alcanza el limite ya responde con el bloqueo.
        $this->postJson('/api/auth/login', [
            'usuario' => 'lock-test',
            'password' => 'IncorrectPassword123!',
        ])
            ->assertStatus(423)
            ->assertExactJson([
                'message' => 'Cuenta bloqueada.',
                'minutos' => 15,
            ]);

        $this->assertSame(
            3,
            DB::table('users')
                ->where('id', $user->getKey())
                ->value('failed_login_attempts')
        );

        $this->assertNotNull(
            DB::table('users')
                ->where('id', $user->getKey())
                ->value('locked_until')
        );

        $this->assertSame(
            3,
            DB::table('login_attempts')
                ->where('user_id', $user->getKey())
                ->where('successful', false)
                ->count()
        );
    }

    public function test_locked_account_rejects_correct_password_without_extending_lock(): void
    {
        $user = UserFactory::new()->createOne([
            'usuario' => 'locked-user',
            'password' => 'CorrectPassword123!',
            'failed_login_attempts' => 3,
            'locked_until' => now()->addMinutes(15),
        ]);

        $lockedUntil = DB::table('users')
            ->where('id', $user->getKey())
            ->value('locked_until');

        $this->assertNotNull($lockedUntil);

        $this->postJson('/api/auth/login', [
            'usuario' => 'locked-user',
            'password' => 'CorrectPassword123!',
        ])
            ->assertStatus(423)
            ->assertExactJson([
                'message' => 'Cuenta bloqueada.',
                'minutos' => 15,
            ]);

        $this->assertSame(
            3,
            DB::table('users')
                ->where('id', $user->getKey())
                ->value('failed_login_attempts')
        );

        $this->assertSame(
            $lockedUntil,
            DB::table('users')
                ->where('id', $user->getKey())
                ->value('locked_until')
        );
    }

    public function test_success_before_threshold_resets_failed_attempts(): void
    {
        $user = UserFactory::new()->createOne([
            'usuario' => 'reset-counter',
            'password' => 'CorrectPassword123!',
        ]);

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $this->postJson('/api/auth/login', [
                'usuario' => 'reset-counter',
                'password' => 'IncorrectPassword123!',
            ])->assertUnauthorized();
        }

        $this->assertSame(
            2,
            DB::table('users')
                ->where('id', $user->getKey())
                ->value('failed_login_attempts')
        );

        $this->postJson('/api/auth/login', [
            'usuario' => 'reset-counter',
            'password' => 'CorrectPassword123!',
        ])->assertOk();

        $this->assertSame(
            0,
            DB::table('users')
                ->where('id', $user->getKey())
                ->value('failed_login_attempts')
        );

        $this->assertNull(
            DB::table('users')
                ->where('id', $user->getKey())
                ->value('locked_until')
        );

        $this->assertSame(
            1,
            DB::table('login_attempts')
                ->where('user_id', $user->getKey())
                ->where('successful', true)
                ->count()
        );
    }

    public function test_user_can_login_after_temporary_lock_expires(): void
    {
        $user = UserFactory::new()->createOne([
            'usuario' => 'expired-lock',
            'password' => 'CorrectPassword123!',
            'failed_login_attempts' => 3,
            'locked_until' => now()->addMinutes(15),
        ]);

        $this->travel(16)->minutes();

        $this->postJson('/api/auth/login', [
            'usuario' => 'expired-lock',
            'password' => 'CorrectPassword123!',
        ])->assertOk();

        $this->assertSame(
            0,
            DB::table('users')
                ->where('id', $user->getKey())
                ->value('failed_login_attempts')
        );

        $this->assertNull(
            DB::table('users')
                ->where('id', $user->getKey())
                ->value('locked_until')
        );
    }

    public function test_the_remaining_minutes_of_the_lock_are_reported(): void
    {
        UserFactory::new()->createOne([
            'usuario' => 'minutes-left',
            'password' => 'CorrectPassword123!',
            'failed_login_attempts' => 3,
            'locked_until' => now()->addMinutes(15),
        ]);

        $this->travel(11)->minutes();

        $this->postJson('/api/auth/login', [
            'usuario' => 'minutes-left',
            'password' => 'CorrectPassword123!',
        ])
            ->assertStatus(423)
            ->assertJsonPath('minutos', 4);
    }

    public function test_unknown_identifier_is_recorded_without_user_reference(): void
    {
        $this->postJson('/api/auth/login', [
            'usuario' => 'unknown-security',
            'password' => 'IncorrectPassword123!',
        ])->assertUnauthorized();

        $attempt = DB::table('login_attempts')
            ->where(
                'identifier',
                'unknown-security'
            );

        $this->assertTrue($attempt->exists());
        $this->assertNull($attempt->value('user_id'));
        $this->assertFalse(
            (bool) $attempt->value('successful')
        );
    }

    public function test_inactive_user_attempt_does_not_increment_lock_counter(): void
    {
        $user = UserFactory::new()->createOne([
            'usuario' => 'inactive-security',
            'password' => 'CorrectPassword123!',
            'is_active' => false,
        ]);

        $this->postJson('/api/auth/login', [
            'usuario' => 'inactive-security',
            'password' => 'CorrectPassword123!',
        ])
            ->assertStatus(423)
            ->assertJsonMissingPath('minutos');

        $this->assertSame(
            0,
            DB::table('users')
                ->where('id', $user->getKey())
                ->value('failed_login_attempts')
        );

        $this->assertSame(
            1,
            DB::table('login_attempts')
                ->where('user_id', $user->getKey())
                ->where('successful', false)
                ->count()
        );
    }
}
