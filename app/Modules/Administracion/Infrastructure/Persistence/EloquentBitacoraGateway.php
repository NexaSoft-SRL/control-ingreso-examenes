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
        // El rol de ese momento: si despues cambia, el asiento no.
        $rol = $usuarioId === null ? null : DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->where('users.id', $usuarioId)
            ->value('roles.name');

        DB::table('bitacora_operaciones')->insert([
            'usuario_id' => $usuarioId,
            'rol' => is_string($rol) ? $rol : null,
            'actor' => $usuarioId === null && $actor !== null ? mb_substr($actor, 0, 150) : null,
            'operacion' => $operacion,
            'tabla_afectada' => $tablaAfectada,
            'registro_id' => $registroId,
            'descripcion' => $descripcion,
            'fecha_operacion' => now(),
        ]);
    }
}
