<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Actions;

use App\Modules\Estudiantes\Application\Contracts\RegistroPendienteGateway;
use App\Modules\Estudiantes\Domain\Exceptions\CodigoDeAccesoInvalidoException;

/**
 * Quien aun no esta en el padron prueba que recibe el correo de su codigo
 * universitario: con el codigo correcto puede empadronarse. Cinco intentos.
 */
final readonly class ConfirmarCorreoDeRegistro
{
    public function __construct(
        private RegistroPendienteGateway $pendientes,
    ) {}

    /**
     * @throws CodigoDeAccesoInvalidoException
     */
    public function execute(string $codigoUniversitario, string $codigo): void
    {
        if (! $this->pendientes->consumirCodigo(trim($codigoUniversitario), hash('sha256', $codigo), EntrarComoEstudiante::INTENTOS)) {
            throw new CodigoDeAccesoInvalidoException;
        }
    }
}
