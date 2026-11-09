<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\CredencialesGateway;
use App\Modules\Administracion\Domain\Models\User;
use Illuminate\Support\Facades\Hash;

final class EloquentCredencialesGateway implements CredencialesGateway
{
    public function contrasenaCoincide(
        int $usuarioId,
        string $contrasena,
    ): bool {
        $usuario = User::find($usuarioId);

        if (! $usuario instanceof User) {
            return false;
        }

        $almacenada = $usuario->getAuthPassword();

        return $almacenada !== '' && Hash::check($contrasena, $almacenada);
    }

    public function cambiarContrasena(
        int $usuarioId,
        string $nuevaContrasena,
    ): void {
        $usuario = User::find($usuarioId);

        if (! $usuario instanceof User) {
            return;
        }

        // El modelo cifra la contrasena mediante su cast 'hashed'.
        $usuario->forceFill([
            'password' => $nuevaContrasena,
            'password_changed_at' => now(),
            'password_temporal_expira_en' => null,
        ])->save();
    }

    public function debeCambiarContrasena(int $usuarioId): bool
    {
        $usuario = User::find($usuarioId);

        if (! $usuario instanceof User) {
            return false;
        }

        return $usuario->getAttribute('password_changed_at') === null;
    }
}
