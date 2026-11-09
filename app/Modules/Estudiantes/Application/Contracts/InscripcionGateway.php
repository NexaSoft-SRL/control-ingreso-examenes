<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Contracts;

use App\Modules\Estudiantes\Application\DTOs\AltaDeInscritoData;
use App\Modules\Estudiantes\Application\DTOs\BajaDeInscritoData;
use App\Modules\Estudiantes\Application\DTOs\FiltroListaData;
use App\Modules\Estudiantes\Application\DTOs\GrupoDeCargaData;
use App\Modules\Estudiantes\Application\DTOs\PaginaData;
use App\Modules\Estudiantes\Application\DTOs\RegistroCargaData;

/**
 * Las inscripciones y lo que una carga necesita saber de la oferta para
 * ubicar cada fila en su grupo. Una inscripcion dada de baja no cuenta:
 * ninguna lectura la devuelve.
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
     * Las carreras de la facultad, por codigo y por nombre (en la forma
     * de `DatosDeInscrito::clave()`) => id.
     *
     * @return array<string, int>
     */
    public function carrerasDeFacultad(int $facultadId): array;

    /**
     * Las inscripciones vigentes (no dadas de baja) entre esos estudiantes
     * y esos grupos, como claves `estudianteId|grupoId`.
     *
     * @param  list<int>  $estudianteIds
     * @param  list<int>  $grupoIds
     * @return array<string, true>
     */
    public function existentes(array $estudianteIds, array $grupoIds): array;

    /**
     * Guarda la carga entera en una transaccion (estudiantes nuevos,
     * inscripciones, conflictos, la fila de `cargas_inscritos` y el asiento
     * de bitacora) y devuelve el id de la carga. Una inscripcion que estaba
     * dada de baja se reactiva.
     */
    public function registrar(RegistroCargaData $carga): int;

    /**
     * Guarda en una transaccion al estudiante agregado suelto: lo crea si
     * es nuevo, lo inscribe (o reactiva su inscripcion dada de baja) o deja
     * el conflicto, y asienta la bitacora. Devuelve true si reactivo una
     * inscripcion dada de baja.
     */
    public function agregar(AltaDeInscritoData $alta): bool;

    /**
     * Si el estudiante ya registro su ingreso en algun examen del grupo.
     */
    public function tieneIngresoEnGrupo(int $estudianteId, int $grupoId): bool;

    /**
     * Da de baja la inscripcion sin borrarla (quien, cuando y por que),
     * retira la habilitacion y el codigo QR de los examenes del grupo en
     * los que el estudiante ya no queda inscrito por otro grupo y asienta
     * la bitacora, en una transaccion.
     */
    public function darDeBaja(BajaDeInscritoData $baja): void;

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
