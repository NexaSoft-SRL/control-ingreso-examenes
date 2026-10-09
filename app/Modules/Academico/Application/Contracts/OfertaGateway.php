<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Contracts;

/**
 * Escritura de la oferta. Todo se guarda por su clave natural: volver a
 * guardar lo mismo no duplica.
 */
interface OfertaGateway
{
    /**
     * Por codigo; la que llega sin codigo se busca por nombre en la
     * facultad y, si es nueva, recibe `SIN-<SIGLA>-<n>`.
     *
     * @param  list<array{codigo: string|null, nombre: string, anual: bool}>  $carreras
     * @return list<int> el id de cada carrera, en el mismo orden
     */
    public function guardarCarreras(int $facultadId, string $sigla, array $carreras): array;

    /**
     * @param  array<string, string>  $asignaturas  codigo => nombre
     * @return array<string, int> codigo => id
     */
    public function guardarAsignaturas(array $asignaturas): array;

    /**
     * @param  list<array{carrera_id: int, asignatura_id: int, nivel: string}>  $plan
     */
    public function guardarPlan(array $plan): void;

    /**
     * Crea los docentes que falten. Nunca toca la cuenta de uno existente.
     *
     * @param  array<string, string>  $docentes  nombre normalizado => nombre completo
     * @return array<string, int> nombre normalizado => id
     */
    public function guardarDocentes(array $docentes): array;

    /**
     * Crea, sin edificio, las aulas que falten (las ubicadas ya existen).
     *
     * @param  list<string>  $nombres
     * @return array<string, int> nombre => id
     */
    public function guardarAulas(int $facultadId, array $nombres): array;

    /**
     * @param  list<array{periodo_id: int, asignatura_id: int, codigo: string, docente_id: int|null}>  $grupos
     * @return list<int> el id de cada grupo, en el mismo orden
     */
    public function guardarGrupos(int $facultadId, array $grupos): array;

    /**
     * Borra los horarios de esos grupos y deja los que se indican.
     *
     * @param  list<int>  $grupoIds
     * @param  list<array{grupo_id: int, aula_id: int|null, dia: string, hora_inicio: string, hora_fin: string, es_auxiliatura: bool}>  $horarios
     */
    public function reemplazarHorarios(array $grupoIds, array $horarios): void;

    /**
     * Borra los grupos de la facultad en esos periodos que ya no vienen en
     * el archivo, salvo los que tienen inscripciones o examenes. Devuelve
     * cuantos borro.
     *
     * @param  list<int>  $periodoIds
     * @param  list<int>  $presentes
     */
    public function eliminarGruposAusentes(int $facultadId, array $periodoIds, array $presentes): int;
}
