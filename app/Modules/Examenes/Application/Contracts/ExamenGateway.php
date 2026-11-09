<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Contracts;

use App\Modules\Examenes\Application\DTOs\ExamenActualData;
use App\Modules\Examenes\Application\DTOs\GuardarExamenData;

/**
 * Escritura del examen con sus grupos, sus aulas, quien las atiende y sus
 * normas marcadas.
 */
interface ExamenGateway
{
    public function existe(int $examenId): bool;

    public function tieneIngresos(int $examenId): bool;

    /**
     * Incidencias que nombran al examen: son historia y no se borran.
     */
    public function tieneIncidencias(int $examenId): bool;

    public function actual(int $examenId): ?ExamenActualData;

    /**
     * Guarda el examen completo y asienta `examen.registrar` (y
     * `examen.asignar_auxiliar` si alguien mas atiende un aula). De cada
     * plantilla marcada guarda una copia del texto.
     * `$datos->periodoId` tiene que venir resuelto.
     */
    public function registrar(GuardarExamenData $datos, int $usuarioId): int;

    /**
     * Reemplaza los datos, los grupos, las aulas, el personal y las normas
     * marcadas (la que ya estaba conserva el texto con que se guardo). Quitar un
     * aula deja sin aula a sus habilitados; quitar un grupo borra las
     * habilitaciones y los codigos QR de quienes ya no estan inscritos en
     * ningun grupo del examen. Asienta `examen.modificar` y, si cambia el
     * personal, `examen.asignar_auxiliar`.
     */
    public function actualizar(int $examenId, GuardarExamenData $datos, int $usuarioId): bool;

    public function eliminar(int $examenId, int $usuarioId): bool;

    /**
     * Cierra el examen si esta abierto (ya abrio su puerta, sigue en su dia y
     * nadie lo finalizo) y asienta `examen.finalizar`. Devuelve el instante
     * de cierre, o null si no estaba abierto.
     */
    public function finalizar(int $examenId, int $usuarioId): ?string;
}
