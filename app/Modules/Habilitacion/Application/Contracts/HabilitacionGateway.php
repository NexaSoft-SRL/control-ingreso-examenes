<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Application\Contracts;

use App\Modules\Habilitacion\Application\DTOs\CifrasHabilitacionData;
use App\Modules\Habilitacion\Application\DTOs\FiltroHabilitacionData;
use App\Modules\Habilitacion\Application\DTOs\GrupoDeExamenData;
use App\Modules\Habilitacion\Application\DTOs\HabilitacionData;
use App\Modules\Habilitacion\Application\DTOs\InscritoData;

/**
 * La condicion de los inscritos frente a un examen. La lista del examen
 * son los inscritos de sus grupos; «sin revisar» es no tener condicion
 * registrada.
 */
interface HabilitacionGateway
{
    /**
     * La condicion registrada de un estudiante en un examen, o `null` si
     * esta sin revisar. Es el contrato que usa la puerta (modulo Ingreso).
     * No comprueba la inscripcion: eso lo sabe quien pregunta.
     */
    public function condicionDe(int $examenId, int $estudianteId): ?HabilitacionData;

    public function existeExamen(int $examenId): bool;

    /**
     * Una pagina de la lista, por apellidos y nombres. Quien esta inscrito
     * en dos grupos del examen sale una vez, con el grupo de menor codigo.
     *
     * @return list<InscritoData>
     */
    public function listar(
        int $examenId,
        FiltroHabilitacionData $filtros,
        int $pagina,
        int $porPagina,
    ): array;

    /**
     * Cuantos hay de cada condicion con esos filtros (el de condicion se
     * ignora).
     *
     * @return array{todos: int, habilitado: int, no: int, pendiente: int}
     */
    public function condiciones(int $examenId, FiltroHabilitacionData $filtros): array;

    public function cifras(int $examenId): CifrasHabilitacionData;

    /**
     * @return list<GrupoDeExamenData>
     */
    public function gruposDe(int $examenId, int $usuarioId): array;

    /**
     * Todos los estudiantes que dejan pasar los filtros, no solo una pagina.
     *
     * @return list<int>
     */
    public function estudiantesFiltrados(int $examenId, FiltroHabilitacionData $filtros): array;

    /**
     * Cuantos de esos estudiantes no estan inscritos en ningun grupo del
     * examen (o no existen).
     *
     * @param  list<int>  $estudianteIds
     */
    public function cuantosNoInscritos(int $examenId, array $estudianteIds): int;

    /**
     * Cuantos de esos estudiantes ya ingresaron al examen.
     *
     * @param  list<int>  $estudianteIds
     */
    public function cuantosConIngreso(int $examenId, array $estudianteIds): int;

    /**
     * Habilita: `habilitado = true`, sin motivo, y no toca el aula. Solo
     * escribe a quienes no estaban habilitados. Devuelve cuantos cambiaron.
     *
     * @param  list<int>  $estudianteIds
     */
    public function habilitar(int $examenId, array $estudianteIds, int $usuarioId): int;

    /**
     * Inhabilita: `habilitado = false`, con el motivo y sin aula. Devuelve
     * cuantos quedaron inhabilitados con ese motivo.
     *
     * @param  list<int>  $estudianteIds
     */
    public function inhabilitar(int $examenId, array $estudianteIds, string $motivo, int $usuarioId): int;
}
