<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Actions;

use App\Modules\Academico\Application\Contracts\AlcanceDocenteGateway;
use App\Modules\Estudiantes\Application\Contracts\InscripcionGateway;
use App\Modules\Estudiantes\Application\Contracts\PadronGateway;
use App\Modules\Estudiantes\Application\DTOs\AltaDeInscritoData;
use App\Modules\Estudiantes\Application\DTOs\SolicitudAltaData;
use App\Modules\Estudiantes\Domain\Enums\ResultadoAlta;
use App\Modules\Estudiantes\Domain\Exceptions\DocumentoEnUsoException;
use App\Modules\Estudiantes\Domain\Exceptions\GrupoAjenoException;
use App\Modules\Estudiantes\Domain\Exceptions\PeriodoCerradoException;
use App\Modules\Estudiantes\Domain\Exceptions\YaInscritoException;
use App\Modules\Estudiantes\Domain\Rules\DatosDeInscrito;

/**
 * El docente agrega a un estudiante suelto a la lista de su grupo, con las
 * reglas de una fila del archivo de carga: si no esta en el padron se crea
 * con origen docente; si esta, solo se inscribe, y si sus datos difieren
 * no se tocan: queda el conflicto para la administracion.
 */
final readonly class AgregarInscrito
{
    public function __construct(
        private InscripcionGateway $inscripciones,
        private PadronGateway $padron,
        private AlcanceDocenteGateway $alcance,
    ) {}

    /**
     * @return ResultadoAlta|null null si el grupo no existe
     *
     * @throws GrupoAjenoException
     * @throws PeriodoCerradoException
     * @throws YaInscritoException
     * @throws DocumentoEnUsoException si el documento es de otro estudiante
     */
    public function execute(SolicitudAltaData $solicitud): ?ResultadoAlta
    {
        $grupo = $this->inscripciones->grupo($solicitud->grupoId);

        if ($grupo === null) {
            return null;
        }

        if (! $this->alcance->esGrupoDelDocente($solicitud->usuarioId, $grupo->id)) {
            throw new GrupoAjenoException;
        }

        if (! $grupo->periodoVigente) {
            throw new PeriodoCerradoException;
        }

        $codigo = $solicitud->codigoUniversitario;
        $alta = new AltaDeInscritoData(
            usuarioId: $solicitud->usuarioId,
            grupoId: $grupo->id,
            facultadId: $grupo->facultadId,
            codigoUniversitario: $codigo,
            documentoIdentidad: $solicitud->documentoIdentidad,
            nombres: mb_substr($solicitud->nombres, 0, DatosDeInscrito::MAXIMO_NOMBRE),
            apellidos: mb_substr($solicitud->apellidos, 0, DatosDeInscrito::MAXIMO_NOMBRE),
        );

        $guardado = $this->padron->porCodigos([$codigo])[$codigo] ?? null;

        if ($guardado === null) {
            $dueno = $this->padron->codigosPorDocumento([$alta->documentoIdentidad])[$alta->documentoIdentidad] ?? null;

            if ($dueno !== null && $dueno !== $codigo) {
                throw new DocumentoEnUsoException;
            }

            $this->inscripciones->agregar($alta);

            return ResultadoAlta::Nuevo;
        }

        if ($this->inscripciones->existentes([$guardado->id], [$grupo->id]) !== []) {
            throw new YaInscritoException;
        }

        $conflicto = DatosDeInscrito::diferencia(
            $guardado->documentoIdentidad,
            $guardado->nombres,
            $guardado->apellidos,
            $alta->documentoIdentidad,
            $alta->nombres,
            $alta->apellidos,
        );

        $reinscrito = $this->inscripciones->agregar(new AltaDeInscritoData(
            usuarioId: $alta->usuarioId,
            grupoId: $alta->grupoId,
            facultadId: $alta->facultadId,
            codigoUniversitario: $codigo,
            documentoIdentidad: $alta->documentoIdentidad,
            nombres: $alta->nombres,
            apellidos: $alta->apellidos,
            estudianteId: $guardado->id,
            conflicto: $conflicto,
        ));

        return match (true) {
            $conflicto !== null => ResultadoAlta::Conflicto,
            $reinscrito => ResultadoAlta::Reinscrito,
            default => ResultadoAlta::Inscrito,
        };
    }
}
