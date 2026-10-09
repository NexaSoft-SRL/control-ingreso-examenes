<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Queries;

use App\Modules\Academico\Application\Contracts\AlcanceDocenteGateway;
use App\Modules\Academico\Application\Contracts\ConsultaOfertaGateway;
use App\Modules\Academico\Application\Contracts\PeriodoGateway;

/**
 * «Mis grupos»: los grupos que dicta el docente, con sus inscritos.
 */
final readonly class ListarGruposDelDocente
{
    public function __construct(
        private ConsultaOfertaGateway $oferta,
        private AlcanceDocenteGateway $alcance,
        private PeriodoGateway $periodos,
        private ResolverPeriodos $resolverPeriodos,
    ) {}

    /**
     * Los grupos del docente unido a la cuenta. Una cuenta que no es de
     * un docente no tiene grupos.
     *
     * @return array{data: list<array<string, mixed>>, meta: array{periodo: string|null, asignaturas: int, sin_lista: int}}
     */
    public function execute(int $usuarioId, ?int $periodoId): array
    {
        $docenteId = $this->alcance->docenteDeUsuario($usuarioId);

        $grupos = $docenteId === null
            ? []
            : $this->oferta->gruposDeDocente(
                $docenteId,
                $this->resolverPeriodos->execute($periodoId),
            );

        $asignaturas = [];
        $sinLista = 0;

        foreach ($grupos as $grupo) {
            $asignaturas[$grupo['asignatura']['id']] = true;

            if (! $grupo['con_lista']) {
                $sinLista++;
            }
        }

        return [
            'data' => $grupos,
            'meta' => [
                'periodo' => $periodoId === null
                    ? $this->periodos->principal()?->codigo
                    : $this->oferta->codigoDePeriodo($periodoId),
                'asignaturas' => count($asignaturas),
                'sin_lista' => $sinLista,
            ],
        ];
    }
}
