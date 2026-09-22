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
    ): void {
        DB::table('bitacora_operaciones')->insert([
            'usuario_id' => $usuarioId,
            'operacion' => $operacion,
            'tabla_afectada' => $tablaAfectada,
            'registro_id' => $registroId,
            'descripcion' => $descripcion,
            'fecha_operacion' => now(),
        ]);
    }
}
