<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Actions;

use App\Modules\Academico\Application\Contracts\AlcanceDocenteGateway;
use App\Modules\Academico\Application\Contracts\PeriodoGateway;
use App\Modules\Estudiantes\Application\Contracts\InscripcionGateway;
use App\Modules\Estudiantes\Application\Contracts\LectorListaGateway;
use App\Modules\Estudiantes\Application\Contracts\PadronGateway;
use App\Modules\Estudiantes\Application\DTOs\ConflictoCargaData;
use App\Modules\Estudiantes\Application\DTOs\EstudianteData;
use App\Modules\Estudiantes\Application\DTOs\FilaInscritoData;
use App\Modules\Estudiantes\Application\DTOs\RechazoCargaData;
use App\Modules\Estudiantes\Application\DTOs\RegistroCargaData;
use App\Modules\Estudiantes\Application\DTOs\ResultadoCargaData;
use App\Modules\Estudiantes\Application\DTOs\SolicitudCargaData;
use App\Modules\Estudiantes\Domain\Enums\AlcanceCarga;
use App\Modules\Estudiantes\Domain\Enums\OrigenEstudiante;
use App\Modules\Estudiantes\Domain\Enums\TipoConflicto;
use App\Modules\Estudiantes\Domain\Exceptions\ArchivoRechazadoException;
use App\Modules\Estudiantes\Domain\Exceptions\GrupoAjenoException;
use App\Modules\Estudiantes\Domain\Exceptions\PeriodoCerradoException;
use App\Modules\Estudiantes\Domain\Rules\FormatoDeLista;

/**
 * Carga de una lista de inscritos (3.5.1). Cada fila cae en una sola
 * salida: rechazada, nuevo, conflicto, ya inscrito o reutilizado. La carga
 * es aditiva (nunca quita inscritos) y no modifica a un estudiante que ya
 * existe: las diferencias quedan como conflicto para la administracion.
 *
 * Antes de mirar las filas se revisa el archivo entero (tipo real y
 * estructura): si no es una lista de inscritos no se guarda nada.
 */
