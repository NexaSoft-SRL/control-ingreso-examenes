<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Queries;

use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Ingreso\Application\Authorization\AlcancePuerta;
use App\Modules\Ingreso\Application\Contracts\ExamenesDePuerta;
use App\Modules\Ingreso\Application\DTOs\OperadorPuertaData;
use App\Modules\Ingreso\Domain\Exceptions\AccesoPuntoDenegado;

/**
 * Los examenes de la fecha en el aula cuya puerta la persona puede operar.
 * Sin ninguno, la persona no atiende el aula.
 */
final readonly class ExamenesOperablesEnAula
{
    public function __construct(
        private ExamenesDePuerta $examenesDePuerta,
        private ExamenGateway $examenes,
        private AlcancePuerta $alcance,
    ) {}

    /**
     * @return list<int>
     */
    public function ejecutar(OperadorPuertaData $operador, int $aulaId, string $fecha): array
    {
        $autorizados = [];

        foreach (array_unique($this->examenesDePuerta->examenesDelAula($aulaId, $fecha)) as $examenId) {
            $examen = $this->examenes->actual($examenId);

            if (
                $examen !== null
                && substr($examen->fecha, 0, 10) === $fecha
                && in_array($aulaId, $examen->aulas, true)
                && $this->alcance->puedeOperar($operador, $examenId, $aulaId)
            ) {
                $autorizados[] = $examenId;
            }
        }

        if ($autorizados === []) {
            throw new AccesoPuntoDenegado('Sin asignación en esta aula.');
        }

        return $autorizados;
    }
}
