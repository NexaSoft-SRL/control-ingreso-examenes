<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

interface VerificacionPuntoControlGateway
{
    /**
     * @return array{estado: 'ok'|'no_valido'|'finalizado'|'aula_denegada', acceso?: object{id: int, examen_id: int, aula_id: int}}
     */
    public function verificarAccesoPorToken(string $token, int $routeExamen, int $routeAula): array;

    public function tienePermisoPuntoControl(int $usuarioId): bool;

    public function rolDelUsuario(int $usuarioId): string;
}
