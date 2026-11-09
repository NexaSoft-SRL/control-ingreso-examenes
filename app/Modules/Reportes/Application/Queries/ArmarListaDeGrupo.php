<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\Queries;

use App\Modules\Academico\Application\Contracts\AlcanceDocenteGateway;
use App\Modules\Reportes\Application\Contracts\ReporteGateway;
use App\Modules\Reportes\Application\DTOs\FilaEstudianteGrupo;
use App\Modules\Reportes\Application\DTOs\TablaReporte;
use App\Modules\Reportes\Domain\Exceptions\RecursoAjenoException;
use App\Modules\Reportes\Domain\Rules\FechaLegible;

/**
 * «Reporte de mis estudiantes»: la lista de inscritos de un grupo del
 * docente, lista para la hoja de calculo.
 */
final readonly class ArmarListaDeGrupo
{
    public function __construct(
        private ReporteGateway $gateway,
        private AlcanceDocenteGateway $alcance,
        /** El correo institucional no se guarda: es el codigo universitario con `config('umss.dominio_correo_estudiantes')`. */
        private string $dominioCorreo,
    ) {}

    /**
     * Devuelve null si el grupo no existe.
     *
     * @throws RecursoAjenoException si el grupo no es del docente
     */
    public function execute(int $grupoId, int $usuarioId): ?TablaReporte
    {
        $grupo = $this->gateway->grupo($grupoId);

        if ($grupo === null) {
            return null;
        }

        if (! $this->alcance->esGrupoDelDocente($usuarioId, $grupoId)) {
            throw new RecursoAjenoException('Este grupo no está a su cargo.');
        }

        $estudiantes = $this->gateway->estudiantesDeGrupo($grupoId);

        return new TablaReporte(
            clave: sprintf(
                'estudiantes_%s_g%s',
                ArmarTabla::paraArchivo($grupo->asignaturaCodigo),
                ArmarTabla::paraArchivo($grupo->codigo),
            ),
            titulo: 'Estudiantes del grupo',
            filtros: [
                sprintf('Asignatura: %s · %s', $grupo->asignaturaCodigo, $grupo->asignatura),
                sprintf('Grupo: %s', $grupo->codigo),
                sprintf('Período: %s', $grupo->periodo),
            ],
            encabezados: ['Código SIS', 'Estudiante', 'C.I.', 'Correo institucional', 'Origen'],
            filas: array_map(
                fn (FilaEstudianteGrupo $fila): array => [
                    $fila->codigoUniversitario,
                    $fila->nombre,
                    $fila->documento ?? '',
                    $fila->codigoUniversitario.'@'.$this->dominioCorreo,
                    $fila->verificado ? 'Administración' : 'Docente',
                ],
                $estudiantes,
            ),
            totales: [sprintf('%d estudiantes inscritos.', count($estudiantes))],
            emitidoEn: FechaLegible::instante(now()->format('Y-m-d H:i:s')),
        );
    }
}
