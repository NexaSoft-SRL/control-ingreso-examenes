<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Queries;

use App\Modules\Administracion\Application\Contracts\RespaldoGateway;
use App\Modules\Administracion\Application\DTOs\ArchivoRespaldoData;
use App\Modules\Administracion\Domain\Exceptions\RespaldoFallidoException;
use App\Modules\Administracion\Domain\Exceptions\RespaldoNoEncontradoException;

/**
 * El guion SQL de un respaldo, para guardarlo fuera del servidor o
 * importarlo a mano desde el panel del alojamiento.
 */
final readonly class DescargarRespaldo
{
    public function __construct(
        private RespaldoGateway $respaldos,
    ) {}

    /**
     * @throws RespaldoNoEncontradoException
     * @throws RespaldoFallidoException
     */
    public function execute(int $respaldoId): ArchivoRespaldoData
    {
        $respaldo = $this->respaldos->buscar($respaldoId);

        if ($respaldo === null) {
            throw new RespaldoNoEncontradoException('Respaldo no encontrado.');
        }

        if (! $respaldo->esCorrecto() || $respaldo->archivo === null) {
            throw new RespaldoFallidoException('Ese respaldo falló: no hay archivo que descargar.');
        }

        $contenido = $this->respaldos->contenido($respaldoId);

        if ($contenido === null) {
            throw new RespaldoNoEncontradoException('El archivo de ese respaldo ya no está en disco.');
        }

        return new ArchivoRespaldoData($respaldo->archivo, $contenido);
    }
}
