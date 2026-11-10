<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

use App\Modules\Ingreso\Application\DTOs\NuevaIncidenciaData;
use App\Modules\Ingreso\Domain\Enums\CausaIntento;
use App\Modules\Ingreso\Domain\Enums\Gravedad;
use App\Modules\Ingreso\Domain\Enums\TipoIncidencia;

interface IncidenciaGateway
{
    /**
     * Asienta la incidencia. Con `$operacion` deja ademas esa fila en la
     * bitacora (las del sistema no la llevan). Devuelve nulo si era una
     * expulsion y ese ingreso ya tenia una.
     */
    public function registrar(NuevaIncidenciaData $datos, ?string $operacion = null): ?int;

    /**
     * Incidencias reportadas por personas (y los ingresos repetidos que
     * asienta la puerta), de la mas reciente a la mas
     * antigua, con los conteos del examen (o del aula).
     *
     * @return array{data: list<array<string, mixed>>, sin_atender: int, todas: int}
     */
    public function listar(int $examenId, ?int $aulaId, bool $soloSinAtender): array;

    /**
     * Una incidencia reportada por una persona, con la forma del listado
     * mas `examen_id`; nulo si no existe o es del sistema.
     *
     * @return array<string, mixed>|null
     */
    public function reportada(int $incidenciaId): ?array;

    /**
     * Falso si ya estaba atendida.
     */
    public function atender(int $incidenciaId, int $usuarioId): bool;

    /**
     * Cambia el tipo y el detalle, agrega las fotos nuevas a la evidencia
     * (no reemplaza ni borra las que habia) y el estudiante si viene
     * `$estudiante` (texto escrito e id del padron, si se encontro). Con
     * `$gravedad` la cambia; `$tipoTexto` es el nombre de una no prevista
     * (null lo deja como esta).
     *
     * @param  list<string>  $evidencias  rutas de las fotos nuevas, ya guardadas
     * @param  array{texto: string|null, id: int|null}|null  $estudiante
     */
    public function corregir(
        int $incidenciaId,
        TipoIncidencia $tipo,
        string $detalle,
        array $evidencias,
        int $usuarioId,
        ?array $estudiante = null,
        ?Gravedad $gravedad = null,
        ?string $tipoTexto = null,
    ): void;

    /**
     * Cuantas fotos de evidencia tiene ya la incidencia.
     */
    public function cuantasEvidencias(int $incidenciaId): int;

    /**
     * Si en los ultimos `$minutos` ya se asento un ingreso repetido de ese
     * ingreso en esa aula.
     */
    public function repetidoReciente(int $ingresoId, int $aulaId, int $minutos): bool;

    /**
     * Si en los ultimos `$minutos` la puerta ya asento un intento de ese
     * estudiante en esa aula por esa causa.
     */
    public function intentoReciente(int $estudianteId, int $aulaId, CausaIntento $causa, int $minutos): bool;

    /**
     * Donde esta la foto numero `$numero` (1, 2 o 3) de la evidencia, o
     * nulo si no la tiene.
     */
    public function evidencia(int $incidenciaId, int $numero = 1): ?string;

    /**
     * Pone el video de evidencia si la incidencia aun no tiene uno.
     */
    public function ponerVideo(int $incidenciaId, string $ruta, string $tipo, ?int $usuarioId): bool;

    /**
     * El video de evidencia (ruta y tipo), o nulo si no tiene.
     *
     * @return array{ruta: string, tipo: string}|null
     */
    public function video(int $incidenciaId): ?array;
}
