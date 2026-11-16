<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

use App\Modules\Ingreso\Application\DTOs\OperadorData;
use App\Modules\Ingreso\Domain\Enums\TipoIrregularidad;

/**
 * Lo que la puerta guarda para trabajar sin conexion y revisar despues: la
 * instantanea que se descarga, las sesiones, los eventos y las
 * irregularidades.
 */
interface SinConexionGateway
{
    /**
     * Los inscritos de esos examenes con su condicion y quien ya ingreso, y
     * el hash (SHA-256) de cada codigo QR emitido. Nunca el carnet, la foto
     * ni el QR en claro.
     *
     * @param  list<int>  $examenIds
     * @return array{
     *     estudiantes: list<array{id: int, codigo: string, nombre: string, examenes: list<array<string, mixed>>}>,
     *     claves: array<string, array{examen_id: int, estudiante_id: int}>
     * }
     */
    public function instantanea(array $examenIds): array;

    /**
     * @param  list<int>  $examenIds
     */
    public function abrirSesion(
        string $dispositivo,
        int $aulaId,
        OperadorData $operador,
        string $instantaneaEn,
        array $examenIds,
    ): int;

    public function sesion(string $dispositivo, int $aulaId, string $instantaneaEn): ?int;

    public function eventoAplicado(string $uid): bool;

    public function guardarEvento(
        int $sesionId,
        string $uid,
        string $hora,
        string $medio,
        string $resultado,
        ?int $examenId,
        ?int $estudianteId,
        ?int $ingresoId,
    ): void;

    public function cerrarSesion(int $sesionId, int $eventos, string $ahora): void;

    /**
     * @param  array<string, mixed>|null  $datos
     */
    public function registrarIrregularidad(
        int $examenId,
        ?int $sesionId,
        TipoIrregularidad $tipo,
        string $detalle,
        ?int $estudianteId,
        ?int $aulaId,
        ?array $datos,
    ): int;

    /**
     * Las irregularidades del examen, las pendientes primero y por gravedad.
     *
     * @return list<array<string, mixed>>
     */
    public function irregularidades(int $examenId): array;

    /**
     * Las sesiones que cubrian el examen, con su aula y su estado.
     *
     * @return list<array<string, mixed>>
     */
    public function sesionesDelExamen(int $examenId): array;

    public function examenDeIrregularidad(int $irregularidadId): ?int;

    /**
     * Resuelve una pendiente; falso si ya estaba resuelta o no existe.
     */
    public function resolver(int $irregularidadId, int $usuarioId, string $estado, string $nota): bool;
}
