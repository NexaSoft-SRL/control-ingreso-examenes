<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Application\Contracts\RestauradorRespaldoGateway;
use App\Modules\Administracion\Application\DTOs\RespaldoData;
use App\Modules\Administracion\Domain\Exceptions\RespaldoIncompatibleException;
use Throwable;

/**
 * HU-25: Acción para restaurar la base de datos a partir de un respaldo,
 * registrando la operación y los intentos incompatibles en la bitácora.
 */
final readonly class RestaurarRespaldo
{
    public function __construct(
        private RestauradorRespaldoGateway $gateway,
        private BitacoraGateway $bitacora,
    ) {}

    public function execute(int $respaldoId, ?int $usuarioId = null): RespaldoData
    {
        try {
            $respaldo = $this->gateway->restaurar($respaldoId);

            $this->bitacora->registrar(
                $usuarioId,
                'respaldo.restaurar',
                'respaldos',
                $respaldo->id,
                sprintf('Base de datos restaurada al respaldo %s.', $respaldo->archivo),
            );

            return $respaldo;
        } catch (RespaldoIncompatibleException $error) {
            $this->bitacora->registrar(
                $usuarioId,
                'respaldo.incompatible',
                'respaldos',
                $error->respaldoId ?? $respaldoId,
                sprintf('Intento de restauración con versión incompatible: %s', $error->getMessage()),
            );

            throw $error;
        } catch (Throwable $error) {
            $this->bitacora->registrar(
                $usuarioId,
                'respaldo.restaurar_fallido',
                'respaldos',
                $respaldoId,
                sprintf('Fallo al restaurar respaldo: %s', $error->getMessage()),
            );

            throw $error;
        }
    }
}
