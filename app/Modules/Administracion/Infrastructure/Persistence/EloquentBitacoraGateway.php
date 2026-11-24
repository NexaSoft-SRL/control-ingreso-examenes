<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use Illuminate\Support\Facades\DB;

final class EloquentBitacoraGateway implements BitacoraGateway
{
    public function registrar(
        ?int $usuarioId,
        string $operacion,
        ?string $tablaAfectada,
        ?int $registroId,
        ?string $descripcion,
        ?string $actor = null,
    ): void {
        // Nombre y rol del momento: los cambios posteriores no reescriben el asiento.
        $autor = $usuarioId === null ? null : DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->where('users.id', $usuarioId)
            ->first(['users.name as nombre', 'roles.name as rol']);
        $rol = data_get($autor, 'rol');
        $nombre = data_get($autor, 'nombre');

        DB::table('bitacora_operaciones')->insert([
            'usuario_id' => $usuarioId,
            'rol' => is_string($rol) ? $rol : null,
            'actor' => $usuarioId === null
                ? ($actor === null ? null : mb_substr($actor, 0, 150))
                : (is_string($nombre)
                    ? mb_substr($nombre, 0, 150)
                    : null),
            'operacion' => $operacion,
            'tabla_afectada' => $tablaAfectada,
            'registro_id' => $registroId,
            'descripcion' => $descripcion,
            'fecha_operacion' => now(),
        ]);
    }
}
