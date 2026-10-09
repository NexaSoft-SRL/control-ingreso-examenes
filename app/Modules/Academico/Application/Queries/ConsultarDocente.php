<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Queries;

use App\Modules\Academico\Application\Contracts\ConsultaDocentesGateway;
use App\Modules\Administracion\Application\Contracts\ProponedorUsuario;

/**
 * El detalle de un docente: sus materias por facultad, su cuenta y el
 * usuario que se le propone.
 */
final readonly class ConsultarDocente
{
    public function __construct(
        private ConsultaDocentesGateway $docentes,
        private ResolverPeriodos $resolverPeriodos,
        private ProponedorUsuario $proponedor,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function execute(int $docenteId): ?array
    {
        $docente = $this->docentes->detalle(
            $docenteId,
            $this->resolverPeriodos->execute(null),
        );

        if ($docente === null) {
            return null;
        }

        // Con cuenta, no hay nada que proponer: se muestra su usuario.
        $docente['usuario_sugerido'] = $docente['cuenta'] === null
            ? $this->proponedor->proponer($docente['nombre'])
            : $docente['cuenta']['usuario'];

        return $docente;
    }
}
