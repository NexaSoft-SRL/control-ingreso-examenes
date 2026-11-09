<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\Actions;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Application\Contracts\CredencialesGateway;
use App\Modules\Administracion\Domain\Exceptions\ContrasenaNoAceptadaException;

final readonly class CambiarContrasena
{
    public function __construct(
        private CredencialesGateway $credenciales,
        private BitacoraGateway $bitacora,
    ) {}

    /**
     * Con contrasena temporal no se pide la actual: la sesion abierta ya
     * prueba que la conoce. En cualquier otro caso es obligatoria.
     *
     * @throws ContrasenaNoAceptadaException si la actual no coincide o la
     *                                       nueva repite la que ya tiene
     */
    public function execute(
        int $usuarioId,
        ?string $contrasenaActual,
        string $contrasenaNueva,
    ): void {
        $esTemporal = $this->credenciales->debeCambiarContrasena($usuarioId);

        if (! $esTemporal && (
            $contrasenaActual === null
            || $contrasenaActual === ''
            || ! $this->credenciales->contrasenaCoincide($usuarioId, $contrasenaActual)
        )) {
            throw ContrasenaNoAceptadaException::actualIncorrecta();
        }

        if ($this->credenciales->contrasenaCoincide($usuarioId, $contrasenaNueva)) {
            throw ContrasenaNoAceptadaException::repetida();
        }

        $this->credenciales->cambiarContrasena($usuarioId, $contrasenaNueva);

        $this->bitacora->registrar(
            $usuarioId,
            'usuario.cambiar_contrasena',
            'users',
            $usuarioId,
            null,
        );
    }
}
