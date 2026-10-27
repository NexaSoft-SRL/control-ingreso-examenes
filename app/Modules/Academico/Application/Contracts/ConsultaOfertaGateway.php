<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Contracts;

use App\Modules\Academico\Application\DTOs\PaginaData;

/**
 * Lecturas de la oferta academica para la pantalla Periodo del
 * administrador y para «Mis grupos» del docente. En todos los metodos
 * `$periodoIds` vacio significa «sin filtrar por periodo».
 */
interface ConsultaOfertaGateway
{
    /**
     * @return list<array{id: int, clave: string, sigla: string, nombre: string, color: string, edificios: int, aulas: int}>
     */
    public function facultades(): array;

    /**
     * @return array{id: int, clave: string, sigla: string, nombre: string}|null
     */
    public function facultadPorClave(string $clave): ?array;

    /**
     * Vigentes primero; luego por anio y numero descendente.
     *
     * @return array{filas: list<array<string, mixed>>, total: int}
     */
    public function periodos(PaginaData $pagina): array;

    /**
     * La fila de un periodo, con la misma forma que en `periodos()`.
     *
     * @return array<string, mixed>|null
     */
    public function periodo(int $periodoId): ?array;

    /**
     * @param  list<int>  $periodoIds
     * @return array{docentes: int, docentes_sin_cuenta: int, grupos: int, grupos_sin_lista: int}
     */
    public function pendientes(array $periodoIds): array;

    /**
     * Una fila por facultad con sus cifras y el estado de su ultima
     * importacion (`sin` si nunca se importo).
     *
     * @param  list<int>  $periodoIds
     * @return list<array{sigla: string, nombre: string, carreras: int, grupos: int, aulas: int, importacion: array{estado: string, fecha: string|null, error: string|null}}>
     */
    public function ofertaPorFacultad(array $periodoIds): array;

    /**
     * Hay una importacion de esa facultad que empezo hace menos de
     * `$minutos` y no termino.
     */
    public function importacionEnCurso(string $claveFacultad, int $minutos): bool;

    /**
     * @return list<array{id: int, codigo: string, nombre: string, regimen: string}>
     */
    public function carreras(?string $claveFacultad): array;

    /**
     * Asignaturas de la oferta con sus grupos e inscritos, filtradas por
     * periodo, facultad, carrera, nivel y búsqueda: una fila por asignatura y
     * facultad. Sin periodo, los vigentes (ninguno si no hay vigente).
     *
     * @param  array{periodo_ids: list<int>, facultad: string|null, carrera_id: int|null, nivel: string|null, buscar: string|null, sin_lista?: bool}  $filtros
     * @return array{filas: list<array<string, mixed>>, total: int, total_grupos: int}
     */
    public function asignaturasDeOferta(array $filtros, PaginaData $pagina): array;

    /**
     * Los grupos de una asignatura con su docente, horarios, aula e inscritos.
     * Con `$buscar`, los mismos que cuenta la fila de la asignatura (si solo
     * coincide un docente, solo los suyos), con los del docente primero.
     *
     * @param  list<int>  $periodoIds
     * @return array{filas: list<array<string, mixed>>, total: int}
     */
    public function gruposDeAsignatura(
        int $asignaturaId,
        array $periodoIds,
        ?string $buscar,
        ?string $facultadClave,
        PaginaData $pagina,
    ): array;

    /**
     * @param  list<int>  $periodoIds
     * @return list<array{id: int, codigo: string, asignatura: array{id: int, codigo: string, nombre: string}, nivel: string|null, facultad: string, periodo: string, horarios: list<array{dia: string, hora: string, aula: string|null}>, inscritos: int, con_lista: bool}>
     */
    public function gruposDeDocente(int $docenteId, array $periodoIds): array;

    public function codigoDePeriodo(int $periodoId): ?string;

    /**
     * El dia de hoy para el sistema, `AAAA-MM-DD`.
     */
    public function hoy(): string;

    /**
     * Los niveles (semestre o año) presentes en la oferta filtrada, en el
     * orden del plan de estudios, no alfabetico.
     *
     * @param  array{periodo_ids: list<int>, facultad: string|null, carrera_id: int|null, nivel: string|null, buscar: string|null, sin_lista?: bool}  $filtros
     * @return list<string>
     */
    public function nivelesDeOferta(array $filtros): array;

    /**
     * Si la asignatura existe en el catalogo (para distinguir «sin grupos»
     * de «no existe»).
     */
    public function existeAsignatura(int $asignaturaId): bool;
}
