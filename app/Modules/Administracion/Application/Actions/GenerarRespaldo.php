<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Application\Contracts\GeneradorRespaldoGateway;
use App\Modules\Administracion\Application\DTOs\RespaldoData;
use App\Modules\Administracion\Domain\Exceptions\RespaldoFallidoException;
use Throwable;

/**
 * HU-25: Acción para generar respaldos a demanda (puntual) o programados (cron),
 * registrando la operación en la bitácora del sistema.
 */
final readonly class GenerarRespaldo
{
    public function __construct(
        private GeneradorRespaldoGateway $gateway,
        private BitacoraGateway $bitacora,
    ) {}

    public function execute(string $tipo = 'puntual', ?int $usuarioId = null): RespaldoData
    {
        try {
            $respaldo = $this->gateway->generar($tipo, $usuarioId);

            $this->bitacora->registrar(
                $usuarioId,
                'respaldo.generar',
                'respaldos',
                $respaldo->id,
                sprintf('Respaldo %s generado (%s).', $respaldo->archivo, $respaldo->tipo),
            );

            return $respaldo;
        } catch (RespaldoFallidoException $error) {
            $this->bitacora->registrar(
                $usuarioId,
                'respaldo.fallido',
                'respaldos',
                $error->respaldoId,
                sprintf('Fallo al generar respaldo (%s): %s', $tipo, $error->getMessage()),
            );

            throw $error;
        } catch (Throwable $error) {
            $this->bitacora->registrar(
                $usuarioId,
                'respaldo.fallido',
                'respaldos',
                null,
                sprintf('Fallo al generar respaldo (%s): %s', $tipo, $error->getMessage()),
            );

            throw $error;
        }
    }
}
