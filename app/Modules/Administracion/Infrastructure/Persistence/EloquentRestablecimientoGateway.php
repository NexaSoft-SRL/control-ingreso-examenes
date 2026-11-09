<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\RestablecimientoGateway;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Administracion\Domain\Rules\NombreDeUsuario;
use App\Modules\Administracion\Infrastructure\Mail\EnlaceRestablecimientoMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * El enlace se pide por usuario y viaja al correo de contacto de la
 * cuenta. `password_reset_tokens` conserva su clave por correo: una cuenta
 * sin correo no puede recibir enlace y la restablece la administracion.
 */
final class EloquentRestablecimientoGateway implements RestablecimientoGateway
{
    /**
     * Vigencia del enlace, en minutos.
     */
    private const VIGENCIA = 60;

    public function solicitar(string $usuario): ?int
    {
        $cuenta = $this->cuentaConCorreo($usuario);

        if (! $cuenta instanceof User) {
            return null;
        }

        $correo = (string) $cuenta->email;
        $token = Str::random(64);

        // Se guarda cifrado: quien lea la tabla no puede usar el enlace.
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $correo],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ],
        );

        Mail::to($correo)->send(new EnlaceRestablecimientoMail(
            $cuenta->name,
            url('/restablecer-contrasena?token='.$token.'&usuario='.urlencode($cuenta->usuario)),
            self::VIGENCIA,
        ));

        $id = $cuenta->getKey();

        return is_int($id) ? $id : null;
    }

    public function restablecer(
        string $usuario,
        string $token,
        string $contrasenaNueva,
    ): ?int {
        $cuenta = $this->cuentaConCorreo($usuario);

        if (! $cuenta instanceof User) {
            return null;
        }

        $correo = (string) $cuenta->email;

        $registro = DB::table('password_reset_tokens')
            ->where('email', $correo)
            ->first();

        if ($registro === null) {
            return null;
        }

        /** @var array<string, mixed> $datos */
        $datos = get_object_vars($registro);

        $tokenAlmacenado = $datos['token'];
        $creado = $datos['created_at'];

        if (! is_string($tokenAlmacenado) || ! Hash::check($token, $tokenAlmacenado)) {
            return null;
        }

        if (! is_string($creado) || now()->diffInMinutes($creado, true) > self::VIGENCIA) {
            return null;
        }

        $cuenta->forceFill([
            'password' => $contrasenaNueva,
            'password_changed_at' => now(),
            'password_temporal_expira_en' => null,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        // Un solo uso: el token se elimina apenas sirve.
        DB::table('password_reset_tokens')
            ->where('email', $correo)
            ->delete();

        $id = $cuenta->getKey();

        return is_int($id) ? $id : null;
    }

    private function cuentaConCorreo(string $usuario): ?User
    {
        $cuenta = User::query()
            ->whereRaw("replace(usuario, ' ', '') = ?", [NombreDeUsuario::compacto($usuario)])
            ->where('is_active', true)
            ->whereNotNull('email')
            ->first();

        return $cuenta instanceof User ? $cuenta : null;
    }
}
