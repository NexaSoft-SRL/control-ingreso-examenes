<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Ingreso\Application\Contracts\AccesoAulaGateway;
use App\Modules\Ingreso\Application\DTOs\AccesoAulaData;
use App\Modules\Ingreso\Domain\Exceptions\CodigoDeAulaInvalidoException;

/**
 * Entra a la puerta de un aula con su codigo de seis digitos, sin cuenta.
 * Vale solo mientras el examen esta abierto.
 */
final readonly class AbrirAccesoConCodigo
{
    public function __construct(
        private AccesoAulaGateway $accesos,
    ) {}

    public function execute(string $codigo, string $nombre, ?string $ip): AccesoAulaData
    {
        $acceso = $this->accesos->abrir($codigo, trim($nombre), $ip);

        if ($acceso === null) {
            // Un codigo de verdad fuera de hora dice cuando vale.
            $ventana = $this->accesos->ventanaDelCodigo($codigo);

            if ($ventana === null) {
                throw new CodigoDeAulaInvalidoException;
            }

            throw $ventana['cerrada']
                ? CodigoDeAulaInvalidoException::yaTermino($ventana['cierra'])
                : CodigoDeAulaInvalidoException::todaviaNo($ventana['abre']);
        }

        return $acceso;
    }
}
