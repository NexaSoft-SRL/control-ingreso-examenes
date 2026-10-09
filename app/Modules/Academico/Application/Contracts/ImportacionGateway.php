<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Contracts;

use App\Modules\Academico\Application\DTOs\ResumenImportacionData;
use Closure;

interface ImportacionGateway
{
    /**
     * La facultad por su clave; null si no esta en el catalogo.
     *
     * @return array{id: int, clave: string, sigla: string, codigo_umss: string}|null
     */
    public function facultad(string $clave): ?array;

    /**
     * Hay una importacion de la facultad en estado `IMPORTANDO` iniciada
     * hace menos de `$minutos`.
     */
    public function enCurso(int $facultadId, int $minutos = 5): bool;

    /**
     * Abre la fila de la importacion en `IMPORTANDO`. Queda confirmada de
     * inmediato, fuera de la transaccion de la importacion.
     */
    public function iniciar(int $facultadId, ?int $usuarioId): int;

    /**
     * Corre la importacion entera en una transaccion: si algo falla no
     * queda nada a medias.
     *
     * @template T
     *
     * @param  Closure(): T  $operacion
     * @return T
     */
    public function enTransaccion(Closure $operacion): mixed;

    /**
     * Cierra la fila como `IMPORTADA`, con su resumen, y asienta
     * `oferta.importar` en la bitacora. Se llama dentro de la transaccion.
     */
    public function completar(int $importacionId, ResumenImportacionData $resumen, ?int $usuarioId): void;

    /**
     * Cierra la fila como `FALLO` con el mensaje. Se llama fuera de la
     * transaccion, ya revertida.
     */
    public function fallar(int $importacionId, string $error): void;
}
