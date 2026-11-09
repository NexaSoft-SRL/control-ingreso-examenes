<?php

namespace App\Modules\Administracion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\AuthenticationSecurityGateway;
use App\Modules\Administracion\Application\Contracts\RolGateway;
use App\Modules\Administracion\Application\DTOs\ResultadoAccesoData;
use App\Modules\Administracion\Application\DTOs\SesionData;
use App\Modules\Administracion\Domain\Models\Role;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Administracion\Domain\Rules\NombreDeUsuario;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;

final class EloquentAuthenticationSecurityGateway implements AuthenticationSecurityGateway
{
    private static ?string $dummyPasswordHash = null;

    public function __construct(
        private readonly RolGateway $roles,
    ) {}

    public function authenticate(
        string $identifier,
        string $password,
        ?string $ipAddress,
        ?string $userAgent,
    ): ResultadoAccesoData {
        // El usuario se guarda y se compara en minusculas.
        $identifier = mb_strtolower(trim($identifier));

        $maxFailedAttempts = $this->positiveIntegerConfig(
            'auth_security.max_failed_attempts',
            5,
        );

        $lockoutMinutes = $this->positiveIntegerConfig(
            'auth_security.lockout_minutes',
            15,
        );

        $dummyPasswordHash = $this->dummyPasswordHash();

        return DB::transaction(
            function () use (
                $identifier,
                $password,
                $ipAddress,
                $userAgent,
                $maxFailedAttempts,
                $lockoutMinutes,
                $dummyPasswordHash,
            ): ResultadoAccesoData {
                $user = User::query()
                    // «josesoruco» encuentra a «jose soruco».
                    ->whereRaw("replace(usuario, ' ', '') = ?", [NombreDeUsuario::compacto($identifier)])
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

                    return new ResultadoAccesoData(
                        ResultadoAccesoData::CREDENCIALES,
                    );
                }

                $userId = $this->userId($user);

                $passwordIsValid = Hash::check(
                    $password,
                    (string) $user->password
                );

                $now = now();

                if (! $user->is_active) {
                    $this->recordAttempt(
                        $userId,
                        $identifier,
                        false,
                        $ipAddress,
                        $userAgent,
                    );

                    return new ResultadoAccesoData(
                        ResultadoAccesoData::INACTIVA,
                        $userId,
                    );
                }

                $lockedUntil = $this->fecha($user, 'locked_until');

                if (
                    $lockedUntil !== null
                    && $lockedUntil->getTimestamp() > $now->getTimestamp()
                ) {
                    $this->recordAttempt(
                        $userId,
                        $identifier,
                        false,
                        $ipAddress,
                        $userAgent,
                    );

                    $segundos = $lockedUntil->getTimestamp() - $now->getTimestamp();

                    return new ResultadoAccesoData(
                        ResultadoAccesoData::BLOQUEADA,
                        $userId,
                        minutosBloqueo: max(1, (int) ceil($segundos / 60)),
                    );
                }

                if ($lockedUntil !== null) {
                    $user->forceFill([
                        'failed_login_attempts' => 0,
                        'locked_until' => null,
                    ]);
                }

                if (! $passwordIsValid) {
                    $failedAttempts =
                        $user->failed_login_attempts + 1;

                    $attributes = [
                        'failed_login_attempts' => $failedAttempts,
                    ];

                    $locks = $failedAttempts >= $maxFailedAttempts;

                    if ($locks) {
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

                    if ($locks) {
                        return new ResultadoAccesoData(
                            ResultadoAccesoData::BLOQUEADA,
                            $userId,
                            minutosBloqueo: $lockoutMinutes,
                        );
                    }

                    return new ResultadoAccesoData(
                        ResultadoAccesoData::CREDENCIALES,
                        $userId,
                        intentosRestantes: $maxFailedAttempts - $failedAttempts,
                    );
                }

                // La contrasena es la correcta, pero es la temporal y ya
                // vencio: no entra hasta que la administracion emita otra.
                // No cuenta como intento fallido para el bloqueo.
                $expira = $this->fecha($user, 'password_temporal_expira_en');

                if (
                    $user->getAttribute('password_changed_at') === null
                    && $expira !== null
                    && $expira->getTimestamp() <= $now->getTimestamp()
                ) {
                    $this->recordAttempt(
                        $userId,
                        $identifier,
                        false,
                        $ipAddress,
                        $userAgent,
                    );

                    return new ResultadoAccesoData(
                        ResultadoAccesoData::TEMPORAL_VENCIDA,
                        $userId,
                    );
                }

                $attributes = [
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                    'last_login_at' => $now,
                ];

                if (Hash::needsRehash((string) $user->password)) {
                    $attributes['password'] = $password;
                }

                $user->forceFill($attributes)->save();

                $this->recordAttempt(
                    $userId,
                    $identifier,
                    true,
                    $ipAddress,
                    $userAgent,
                );

                return new ResultadoAccesoData(
                    ResultadoAccesoData::CORRECTO,
                    $userId,
                );
            },
            3
        );
    }

    public function sesionDe(int $usuarioId): ?SesionData
    {
        $user = User::with('role')->find($usuarioId);

        if (! $user instanceof User || ! $user->is_active) {
            return null;
        }

        $rol = $user->getRelationValue('role');
        $correo = $user->getAttribute('email');

        // El docente es de otro modulo: se lee por su tabla, sin su modelo.
        $docenteId = DB::table('docentes')
            ->where('user_id', $usuarioId)
            ->value('id');

        return new SesionData(
            id: $usuarioId,
            nombre: $user->name,
            usuario: $user->usuario,
            correo: is_string($correo) && $correo !== '' ? $correo : null,
            rol: $rol instanceof Role ? $rol->name : null,
            permisos: $this->roles->permisosVigentesDe($usuarioId),
            debeCambiarContrasena: $user->getAttribute('password_changed_at') === null,
            docenteId: is_int($docenteId) ? $docenteId : null,
        );
    }

    private function fecha(User $user, string $atributo): ?DateTimeInterface
    {
        $valor = $user->getAttribute($atributo);

        if ($valor !== null && ! $valor instanceof DateTimeInterface) {
            throw new LogicException(
                "{$atributo} no tiene el tipo de fecha esperado."
            );
        }

        return $valor;
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
        DB::table('login_attempts')->insert([
            'user_id' => $userId,
            'identifier' => $identifier,
            'successful' => $successful,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'attempted_at' => now(),
        ]);
    }
}
