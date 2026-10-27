<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

/**
 * Lo que la puerta muestra junto a un veredicto o al abrir un aula: datos
 * legibles (nombres, horas «08:39»), nunca identificadores internos. No
 * concede permisos.
 */
interface DetallePuertaGateway
{
    /**
     * @return array{id: int, nombre: string, edificio: string|null, piso: string|null, edificio_id: int|null}|null
     */
    public function aula(int $aulaId): ?array;

    /**
     * @return array{id: int, asignatura: string, tipo: string, hora: string}|null
     */
    public function examen(int $examenId): ?array;

    /**
     * El ingreso del estudiante en el examen, con su hora y aula, y la
     * hora de la expulsion si la hubo.
     *
     * @return array{id: int, hora: string, registrado_en: string, aula_id: int|null, aula: string|null, expulsado_a: string|null, es_excepcion: bool}|null
     */
    public function ingresoDe(int $examenId, int $estudianteId): ?array;

    /**
     * Otro examen de la fecha en el que el estudiante esta inscrito, para
     * indicarle a donde ir.
     *
     * @param  list<int>  $excluidos
     * @return array{texto: string, aula: string|null}|null
     */
    public function examenCorrespondiente(
        int $estudianteId,
        string $fecha,
        array $excluidos,
    ): ?array;

    /**
     * Los examenes indicados con sus cifras en el aula y sus normas.
     *
     * @param  list<int>  $examenIds
     * @return list<array{
     *     id: int,
     *     asignatura: string,
     *     tipo: string,
     *     fecha: string,
     *     hora: string,
     *     hora_fin: string,
     *     ingresados: int,
     *     asignados: int,
     *     faltan: int,
     *     normas: string|null,
     *     normas_marcadas: list<string>
     * }>
     */
    public function examenesDelAula(int $aulaId, array $examenIds): array;

    /**
     * Los ingresos del aula en esos examenes, del mas reciente al mas
     * antiguo; con `$buscar`, solo los que coinciden en nombre o codigo.
     *
     * @param  list<int>  $examenIds
     * @return list<array{
     *     ingreso_id: int,
     *     examen_id: int,
     *     estudiante_id: int,
     *     codigo: string,
     *     nombres: string,
     *     apellidos: string,
     *     aula_id: int|null,
     *     registrado_en: string,
     *     hora: string,
     *     expulsado: bool,
     *     expulsado_a: string|null,
     *     es_excepcion: bool,
     *     operador: string|null,
     *     tarde: int|null
     * }>
     */
    public function ingresosDelAula(
        int $aulaId,
        array $examenIds,
        ?string $buscar,
        int $limite,
    ): array;

    /**
     * @param  list<int>  $examenIds
     */
    public function totalIngresos(int $aulaId, array $examenIds): int;
}
