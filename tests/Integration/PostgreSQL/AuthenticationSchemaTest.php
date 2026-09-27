<?php

namespace Tests\Integration\PostgreSQL;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthenticationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_security_fields_have_safe_defaults(): void
    {
        $userId = DB::table('usuarios')->insertGetId([
            'name' => 'Auth Schema Test',
            'email' => 'auth-schema@example.invalid',
            'password' => 'not-used-in-schema-test',
        ]);

        $userQuery = DB::table('usuarios')
            ->where('id', $userId);

        $this->assertTrue($userQuery->exists());

        $this->assertTrue(
            (bool) $userQuery->value('is_active')
        );

        $this->assertSame(
            0,
            $userQuery->value('failed_login_attempts')
        );

        $this->assertNull(
            $userQuery->value('locked_until')
        );

        $this->assertNull(
            $userQuery->value('last_login_at')
        );
    }

    public function test_failed_login_attempts_cannot_be_negative(): void
    {
        $this->expectException(QueryException::class);

        DB::table('usuarios')->insert([
            'name' => 'Invalid Counter Test',
            'email' => 'negative-counter@example.invalid',
            'password' => 'not-used-in-schema-test',
            'failed_login_attempts' => -1,
        ]);
    }

    public function test_login_attempt_can_be_recorded_for_unknown_identifier(): void
    {
        $attemptId = DB::table('login_attempts')->insertGetId([
            'user_id' => null,
            'identifier' => 'unknown-user@example.invalid',
            'ip_address' => '192.0.2.10',
            'user_agent' => 'HU-01 integration test',
        ]);

        $attemptQuery = DB::table('login_attempts')
            ->where('id', $attemptId);

        $this->assertTrue($attemptQuery->exists());

        $this->assertNull(
            $attemptQuery->value('user_id')
        );

        $this->assertSame(
            'unknown-user@example.invalid',
            $attemptQuery->value('identifier')
        );

        $this->assertFalse(
            (bool) $attemptQuery->value('successful')
        );

        $this->assertNotNull(
            $attemptQuery->value('attempted_at')
        );
    }

    public function test_login_attempt_accepts_existing_user_reference(): void
    {
        $userId = DB::table('usuarios')->insertGetId([
            'name' => 'Known User Test',
            'email' => 'known-user@example.invalid',
            'password' => 'not-used-in-schema-test',
        ]);

        $attemptId = DB::table('login_attempts')->insertGetId([
            'user_id' => $userId,
            'identifier' => 'known-user@example.invalid',
            'successful' => true,
        ]);

        $attemptQuery = DB::table('login_attempts')
            ->where('id', $attemptId);

        $this->assertTrue($attemptQuery->exists());

        $this->assertSame(
            $userId,
            $attemptQuery->value('user_id')
        );

        $this->assertTrue(
            (bool) $attemptQuery->value('successful')
        );
    }

    public function test_login_attempt_rejects_nonexistent_user_reference(): void
    {
        $this->expectException(QueryException::class);

        DB::table('login_attempts')->insert([
            'user_id' => 999999999,
            'identifier' => 'invalid-reference@example.invalid',
        ]);
    }

    public function test_deleting_user_preserves_login_attempt_and_nulls_reference(): void
    {
        $userId = DB::table('usuarios')->insertGetId([
            'name' => 'Deleted User Test',
            'email' => 'deleted-user@example.invalid',
            'password' => 'not-used-in-schema-test',
        ]);

        $attemptId = DB::table('login_attempts')->insertGetId([
            'user_id' => $userId,
            'identifier' => 'deleted-user@example.invalid',
            'successful' => false,
        ]);

        DB::table('usuarios')
            ->where('id', $userId)
            ->delete();

        $attemptQuery = DB::table('login_attempts')
            ->where('id', $attemptId);

        $this->assertTrue($attemptQuery->exists());

        $this->assertNull(
            $attemptQuery->value('user_id')
        );

        $this->assertSame(
            'deleted-user@example.invalid',
            $attemptQuery->value('identifier')
        );
    }
}
