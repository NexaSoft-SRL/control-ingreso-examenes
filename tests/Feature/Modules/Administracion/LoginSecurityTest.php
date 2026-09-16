<?php

namespace Tests\Feature\Modules\Administracion;

use Database\Factories\UserFactory;
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

    public function test_failed_logins_increment_counter_and_lock_at_threshold(): void
    {
        $user = UserFactory::new()->createOne([
            'email' => 'lock-test@example.invalid',
            'password' => 'CorrectPassword123!',
            'is_active' => true,
        ]);

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $this->postJson('/api/auth/login', [
                'email' => 'lock-test@example.invalid',
                'password' => 'IncorrectPassword123!',
            ])->assertUnauthorized();
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

        $this->postJson('/api/auth/login', [
            'email' => 'lock-test@example.invalid',
            'password' => 'IncorrectPassword123!',
        ])->assertUnauthorized();

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
            'email' => 'locked-user@example.invalid',
            'password' => 'CorrectPassword123!',
            'failed_login_attempts' => 3,
            'locked_until' => now()->addMinutes(15),
        ]);

        $lockedUntil = DB::table('users')
            ->where('id', $user->getKey())
            ->value('locked_until');

        $this->assertNotNull($lockedUntil);

        $this->postJson('/api/auth/login', [
            'email' => 'locked-user@example.invalid',
            'password' => 'CorrectPassword123!',
        ])
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Credenciales incorrectas.',
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
            'email' => 'reset-counter@example.invalid',
            'password' => 'CorrectPassword123!',
        ]);

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $this->postJson('/api/auth/login', [
                'email' => 'reset-counter@example.invalid',
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
            'email' => 'reset-counter@example.invalid',
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
            'email' => 'expired-lock@example.invalid',
            'password' => 'CorrectPassword123!',
            'failed_login_attempts' => 3,
            'locked_until' => now()->addMinutes(15),
        ]);

        $this->travel(16)->minutes();

        $this->postJson('/api/auth/login', [
            'email' => 'expired-lock@example.invalid',
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

    public function test_unknown_identifier_is_recorded_without_user_reference(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'unknown-security@example.invalid',
            'password' => 'IncorrectPassword123!',
        ])->assertUnauthorized();

        $attempt = DB::table('login_attempts')
            ->where(
                'identifier',
                'unknown-security@example.invalid'
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
            'email' => 'inactive-security@example.invalid',
            'password' => 'CorrectPassword123!',
            'is_active' => false,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'inactive-security@example.invalid',
            'password' => 'CorrectPassword123!',
        ])->assertUnauthorized();

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
