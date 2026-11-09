<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Ingreso\Application\Contracts\PuertaGateway;
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

    public function __construct(private PuertaGateway $puerta) {}

    /**
     * El id del estudiante si el texto es su codigo; nulo si es un nombre
     * o esta vacio.
     *
     * @throws DatoNoValidoException
     */
    public function resolver(int $examenId, string $texto): ?int
    {
        if ($texto === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $texto) === 1) {
            $id = $this->puerta->estudiantePorIdentificador($texto)['estudiante']->id ?? null;

            if ($id === null || $this->puerta->examenesDondeEstaInscrito($id, [$examenId]) === []) {
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
