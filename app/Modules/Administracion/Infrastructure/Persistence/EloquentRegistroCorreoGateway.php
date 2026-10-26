<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\RegistroCorreoGateway;
use App\Modules\Administracion\Application\DTOs\RegistroCorreoData;
use App\Modules\Administracion\Domain\Enums\EstadoEnvio;
use App\Modules\Administracion\Domain\Enums\TipoCorreo;
use App\Modules\Administracion\Domain\Models\EnvioCorreo;
use InvalidArgumentException;
use RuntimeException;

final class EloquentRegistroCorreoGateway implements RegistroCorreoGateway
{
    public function registrar(RegistroCorreoData $datos): int
    {
        $tipo = TipoCorreo::tryFrom($datos->tipo);

        if ($tipo === null) {
            throw new InvalidArgumentException("Tipo de correo desconocido: {$datos->tipo}.");
        }

        $envio = EnvioCorreo::query()->create([
            'tipo' => $tipo,
            'destinatario' => mb_substr($datos->destinatario, 0, 150),
            'examen_id' => $datos->examenId,
            'estudiante_id' => $datos->estudianteId,
            'estado' => EstadoEnvio::Pendiente,
            'solicitado_por' => $datos->solicitadoPor,
        ]);

        $id = $envio->getKey();

        if (! is_int($id)) {
            throw new RuntimeException('El registro del correo no devolvió un identificador.');
        }

        return $id;
    }

    public function marcarEnviado(int $envioId): void
    {
        EnvioCorreo::query()->whereKey($envioId)->update([
            'estado' => EstadoEnvio::Enviado->value,
            'error' => null,
            'enviado_en' => now(),
        ]);
    }

    public function marcarFallido(int $envioId, string $error): void
    {
        EnvioCorreo::query()
            ->whereKey($envioId)
            ->where('estado', '!=', EstadoEnvio::Enviado->value)
            ->update([
                'estado' => EstadoEnvio::Fallido->value,
                'error' => mb_substr($error, 0, 2000),
            ]);
    }
}
