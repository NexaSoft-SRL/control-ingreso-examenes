<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\RestauradorRespaldoGateway;

/**
 * HU-25: Acción para localizar y validar el archivo físico de un respaldo para su descarga.
 */
final readonly class DescargarRespaldo
{
    public function __construct(
        private RestauradorRespaldoGateway $gateway,
    ) {}

    /**
     * @return array{nombre: string, contenido: string}
     */
    public function execute(int $respaldoId): array
    {
        return $this->gateway->obtenerArchivo($respaldoId);
    }
}
