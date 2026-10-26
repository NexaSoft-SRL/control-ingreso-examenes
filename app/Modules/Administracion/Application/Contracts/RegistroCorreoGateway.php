<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

use App\Modules\Administracion\Application\DTOs\RegistroCorreoData;

/**
 * Registro de los correos que envia el sistema (`envios_correo`): se
 * anota el correo como pendiente antes de enviarlo y se cierra con su
 * resultado. Lo usa `Ingreso` para los codigos QR y los avisos.
 */
interface RegistroCorreoGateway
{
    /**
     * Anota el correo como PENDIENTE y devuelve su identificador.
     */
    public function registrar(RegistroCorreoData $datos): int;

    /**
     * Lo deja ENVIADO, con la hora del servidor.
     */
    public function marcarEnviado(int $envioId): void;

    /**
     * Lo deja FALLIDO con el motivo, salvo que ya haya salido.
     */
    public function marcarFallido(int $envioId, string $error): void;
}
