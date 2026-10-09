<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Contracts;

use App\Modules\Estudiantes\Application\DTOs\FiltroListaData;
use App\Modules\Estudiantes\Application\DTOs\GrupoDeCargaData;
use App\Modules\Estudiantes\Application\DTOs\PaginaData;
use App\Modules\Estudiantes\Application\DTOs\RegistroCargaData;

/**
 * Las inscripciones y lo que una carga necesita saber de la oferta para
 * ubicar cada fila en su grupo.
 */
interface InscripcionGateway
{
    public function grupo(int $grupoId): ?GrupoDeCargaData;

    /**
     * El id de la facultad con esa clave (`fcyt`); null si no existe.
     */
    public function facultadPorClave(string $clave): ?int;

    /**
     * Los grupos de la facultad en los periodos vigentes, por
     * `CODIGO_ASIGNATURA|GRUPO` en mayusculas. Si el mismo grupo esta en
     * dos periodos vigentes gana el mas reciente.
     *
     * @return array<string, int>
     */
    public function gruposVigentesDeFacultad(int $facultadId): array;

    /**
     * Las carreras de la facultad: codigo => id.
     *
     * @return array<string, int>
     */
    public function carrerasDeFacultad(int $facultadId): array;

    /**
     * Las inscripciones que ya existen entre esos estudiantes y esos
     * grupos, como claves `estudianteId|grupoId`.
     *
     * @param  list<int>  $estudianteIds
     * @param  list<int>  $grupoIds
     * @return array<string, true>
     */
    public function existentes(array $estudianteIds, array $grupoIds): array;

    /**
     * Guarda la carga entera en una transaccion (estudiantes nuevos,
     * inscripciones, conflictos, la fila de `cargas_inscritos` y el asiento
     * de bitacora) y devuelve el id de la carga.
     */
    public function registrar(RegistroCargaData $carga): int;

    /**
     * Los inscritos del grupo, por apellidos y nombres.
     */
    public function inscritosDeGrupo(int $grupoId, FiltroListaData $filtro): PaginaData;

    /**
     * La lista entera del grupo, por apellidos y nombres, para descargar.
     *
     * @return list<array{codigo: string, nombre: string, documento: string, origen: string}>
     */
    public function listaDeGrupo(int $grupoId): array;
}
