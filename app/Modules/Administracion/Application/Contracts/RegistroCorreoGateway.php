<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Contracts;

use App\Modules\Administracion\Application\DTOs\RegistroCorreoData;
use DateTimeImmutable;

/**
 * Registro de los correos que envia el sistema (`envios_correo`). Lo usan
 * este modulo (credenciales) e `Ingreso` (codigos QR): se anota el correo
 * como pendiente antes de enviarlo y se cierra con su resultado.
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
     * Lo deja FALLIDO con el motivo.
     */
    public function marcarFallido(int $envioId, string $error): void;

    /**
     * Marca como fallidos los envios ya dados por enviados a esa direccion
     * entre `$desde` y `$hasta` (el correo rebotó). Devuelve cuantos marco.
     */
    public function marcarRebotados(
        string $destinatario,
        DateTimeImmutable $desde,
        DateTimeImmutable $hasta,
        string $error,
    ): int;
}
