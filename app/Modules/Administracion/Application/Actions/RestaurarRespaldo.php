<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\RespaldoGateway;
use App\Modules\Administracion\Application\DTOs\RespaldoRestauradoData;
use App\Modules\Administracion\Domain\Exceptions\RespaldoFallidoException;
use App\Modules\Administracion\Domain\Exceptions\RespaldoIncompatibleException;
use App\Modules\Administracion\Domain\Exceptions\RespaldoNoEncontradoException;

/**
 * Restaurar repone los datos del respaldo elegido. Es la operacion mas
 * peligrosa del sistema: se comprueba todo antes de tocar nada.
 */
final readonly class RestaurarRespaldo
{
    public function __construct(
        private RespaldoGateway $respaldos,
    ) {}

    /**
     * @throws RespaldoNoEncontradoException
     * @throws RespaldoFallidoException
     * @throws RespaldoIncompatibleException
     */
    public function execute(int $respaldoId, ?int $usuarioId): RespaldoRestauradoData
    {
        $respaldo = $this->respaldos->buscar($respaldoId);

        if ($respaldo === null) {
            throw new RespaldoNoEncontradoException('Respaldo no encontrado.');
        }

        if (! $respaldo->esCorrecto()) {
            throw new RespaldoFallidoException('Ese respaldo falló: no hay nada que restaurar.');
        }

        $contenido = $this->respaldos->contenido($respaldoId);

        if ($contenido === null) {
            throw new RespaldoNoEncontradoException('El archivo de ese respaldo ya no está en disco.');
        }

        if (! $this->respaldos->esCompatible($contenido)) {
            throw new RespaldoIncompatibleException(
                'Ese respaldo es de una versión anterior del sistema y no se puede restaurar.'
            );
        }

        return new RespaldoRestauradoData(
            tablas: $this->respaldos->restaurar($respaldoId, $usuarioId),
            generadoEn: $respaldo->generadoEn,
        );
    }
}
