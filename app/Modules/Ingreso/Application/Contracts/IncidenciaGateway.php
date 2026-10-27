<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

use App\Modules\Ingreso\Application\DTOs\NuevaIncidenciaData;
use App\Modules\Ingreso\Domain\Enums\TipoIncidencia;

/**
 * Las incidencias de un examen en un solo modelo: lo que reporta una
 * persona, lo que rechaza la puerta, las expulsiones y las situaciones
 * excepcionales.
 */
interface IncidenciaGateway
{
    /**
     * Asienta la incidencia. Con `$operacion` deja ademas esa fila en la
     * bitacora (las del sistema no la llevan). Devuelve nulo si era una
     * expulsion y ese ingreso ya tenia una.
     */
    public function registrar(NuevaIncidenciaData $datos, ?string $operacion = null): ?int;

    /**
     * Las incidencias del examen (o de unas aulas), de la mas reciente a la
     * mas antigua, con los conteos: las reportadas por personas y los
     * ingresos repetidos que asienta la puerta. Los demas intentos que
     * rechaza la puerta no se listan ni se atienden.
     *
     * @param  list<int>|null  $aulas  nulo: todas las aulas del examen
     * @return array{data: list<array<string, mixed>>, sin_atender: int, todas: int}
     */
    public function listar(int $examenId, ?array $aulas, bool $soloSinAtender): array;

    /**
     * Una incidencia con la forma del listado mas `examen_id`; nulo si no
     * existe.
     *
     * @return array<string, mixed>|null
     */
    public function reportada(int $incidenciaId): ?array;

    /**
     * Falso si ya estaba atendida.
     */
    public function atender(int $incidenciaId, int $usuarioId): bool;

    /**
     * Cambia el tipo y el detalle, la evidencia si viene una nueva y el
     * estudiante si viene `$estudiante`. Devuelve la ruta de la evidencia
     * que se reemplazo.
     *
     * @param  array{texto: string|null, id: int|null}|null  $estudiante
     */
    public function corregir(
        int $incidenciaId,
        TipoIncidencia $tipo,
        string $detalle,
        ?string $evidencia,
        int $usuarioId,
        ?array $estudiante = null,
    ): ?string;

    public function evidencia(int $incidenciaId): ?string;

    /**
     * El estudiante inscrito en el examen con ese codigo SIS o C.I.
     */
    public function estudianteDelExamen(int $examenId, string $identificador): ?int;

    public function examenTieneAula(int $examenId, int $aulaId): bool;

    public function fechaDelExamen(int $examenId): ?string;
}
