<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

interface VerificacionPuntoControlGateway
{
    /**
     * El acceso con codigo de aula de ese token y como esta: `autorizado`
     * (opera la puerta), `pendiente`, `rechazado`, `cortado` o `finalizado`
     * (el examen ya cerro). Nulo si el token no es de ningun acceso.
     *
     * @return array{id: int, examen_id: int, aula_id: int, aula: string, nombre: string, hasta: string, estado: string}|null
     */
    public function accesoDeToken(string $token): ?array;

    /**
     * @param  list<string>  $permisos
     */
    public function tieneAlgunPermiso(int $usuarioId, array $permisos): bool;

    public function tienePermisoPuntoControl(int $usuarioId): bool;

    public function rolDelUsuario(int $usuarioId): string;
}
