<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Ingreso\Application\Contracts\IncidenciaGateway;
use App\Modules\Ingreso\Domain\Exceptions\DatoNoValidoException;

/**
 * El estudiante que nombra una incidencia: un codigo SIS o C.I. de alguien
 * inscrito en el examen, o un nombre escrito a mano cuando no se lo pudo
 * identificar.
 */
final readonly class EstudianteDeIncidencia
{
    // Palabras de letras separadas por un espacio, un punto, un apostrofo
    // o un guion, sin dos signos seguidos y con al menos dos letras.
    private const NOMBRE = "/^(?=(?:.*\\p{L}){2})\\p{L}+(?:(?:\\.\\s*|\\s+|['-])\\p{L}+)*\\.?$/u";

    // Codigo SIS, o C.I. con su complemento («5003748-1A»).
    private const IDENTIFICADOR = '/^\d{5,10}([\s-][0-9A-Za-z]{1,3}|[A-Za-z][0-9A-Za-z]{0,2})?$/';

    public function __construct(private IncidenciaGateway $incidencias) {}

    /**
     * El id del estudiante si el texto es su codigo o C.I.; nulo si es un
     * nombre o esta vacio.
     *
     * @throws DatoNoValidoException
     */
    public function resolver(int $examenId, string $texto): ?int
    {
        if ($texto === '') {
            return null;
        }

        if (preg_match('/^\d/', $texto) === 1) {
            $id = preg_match(self::IDENTIFICADOR, $texto) === 1
                ? $this->incidencias->estudianteDelExamen($examenId, $texto)
                : null;

            if ($id === null) {
                throw new DatoNoValidoException('estudiante', 'No inscrito en el examen.');
            }

            return $id;
        }

        if (preg_match(self::NOMBRE, $texto) !== 1) {
            throw new DatoNoValidoException('estudiante', 'Un código SIS o solo letras.');
        }

        return null;
    }
}
