<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

use App\Modules\Ingreso\Domain\Enums\CausaIntento;
use App\Modules\Ingreso\Domain\Enums\VeredictoIngreso;

interface IncidenciaIngresoGateway
{
    public function registrar(
        int $examenId,
        int $aulaId,
        ?int $estudianteId,
        int $usuarioId,
        VeredictoIngreso $veredicto,
        string $detalle,
    ): void;

    /**
     * Intento sin examen resuelto (no inscrito, sin registro, QR no
     * valido). El examen queda nulo: no se adjudica a uno del aula.
     */
    public function registrarIntento(
        int $aulaId,
        ?int $estudianteId,
        int $usuarioId,
        CausaIntento $causa,
        string $detalle,
    ): void;

    /**
     * Si el mismo intento (aula, estudiante, tipo y detalle) ya quedo
     * registrado en los ultimos minutos: leer otra vez a la misma persona
     * no suma intentos.
     */
    public function repetidoReciente(
        int $aulaId,
        ?int $estudianteId,
        string $tipo,
        string $detalle,
        int $minutos,
    ): bool;
}
