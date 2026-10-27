<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

use App\Modules\Ingreso\Domain\Enums\CausaIntento;
use App\Modules\Ingreso\Domain\Enums\VeredictoIngreso;

/**
 * Lo que la puerta asienta sola en `incidencias` (origen SISTEMA): los
 * intentos que rechaza y los ingresos repetidos. Sin cuenta, el autor es
 * el acceso con codigo de aula.
 */
interface IncidenciaIngresoGateway
{
    /**
     * Un rechazo con examen resuelto (no habilitado, expulsado...).
     */
    public function registrar(
        int $examenId,
        int $aulaId,
        ?int $estudianteId,
        ?int $usuarioId,
        VeredictoIngreso $veredicto,
        string $detalle,
        ?int $accesoCodigoAulaId = null,
    ): void;

    /**
     * Intento sin examen resuelto (no inscrito, sin registro, QR no
     * valido). El examen queda nulo: no se adjudica a uno del aula.
     */
    public function registrarIntento(
        int $aulaId,
        ?int $estudianteId,
        ?int $usuarioId,
        CausaIntento $causa,
        string $detalle,
        ?int $accesoCodigoAulaId = null,
    ): void;

    /**
     * Si el mismo intento (aula, estudiante, causa y detalle) ya quedo
     * registrado en los ultimos minutos: leer otra vez a la misma persona
     * no suma intentos.
     */
    public function repetidoReciente(
        int $aulaId,
        ?int $estudianteId,
        string $causa,
        string $detalle,
        int $minutos,
    ): bool;

    /**
     * Una identificacion que ya entro, leida otra vez: queda como
     * incidencia del ingreso.
     */
    public function registrarIngresoRepetido(
        int $examenId,
        int $aulaId,
        int $estudianteId,
        int $ingresoId,
        ?int $usuarioId,
        ?int $accesoCodigoAulaId,
        string $detalle,
    ): void;

    /**
     * Si ese ingreso ya dejo un ingreso repetido en esa aula en los
     * ultimos minutos.
     */
    public function ingresoRepetidoReciente(int $ingresoId, int $aulaId, int $minutos): bool;
}
