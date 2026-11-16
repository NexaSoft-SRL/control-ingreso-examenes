<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Contracts;

/**
 * El codigo de acceso de quien aun no esta en el padron. No hay fila de
 * estudiante donde guardarlo: vive unos minutos y, si coincide, deja
 * empadronarse solo a quien controla ese correo.
 */
interface RegistroPendienteGateway
{
    /**
     * Deja vigente un solo codigo: el nuevo anula los anteriores.
     */
    public function guardarCodigo(string $codigoUniversitario, string $hash, int $minutos): void;

    /**
     * Verdadero si el codigo vigente coincide; lo consume. Cada intento
     * fallido cuenta y, pasado el maximo, el codigo deja de valer.
     */
    public function consumirCodigo(string $codigoUniversitario, string $hash, int $maximoIntentos): bool;

    /**
     * Intentos que quedan al codigo vigente; null si no hay uno vigente.
     */
    public function intentosRestantes(string $codigoUniversitario, int $maximoIntentos): ?int;

    /**
     * Si ese codigo era uno vigente que un reenvio reemplazo.
     */
    public function fueReemplazado(string $codigoUniversitario, string $hash): bool;
}
