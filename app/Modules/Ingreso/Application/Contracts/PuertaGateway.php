<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

use App\Modules\Ingreso\Application\DTOs\AulaData;
use App\Modules\Ingreso\Application\DTOs\EstudianteData;
use App\Modules\Ingreso\Application\DTOs\ExamenData;
use App\Modules\Ingreso\Domain\Enums\MedioIdentificacion;

/**
 * Lo que la puerta lee de otros modulos: aulas, examenes, inscritos.
 */
interface PuertaGateway
{
    /**
     * La hora del servidor, en ISO 8601.
     */
    public function horaServidor(): string;

    /**
     * La fecha del servidor, `AAAA-MM-DD`.
     */
    public function hoy(): string;

    public function aula(int $aulaId): ?AulaData;

    public function examen(int $examenId): ?ExamenData;

    public function examenTieneAula(int $examenId, int $aulaId): bool;

    /**
     * Los examenes de hoy (o abiertos ahora) que tienen esa aula, con sus
     * cifras en ella, por hora de inicio.
     *
     * @return list<ExamenData>
     */
    public function examenesDelDia(int $aulaId): array;

    /**
     * Las aulas de hoy de una cuenta: las de los examenes de los que es
     * docente y las que atiende como encargada o de apoyo.
     *
     * @return list<array{aula: AulaData, examen: ExamenData}>
     */
    public function aulasDelDia(int $usuarioId): array;

    /**
     * Los ingresos por esa aula a esos examenes, del mas reciente al mas
     * antiguo.
     *
     * @param  list<int>  $examenIds
     * @return array{ingresos: list<array<string, mixed>>, total: int}
     */
    public function ingresosDelAula(int $aulaId, array $examenIds, ?int $limite, ?string $buscar): array;

    /**
     * Las claves de las facultades cuyos edificios necesita el mapa de la
     * puerta: la del aula y las de las demas aulas de esos examenes.
     *
     * @param  list<int>  $examenIds
     * @return list<string>
     */
    public function facultadesDelMapa(int $aulaId, array $examenIds): array;

    public function estudiante(int $estudianteId): ?EstudianteData;

    /**
     * Busca por codigo universitario y, si no, por documento de identidad.
     *
     * @return array{estudiante: EstudianteData, medio: MedioIdentificacion}|null
     */
    public function estudiantePorIdentificador(string $identificador): ?array;

    /**
     * De esos examenes, aquellos en cuyos grupos esta inscrito.
     *
     * @param  list<int>  $examenIds
     * @return list<int>
     */
    public function examenesDondeEstaInscrito(int $estudianteId, array $examenIds): array;

    /**
     * Otro examen del estudiante que esta abierto a esta hora, con el aula
     * que le toca en el (nulo si no tiene).
     *
     * @param  list<int>  $excluidos
     * @return array{id: int, texto: string, aula: string|null}|null
     */
    public function examenCorrespondiente(int $estudianteId, array $excluidos): ?array;
}
