<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\Contracts;

use App\Modules\Estudiantes\Application\DTOs\EstudianteData;
use App\Modules\Estudiantes\Application\DTOs\FiltroListaData;
use App\Modules\Estudiantes\Application\DTOs\PaginaData;

/**
 * El padron: un estudiante existe una sola vez en toda la universidad.
 */
interface PadronGateway
{
    /**
     * Pagina del padron, por apellidos y nombres, con los conteos por
     * facultad.
     */
    public function listar(FiltroListaData $filtro): PaginaData;

    /**
     * La ficha de un estudiante: sus datos y las materias que lleva en los
     * periodos vigentes, con el grupo y el docente de cada una (`docente`
     * null = por designar). Null si el estudiante no existe.
     *
     * @return array{
     *     id: int,
     *     codigo: string,
     *     nombre: string,
     *     nombres: string,
     *     apellidos: string,
     *     documento: string|null,
     *     correo: string,
     *     facultad: string|null,
     *     carrera: string|null,
     *     origen: string,
     *     materias: list<array{
     *         asignatura: array{codigo: string, nombre: string},
     *         grupo: string,
     *         grupo_id: int,
     *         docente: string|null,
     *         periodo: string,
     *         via: string
     *     }>
     * }|null
     */
    public function ficha(int $estudianteId, string $dominioCorreo): ?array;

    /**
     * @return array{estudiantes: int, inscripciones: int, cargados_por_docentes: int, conflictos_pendientes: int}
     */
    public function resumen(): array;

    /**
     * Los estudiantes guardados con esos codigos, por codigo.
     *
     * @param  list<string>  $codigos
     * @return array<string, EstudianteData>
     */
    public function porCodigos(array $codigos): array;

    /**
     * De quien es cada documento: documento => codigo universitario.
     *
     * @param  list<string>  $documentos
     * @return array<string, string>
     */
    public function codigosPorDocumento(array $documentos): array;
}
