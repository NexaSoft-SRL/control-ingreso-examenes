<?php

namespace App\Modules\Administracion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\AuthenticationSecurityGateway;
use App\Modules\Administracion\Domain\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;

final class EloquentAuthenticationSecurityGateway implements AuthenticationSecurityGateway
{
    private static ?string $dummyPasswordHash = null;

    public function authenticate(
        string $identifier,
        string $password,
        ?string $ipAddress,
        ?string $userAgent,
    ): ?User {
        $maxFailedAttempts = $this->positiveIntegerConfig(
            'auth_security.max_failed_attempts',
            5,
        );

        $lockoutMinutes = $this->positiveIntegerConfig(
            'auth_security.lockout_minutes',
            15,
        );

        $dummyPasswordHash = $this->dummyPasswordHash();

        /** @var User|null $authenticatedUser */
        $authenticatedUser = DB::transaction(
            function () use (
                $identifier,
                $password,
                $ipAddress,
                $userAgent,
                $maxFailedAttempts,
                $lockoutMinutes,
                $dummyPasswordHash,
            ): ?User {
                $user = User::query()
                    ->where('correo', $identifier)
                    ->lockForUpdate()
                    ->first();

                if (! $user instanceof User) {
                    Hash::check(
                        $password,
                        $dummyPasswordHash
                    );

                    $this->recordAttempt(
                        null,
                        $identifier,
                        false,
                        $ipAddress,
                        $userAgent,
                    );

                    return null;
                }

                $userId = $this->userId($user);

                $passwordHash = $user->getAttribute('password');

                if (! is_string($passwordHash)) {
                    throw new LogicException(
                        'La contraseña del usuario no tiene un formato válido.'
                    );
                }

                $passwordIsValid = Hash::check(
                    $password,
                    $passwordHash
                );

                $now = now();

                if (isset($user->is_active) && ! $user->is_active) {
                    $this->recordAttempt(
                        $userId,
                        $identifier,
                        false,
                        $ipAddress,
                        $userAgent,
                    );

                    return null;
                }

                $lockedUntil = $user->getAttribute('locked_until');

                if (
                    $lockedUntil !== null
                    && ! $lockedUntil instanceof DateTimeInterface
                ) {
                    throw new LogicException(
                        'locked_until no tiene el tipo de fecha esperado.'
                    );
                }

                if (
                    $lockedUntil instanceof DateTimeInterface
                    && $lockedUntil->getTimestamp() > $now->getTimestamp()
                ) {
                    $this->recordAttempt(
                        $userId,
                        $identifier,
                        false,
                        $ipAddress,
                        $userAgent,
                    );

                    return null;
                }

                if ($lockedUntil instanceof DateTimeInterface) {
                    $user->forceFill([
                        'failed_login_attempts' => 0,
                        'locked_until' => null,
                    ]);
                }

                if (! $passwordIsValid) {
                    $failedLoginAttempts = $user->getAttribute('failed_login_attempts');

                    if (! is_int($failedLoginAttempts)) {
                        throw new LogicException(
                            'El contador de intentos fallidos no tiene un formato válido.'
                        );
                    }

                    $failedAttempts = $failedLoginAttempts + 1;

                    $attributes = [
                        'failed_login_attempts' => $failedAttempts,
                    ];

                    if ($failedAttempts >= $maxFailedAttempts) {
                        $attributes['locked_until'] = $now
                            ->copy()
                            ->addMinutes($lockoutMinutes);
                    }

                    $user->forceFill($attributes)->save();

                    $this->recordAttempt(
                        $userId,
                        $identifier,
                        false,
                        $ipAddress,
                        $userAgent,
                    );

                    return null;
                }

                $attributes = [
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                    'last_login_at' => $now,
                ];

                $user->forceFill($attributes)->save();

                $this->recordAttempt(
                    $userId,
                    $identifier,
                    true,
                    $ipAddress,
                    $userAgent,
                );

                return $user;
            },
            3
        );

        return $authenticatedUser;
    }

    private function dummyPasswordHash(): string
    {
        if (self::$dummyPasswordHash === null) {
            self::$dummyPasswordHash = Hash::make(
                'authentication-dummy-password'
            );
        }

        return self::$dummyPasswordHash;
    }

    private function positiveIntegerConfig(
        string $key,
        int $default,
    ): int {
        $value = config($key, $default);

        if (! is_int($value)) {
            return $default;
        }

        return max(1, $value);
    }

    private function userId(User $user): int
    {
        $userId = $user->getKey();

        if (! is_int($userId)) {
            throw new LogicException(
                'El identificador de usuario no tiene el tipo esperado.'
            );
        }

        return $userId;
    }

    private function recordAttempt(
        ?int $userId,
        string $identifier,
        bool $successful,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        DB::table('intentos_login')->insert([
            'usuario_id' => $userId,
            'fecha_intento' => now(),
            'exitoso' => $successful,
            'ip_origen' => $ipAddress,
        ]);
    }
}
