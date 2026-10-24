<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\CredencialesGateway;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Administracion\Infrastructure\Mail\EnlaceRestablecimientoMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

final class EloquentCredencialesGateway implements CredencialesGateway
{
    public function cambiar(int $usuarioId, string $contrasenaActual, string $nuevaContrasena): bool
    {
        return DB::transaction(function () use ($usuarioId, $contrasenaActual, $nuevaContrasena): bool {
            $usuario = User::query()->whereKey($usuarioId)->lockForUpdate()->first();

            if (! $usuario instanceof User || ! Hash::check($contrasenaActual, $usuario->getAuthPassword())) {
                return false;
            }

            $usuario->forceFill([
                'password' => Hash::make($nuevaContrasena),
                'password_changed_at' => now(),
                'password_temporal_expira_en' => null,
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ])->save();

            return true;
        }, 3);
    }

    public function solicitarRestablecimiento(string $correo): void
    {
        $correoNormalizado = mb_strtolower(trim($correo));
        $usuario = User::query()
            ->whereRaw('lower(correo) = ?', [$correoNormalizado])
            ->where('is_active', true)
            ->first();

        if (! $usuario instanceof User) {
            return;
        }

        $table = DB::table('password_reset_tokens');
        /** @var object{email: string, token: string, created_at: string|\DateTimeInterface|null}|null $existente */
        $existente = (clone $table)->where('email', $correoNormalizado)->first();
        $cfgThrottle = config('auth.passwords.users.throttle', 60);
        $throttle = is_numeric($cfgThrottle) ? (int) $cfgThrottle : 60;

        if (
            $existente !== null
            && $existente->created_at !== null
            && now()->diffInSeconds($existente->created_at) < $throttle
        ) {
            return;
        }

        $token = Str::random(64);
        $cfgExpire = config('auth.passwords.users.expire', 60);
        $expire = is_numeric($cfgExpire) ? (int) $cfgExpire : 60;
        $enlace = URL::to('/restablecer-contrasena?'.http_build_query([
            'correo' => $correoNormalizado,
            'token' => $token,
        ]));

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $correoNormalizado],
            ['token' => Hash::make($token), 'created_at' => now()],
        );

        Mail::to($correoNormalizado)->send(new EnlaceRestablecimientoMail(
            nombre: $usuario->name,
            enlace: $enlace,
            minutosVigencia: $expire,
        ));
    }

    public function restablecer(string $correo, string $token, string $contrasena): bool
    {
        $correoNormalizado = mb_strtolower(trim($correo));

        return DB::transaction(function () use ($correoNormalizado, $token, $contrasena): bool {
            /** @var object{email: string, token: string, created_at: string|\DateTimeInterface|null}|null $fila */
            $fila = DB::table('password_reset_tokens')
                ->where('email', $correoNormalizado)
                ->lockForUpdate()
                ->first();

            $cfgExpire = config('auth.passwords.users.expire', 60);
            $expire = is_numeric($cfgExpire) ? (int) $cfgExpire : 60;

            if (
                $fila === null
                || $fila->created_at === null
                || ! Hash::check($token, $fila->token)
            ) {
                return false;
            }

            $expiraEn = Carbon::parse($fila->created_at)->addMinutes($expire);

            if (now()->greaterThanOrEqualTo($expiraEn)) {
                return false;
            }

            $usuario = User::query()
                ->whereRaw('lower(correo) = ?', [$correoNormalizado])
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $usuario instanceof User) {
                return false;
            }

            $usuario->forceFill([
                'password' => Hash::make($contrasena),
                'password_changed_at' => now(),
                'password_temporal_expira_en' => null,
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ])->save();

            DB::table('password_reset_tokens')->where('email', $correoNormalizado)->delete();
            $usuarioId = $usuario->getKey();
            $sesionUserId = is_scalar($usuarioId) ? (string) $usuarioId : '';
            DB::table('sessions')->where('user_id', $sesionUserId)->delete();

            return true;
        }, 3);
    }
}