final readonly class CargarInscritos
{
    /**
     * Columnas de la lista de un grupo, por posicion.
     */
    public const COLUMNAS_GRUPO = [
        'codigo_universitario',
        'documento_identidad',
        'nombres',
        'apellidos',
    ];

    /**
     * Columnas de la carga de una facultad: una fila por inscripcion.
     * `carrera` es opcional.
     */
    public const COLUMNAS_FACULTAD = [
        'codigo_universitario',
        'documento_identidad',
        'nombres',
        'apellidos',
        'codigo_asignatura',
        'grupo',
        'carrera',
    ];

    /**
     * Las columnas de la carga de facultad que no pueden faltar.
     */
    private const OBLIGATORIAS_FACULTAD = 6;

    public const MOTIVO_FALTAN_DATOS = 'Faltan datos obligatorios';

    public const MOTIVO_SIN_DOCUMENTO = 'Sin documento de identidad';

    public const MOTIVO_CODIGO = 'Código universitario no válido';

    public const MOTIVO_DOCUMENTO = 'Documento de identidad no válido';

    public const MOTIVO_REPETIDO = 'Se repite en el archivo';

    public const MOTIVO_GRUPO = 'El grupo no existe en la oferta';

    public const MOTIVO_DOCUMENTO_AJENO = 'El documento ya pertenece a otro estudiante';

    public function __construct(
        private LectorListaGateway $lector,
        private PadronGateway $padron,
        private InscripcionGateway $inscripciones,
        private AlcanceDocenteGateway $alcance,
        private PeriodoGateway $periodos,
    ) {}

    /**
     * @return ResultadoCargaData|null null si el grupo o la facultad no existen
     *
     * @throws GrupoAjenoException
     * @throws PeriodoCerradoException
     * @throws ArchivoRechazadoException si el archivo se rechaza entero; no se guarda nada
     */
    public function execute(SolicitudCargaData $solicitud): ?ResultadoCargaData
    {
        $grupoId = null;
        $gruposPorClave = [];
        $carreras = [];

        if ($solicitud->alcance === AlcanceCarga::Grupo) {
            $grupo = $solicitud->grupoId === null
                ? null
                : $this->inscripciones->grupo($solicitud->grupoId);

            if ($grupo === null) {
                return null;
            }

            if (! $this->alcance->esGrupoDelDocente($solicitud->usuarioId, $grupo->id)) {
                throw new GrupoAjenoException;
            }

            if (! $grupo->periodoVigente) {
                throw new PeriodoCerradoException;
            }

            $via = OrigenEstudiante::Docente;
            $grupoId = $grupo->id;
            $facultadId = $grupo->facultadId;
            $periodoId = $grupo->periodoId;
        } else {
            $facultadId = $this->inscripciones->facultadPorClave((string) $solicitud->facultad);

            if ($facultadId === null) {
                return null;
            }

            $periodo = $this->periodos->principal();

            if ($periodo === null) {
                throw new PeriodoCerradoException('No hay un período vigente para cargar inscripciones.');
            }

            $via = OrigenEstudiante::Administracion;
            $periodoId = $periodo->id;
            $gruposPorClave = $this->inscripciones->gruposVigentesDeFacultad($facultadId);
            $carreras = $this->inscripciones->carrerasDeFacultad($facultadId);
        }

        $obligatorias = self::obligatorias($solicitud->alcance);

        try {
            $filas = FormatoDeLista::revisar(
                $this->lector->leer($solicitud->rutaArchivo, $solicitud->extension),
                $obligatorias,
                self::opcionales($solicitud->alcance),
            );
        } catch (ArchivoRechazadoException $rechazo) {
            throw $rechazo->conOrdenEsperado($obligatorias);
        }

        // Paso 1: lo que el archivo dice por si solo.
        $rechazos = [];
        $validas = [];
        $vistas = [];
        $total = count($filas);

        foreach ($filas as $numero => $valores) {
            $leida = $this->interpretar($numero, $valores, $grupoId, $facultadId, $gruposPorClave, $carreras);

            if (is_string($leida)) {
                $rechazos[] = new RechazoCargaData($numero, $leida);

                continue;
            }

            $clave = $leida->codigoUniversitario.'|'.$leida->grupoId;

            if (isset($vistas[$clave])) {
                $rechazos[] = new RechazoCargaData($numero, self::MOTIVO_REPETIDO);

                continue;
            }

            $vistas[$clave] = true;
            $validas[] = $leida;
        }

        // Paso 2: lo que dice el padron.
        $codigos = [];
        $documentos = [];
        $grupos = [];

        foreach ($validas as $fila) {
            $codigos[$fila->codigoUniversitario] = true;
            $documentos[$fila->documentoIdentidad] = true;
            $grupos[$fila->grupoId] = true;
        }

        $guardados = $this->padron->porCodigos(array_map(strval(...), array_keys($codigos)));
        $duenos = $this->padron->codigosPorDocumento(array_map(strval(...), array_keys($documentos)));

        $inscritos = $this->inscripciones->existentes(
            array_values(array_map(
                static fn (EstudianteData $estudiante): int => $estudiante->id,
                $guardados,
            )),
            array_keys($grupos),
        );

        $nuevos = [];
        $porCrear = [];
        $porInscribir = [];
        $enConflicto = [];
        $conflictos = [];
        $porVerificar = [];
        $cuentaNuevos = 0;
        $cuentaReutilizados = 0;
        $cuentaYaInscritos = 0;

        foreach ($validas as $fila) {
            $codigo = $fila->codigoUniversitario;
            $guardado = $guardados[$codigo] ?? null;
            $creado = $porCrear[$codigo] ?? null;

            if ($guardado === null && $creado === null) {
                $dueno = $duenos[$fila->documentoIdentidad] ?? null;

                if ($dueno !== null && $dueno !== $codigo) {
                    $rechazos[] = new RechazoCargaData($fila->fila, self::MOTIVO_DOCUMENTO_AJENO);

                    continue;
                }

                $duenos[$fila->documentoIdentidad] = $codigo;
                $porCrear[$codigo] = $fila;
                $nuevos[] = $fila;
                $porInscribir[] = $fila;
                $cuentaNuevos++;

                continue;
            }

            $tipo = $guardado !== null
                ? $this->diferencia($guardado->documentoIdentidad, $guardado->nombres, $guardado->apellidos, $fila)
                : $this->diferencia($creado->documentoIdentidad, $creado->nombres, $creado->apellidos, $fila);

            if ($tipo !== null) {
                $enConflicto[] = $fila->conConflicto($tipo);
                $conflictos[] = new ConflictoCargaData(
                    $fila->fila,
                    $codigo,
                    $tipo === TipoConflicto::DocumentoDistinto
                        ? 'Documento distinto al del padrón'
                        : 'Nombre distinto al del padrón',
                );

                continue;
            }

            // Una carga de administracion que coincide confirma al
            // estudiante que habia traido un docente.
            if ($via === OrigenEstudiante::Administracion && $guardado !== null && ! $guardado->verificado) {
                $porVerificar[$codigo] = true;
            }

            if ($guardado !== null && isset($inscritos[$guardado->id.'|'.$fila->grupoId])) {
                $cuentaYaInscritos++;

                continue;
            }

            $porInscribir[] = $fila;
            $cuentaReutilizados++;
        }

        usort(
            $rechazos,
            static fn (RechazoCargaData $a, RechazoCargaData $b): int => $a->fila <=> $b->fila,
        );

        $resultado = new ResultadoCargaData(
            archivo: mb_substr($solicitud->nombreArchivo, 0, 200),
            filas: $total,
            nuevos: $cuentaNuevos,
            reutilizados: $cuentaReutilizados,
            yaInscritos: $cuentaYaInscritos,
            rechazos: $rechazos,
            conflictos: $conflictos,
        );

        $this->inscripciones->registrar(new RegistroCargaData(
            alcance: $solicitud->alcance,
            grupoId: $grupoId,
            facultadId: $facultadId,
            periodoId: $periodoId,
            via: $via,
            usuarioId: $solicitud->usuarioId,
            resultado: $resultado,
            nuevos: $nuevos,
            inscripciones: $porInscribir,
            conflictos: $enConflicto,
            porVerificar: array_map(strval(...), array_keys($porVerificar)),
        ));

        return $resultado;
    }

    /**
     * Las columnas que el archivo tiene que traer, en orden.
     *
     * @return list<string>
     */
    public static function obligatorias(AlcanceCarga $alcance): array
    {
        return $alcance === AlcanceCarga::Facultad
            ? array_slice(self::COLUMNAS_FACULTAD, 0, self::OBLIGATORIAS_FACULTAD)
            : self::COLUMNAS_GRUPO;
    }

    /**
     * @return list<string>
     */
    private static function opcionales(AlcanceCarga $alcance): array
    {
        return $alcance === AlcanceCarga::Facultad
            ? array_slice(self::COLUMNAS_FACULTAD, self::OBLIGATORIAS_FACULTAD)
            : [];
    }

    /**
     * La fila ya interpretada o el motivo por el que se rechaza.
     *
     * @param  list<string>  $valores
     * @param  array<string, int>  $gruposPorClave
     * @param  array<string, int>  $carreras
     */
    private function interpretar(
        int $numero,
        array $valores,
        ?int $grupoId,
        int $facultadId,
        array $gruposPorClave,
        array $carreras,
    ): FilaInscritoData|string {
        $codigo = $valores[0] ?? '';
        $documento = $valores[1] ?? '';
        $nombres = $valores[2] ?? '';
        $apellidos = $valores[3] ?? '';
        $asignatura = $valores[4] ?? '';
        $grupo = $valores[5] ?? '';
        $carrera = $valores[6] ?? '';

        if ($codigo === '' || $nombres === '' || $apellidos === '') {
            return self::MOTIVO_FALTAN_DATOS;
        }

        if ($grupoId === null && ($asignatura === '' || $grupo === '')) {
            return self::MOTIVO_FALTAN_DATOS;
        }

        if ($documento === '') {
            return self::MOTIVO_SIN_DOCUMENTO;
        }

        if (preg_match('/^\d{5,10}$/', $codigo) !== 1) {
            return self::MOTIVO_CODIGO;
        }

        if (mb_strlen($documento) > 30) {
            return self::MOTIVO_DOCUMENTO;
        }

        $carreraId = null;

        if ($grupoId === null) {
            $grupoId = $gruposPorClave[mb_strtoupper($asignatura.'|'.$grupo)]
                ?? $gruposPorClave[mb_strtoupper($asignatura.'|'.ltrim($grupo, '0'))]
                ?? null;

            if ($grupoId === null) {
                return self::MOTIVO_GRUPO;
            }

            $carreraId = $carreras[mb_strtoupper($carrera)] ?? null;
        }

        return new FilaInscritoData(
            fila: $numero,
            codigoUniversitario: $codigo,
            documentoIdentidad: $documento,
            nombres: mb_substr($nombres, 0, 100),
            apellidos: mb_substr($apellidos, 0, 100),
            grupoId: $grupoId,
            facultadId: $facultadId,
            carreraId: $carreraId,
        );
    }

    /**
     * En que difiere la fila del estudiante guardado; null si coincide.
     * Manda el documento: si difiere, el conflicto es de documento.
     */
    private function diferencia(
        ?string $documento,
        string $nombres,
        string $apellidos,
        FilaInscritoData $fila,
    ): ?TipoConflicto {
        if ($this->documento((string) $documento) !== $this->documento($fila->documentoIdentidad)) {
            return TipoConflicto::DocumentoDistinto;
        }

        if (
            $this->normalizar($nombres) !== $this->normalizar($fila->nombres)
            || $this->normalizar($apellidos) !== $this->normalizar($fila->apellidos)
        ) {
            return TipoConflicto::NombreDistinto;
        }

        return null;
    }

    private function documento(string $valor): string
    {
        return mb_strtoupper((string) preg_replace('/\s+/u', '', $valor));
    }

    /**
     * Mayusculas, sin tildes y con espacios simples: «José  Luis» y
     * «JOSE LUIS» son el mismo nombre.
     */
    private function normalizar(string $valor): string
    {
        $valor = mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', $valor)));

        return strtr($valor, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U',
            'À' => 'A', 'È' => 'E', 'Ì' => 'I', 'Ò' => 'O', 'Ù' => 'U',
        ]);
    }
}
