<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Queries;

use App\Modules\Academico\Application\Contracts\AlcanceDocenteGateway;
use App\Modules\Estudiantes\Application\Contracts\GeneradorListaDeGrupo;
use App\Modules\Estudiantes\Application\Contracts\InscripcionGateway;
use App\Modules\Estudiantes\Application\DTOs\ArchivoDeListaData;
use App\Modules\Estudiantes\Domain\Exceptions\GrupoAjenoException;
use App\Modules\Estudiantes\Domain\Exceptions\GrupoSinListaException;

/**
 * «Reporte de mis estudiantes»: la lista de inscritos de un grupo del
 * docente, como hoja de calculo.
 */
final readonly class ArmarListaDeGrupo
{
    public const ENCABEZADOS = ['Código', 'Estudiante', 'Documento', 'Correo institucional', 'Origen'];

    public function __construct(
        private InscripcionGateway $inscripciones,
        private AlcanceDocenteGateway $alcance,
        private GeneradorListaDeGrupo $generador,
        /** El correo institucional no se guarda: es el codigo universitario con este dominio. */
        private string $dominioCorreo,
    ) {}

    /**
     * @return ArchivoDeListaData|null null si el grupo no existe
     *
     * @throws GrupoAjenoException
     * @throws GrupoSinListaException
     */
    public function execute(int $usuarioId, int $grupoId): ?ArchivoDeListaData
    {
        $grupo = $this->inscripciones->grupo($grupoId);

        if ($grupo === null) {
            return null;
        }

        if (! $this->alcance->esGrupoDelDocente($usuarioId, $grupoId)) {
            throw new GrupoAjenoException;
        }

        $inscritos = $this->inscripciones->listaDeGrupo($grupoId);

        if ($inscritos === []) {
            throw new GrupoSinListaException;
        }

        $filas = [];

        foreach ($inscritos as $inscrito) {
            $filas[] = [
                $inscrito['codigo'],
                $inscrito['nombre'],
                $inscrito['documento'],
                $inscrito['codigo'].'@'.$this->dominioCorreo,
                $inscrito['origen'],
            ];
        }

        return new ArchivoDeListaData(
            sprintf(
                'estudiantes_%s_g%s.xlsx',
                self::paraArchivo($grupo->asignaturaCodigo),
                self::paraArchivo($grupo->codigo),
            ),
            $this->generador->generar(self::ENCABEZADOS, $filas),
        );
    }

    private static function paraArchivo(string $texto): string
    {
        return (string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $texto);
    }
}
