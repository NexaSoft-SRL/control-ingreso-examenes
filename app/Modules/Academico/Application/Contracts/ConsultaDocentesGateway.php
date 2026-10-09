<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Contracts;

use App\Modules\Academico\Application\DTOs\FiltroDocentesData;
use App\Modules\Academico\Application\DTOs\PaginaData;

/**
 * Lecturas de docentes. Un docente es una fila aunque dicte en varias
 * facultades; sus facultades se derivan de sus grupos en `$periodoIds`
 * (vacio = todos los periodos).
 */
interface ConsultaDocentesGateway
{
    /**
     * @param  list<int>  $periodoIds
     * @return array{filas: list<array{id: int, nombre: string, facultades: list<array{sigla: string, grupos: int}>, cuenta: string}>, total: int}
     */
    public function listar(FiltroDocentesData $filtro, array $periodoIds, PaginaData $pagina): array;

    /**
     * `todas`, una clave por sigla de facultad, `sin_cuenta` y
     * `varias_facultades`. No dependen de los filtros de la pantalla.
     *
     * @param  list<int>  $periodoIds
     * @return array<string, int>
     */
    public function conteos(array $periodoIds): array;

    /**
     * @param  list<int>  $periodoIds
     * @return array{id: int, nombre: string, grupos: int, facultades: list<array{sigla: string, grupos: int, materias: list<string>}>, cuenta: array{estado: string, usuario: string, correo: string|null}|null}|null
     */
    public function detalle(int $docenteId, array $periodoIds): ?array;
}
