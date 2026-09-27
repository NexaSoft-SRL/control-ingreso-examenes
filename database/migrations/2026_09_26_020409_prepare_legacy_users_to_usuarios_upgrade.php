<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('usuarios')) {
            Schema::create('usuarios', function (Blueprint $table): void {
                $table->id();

                $table->string('name')->nullable();
                $table->string('email')->unique()->nullable();

                $table->string('nombre')->nullable();
                $table->string('correo')->unique()->nullable();

                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');

                $table->boolean('is_active')
                    ->default(true);

                $table->integer('failed_login_attempts')
                    ->default(0);

                $table->timestamp('locked_until')
                    ->nullable();

                $table->timestamp('last_login_at')
                    ->nullable();

                $table->rememberToken();
                $table->timestamps();
            });

            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement(
                    'ALTER TABLE usuarios
                     ADD CONSTRAINT usuarios_failed_login_attempts_non_negative
                     CHECK (failed_login_attempts >= 0)'
                );
            }
        }

        if (! Schema::hasTable('users')) {
            return;
        }

        foreach (
            DB::table('users')
                ->orderBy('id')
                ->get() as $legacyUser
        ) {
            /** @var array<string, mixed> $legacy */
            $legacy = (array) $legacyUser;

            $legacyId = $legacy['id'] ?? null;

            if (! is_int($legacyId) && ! is_string($legacyId)) {
                throw new RuntimeException(
                    'El usuario legacy contiene un id invalido.'
                );
            }

            $existing = DB::table('usuarios')
                ->where('id', $legacyId)
                ->first();

            if ($existing !== null) {
                /** @var array<string, mixed> $current */
                $current = (array) $existing;

                $currentEmail = $current['correo']
                    ?? $current['email']
                    ?? null;

                $legacyEmail = $legacy['email'] ?? null;

                if (
                    $currentEmail !== null
                    && $legacyEmail !== null
                    && $currentEmail !== $legacyEmail
                ) {
                    throw new RuntimeException(
                        sprintf(
                            'Conflicto al migrar users.id=%s: '
                            .'el correo no coincide con usuarios.',
                            (string) $legacyId
                        )
                    );
                }

                continue;
            }

            DB::table('usuarios')->insert([
                'id' => $legacyId,
                'name' => $legacy['name'] ?? null,
                'email' => $legacy['email'] ?? null,
                'nombre' => $legacy['name'] ?? null,
                'correo' => $legacy['email'] ?? null,
                'email_verified_at' => $legacy['email_verified_at'] ?? null,
                'password' => $legacy['password'],
                'is_active' => $legacy['is_active'] ?? true,
                'failed_login_attempts' => $legacy['failed_login_attempts'] ?? 0,
                'locked_until' => $legacy['locked_until'] ?? null,
                'last_login_at' => $legacy['last_login_at'] ?? null,
                'remember_token' => $legacy['remember_token'] ?? null,
                'created_at' => $legacy['created_at'] ?? null,
                'updated_at' => $legacy['updated_at'] ?? null,
            ]);
        }

        if (
            DB::connection()->getDriverName() === 'pgsql'
            && Schema::hasTable('usuarios')
        ) {
            DB::select(
                <<<'SQL'
                SELECT setval(
                    pg_get_serial_sequence('usuarios', 'id'),
                    COALESCE((SELECT MAX(id) FROM usuarios), 1),
                    EXISTS (SELECT 1 FROM usuarios)
                )
                SQL
            );
        }
    }

    public function down(): void
    {
        throw new RuntimeException(
            'La transición legacy users -> usuarios es forward-only '
            .'y no admite rollback automático.'
        );
    }
};
