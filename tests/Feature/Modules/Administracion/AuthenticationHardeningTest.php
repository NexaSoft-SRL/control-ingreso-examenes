<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Hashing\BcryptHasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AuthenticationHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_plain_password_is_never_persisted_as_plain_text(): void
    {
        $plainPassword = 'SecurePassword123!';

        $user = UserFactory::new()->createOne([
            'email' => 'hashed-password@example.invalid',
            'password' => $plainPassword,
        ]);

        $persistedPassword = DB::table('users')
            ->where('id', $user->getKey())
            ->value('password');

        $this->assertIsString($persistedPassword);

        $this->assertNotSame(
            $plainPassword,
            $persistedPassword
        );

        $this->assertTrue(
            Hash::check(
                $plainPassword,
                $persistedPassword
            )
        );
    }

    public function test_successful_login_rehashes_outdated_password_hash(): void
    {
        $plainPassword = 'RehashPassword123!';

        $hasher = Hash::driver();

        $this->assertInstanceOf(
            BcryptHasher::class,
            $hasher
        );

        // PHPUnit usa BCRYPT_ROUNDS=4 para acelerar los tests.
        // Elevamos solamente este hasher a 5 para que un hash cost=4
        // represente determinísticamente un hash obsoleto.
        $hasher->setRounds(5);

        $outdatedHash = password_hash(
            $plainPassword,
            PASSWORD_BCRYPT,
            [
                'cost' => 4,
            ]
        );

        $this->assertTrue(
            Hash::needsRehash($outdatedHash)
        );

        $user = UserFactory::new()->createOne([
            'email' => 'rehash-user@example.invalid',
            'password' => $outdatedHash,
            'is_active' => true,
        ]);

        $storedBeforeLogin = DB::table('users')
            ->where('id', $user->getKey())
            ->value('password');

        $this->assertIsString($storedBeforeLogin);

        $this->assertSame(
            $outdatedHash,
            $storedBeforeLogin
        );

        $this->postJson('/api/auth/login', [
            'email' => 'rehash-user@example.invalid',
            'password' => $plainPassword,
        ])->assertOk();

        $storedAfterLogin = DB::table('users')
            ->where('id', $user->getKey())
            ->value('password');

        $this->assertIsString($storedAfterLogin);

        $this->assertNotSame(
            $outdatedHash,
            $storedAfterLogin
        );

        $this->assertTrue(
            Hash::check(
                $plainPassword,
                $storedAfterLogin
            )
        );

        $this->assertFalse(
            Hash::needsRehash($storedAfterLogin)
        );
    }

    public function test_failed_login_records_request_security_metadata(): void
    {
        $user = UserFactory::new()->createOne([
            'email' => 'audit-metadata@example.invalid',
            'password' => 'CorrectPassword123!',
            'is_active' => true,
        ]);

        $this->withServerVariables([
            'REMOTE_ADDR' => '192.0.2.55',
        ])
            ->withHeader(
                'User-Agent',
                'HU-01.4 security audit'
            )
            ->postJson('/api/auth/login', [
                'email' => 'audit-metadata@example.invalid',
                'password' => 'IncorrectPassword123!',
            ])
            ->assertUnauthorized();

        $attempt = DB::table('login_attempts')
            ->where('user_id', $user->getKey())
            ->latest('id');

        $this->assertTrue(
            (clone $attempt)->exists()
        );

        $this->assertSame(
            'audit-metadata@example.invalid',
            (clone $attempt)->value('identifier')
        );

        $this->assertFalse(
            (bool) (clone $attempt)->value('successful')
        );

        $this->assertSame(
            '192.0.2.55',
            (clone $attempt)->value('ip_address')
        );

        $this->assertSame(
            'HU-01.4 security audit',
            (clone $attempt)->value('user_agent')
        );

        $this->assertNotNull(
            (clone $attempt)->value('attempted_at')
        );
    }

    public function test_unknown_identifier_performs_password_verification(): void
    {
        Hash::partialMock()
            ->shouldReceive('check')
            ->once()
            ->withArgs(
                static function (
                    mixed $plainValue,
                    mixed $hashedValue,
                ): bool {
                    return $plainValue === 'UnknownPassword123!'
                        && is_string($hashedValue)
                        && $hashedValue !== '';
                }
            )
            ->andReturnFalse();

        $this->postJson('/api/auth/login', [
            'email' => 'timing-unknown@example.invalid',
            'password' => 'UnknownPassword123!',
        ])
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Credenciales incorrectas.',
            ]);
    }
}
