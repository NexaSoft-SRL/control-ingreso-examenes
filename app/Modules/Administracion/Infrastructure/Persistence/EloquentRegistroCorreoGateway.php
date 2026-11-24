<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\RegistroCorreoGateway;
use App\Modules\Administracion\Application\DTOs\RegistroCorreoData;
use App\Modules\Administracion\Domain\Enums\EstadoEnvio;
use App\Modules\Administracion\Domain\Enums\TipoCorreo;
use App\Modules\Administracion\Domain\Models\EnvioCorreo;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
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
            'user_id' => $datos->usuarioId,
            'examen_id' => $datos->examenId,
            'estudiante_id' => $datos->estudianteId,
            'estado' => EstadoEnvio::Pendiente,
            'solicitado_por' => $datos->solicitadoPor,
        ]);

        $id = $envio->getKey();

        if (! is_int($id)) {
            throw new RuntimeException('El registro del correo no devolvio un identificador.');
        }

        return $id;
    }

    public function marcarEnviado(int $envioId): void
    {
        EnvioCorreo::query()->whereKey($envioId)->update([
            'estado' => EstadoEnvio::Enviado->value,
            'error' => null,
            'enviado_en' => now(),
            'updated_at' => now(),
        ]);
    }

    public function marcarFallido(int $envioId, string $error): void
    {
        EnvioCorreo::query()->whereKey($envioId)->update([
            'estado' => EstadoEnvio::Fallido->value,
            'error' => $error,
            'updated_at' => now(),
        ]);
    }

    /**
     * Las fechas de la tabla estan en la zona de la aplicacion; el aviso del
     * rebote puede venir en otra. Si el envio era de un codigo QR, el codigo
     * deja de contar como enviado, para que el docente lo vea pendiente.
     */
    public function marcarRebotados(
        string $destinatario,
        DateTimeImmutable $desde,
        DateTimeImmutable $hasta,
        string $error,
    ): int {
        $zona = new DateTimeZone(date_default_timezone_get());
        $formato = 'Y-m-d H:i:s';

        $consulta = EnvioCorreo::query()
            ->whereRaw('lower(destinatario) = ?', [mb_strtolower(trim($destinatario))])
            ->where('estado', EstadoEnvio::Enviado->value)
            ->whereBetween('enviado_en', [
                $desde->setTimezone($zona)->format($formato),
                $hasta->setTimezone($zona)->format($formato),
            ]);

        $envios = $consulta->get(['id', 'tipo', 'examen_id', 'estudiante_id']);

        if ($envios->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($envios, $error): int {
            $marcados = EnvioCorreo::query()
                ->whereKey($envios->modelKeys())
                ->update([
                    'estado' => EstadoEnvio::Fallido->value,
                    'error' => $error,
                    'updated_at' => now(),
                ]);

            foreach ($envios as $envio) {
                if ($envio->tipo !== TipoCorreo::CodigoQr || $envio->examen_id === null || $envio->estudiante_id === null) {
                    continue;
                }

                DB::table('codigos_qr')
                    ->where('examen_id', $envio->examen_id)
                    ->where('estudiante_id', $envio->estudiante_id)
                    ->update(['enviado_en' => null, 'updated_at' => now()]);
            }

            return $marcados;
        });
    }
}
