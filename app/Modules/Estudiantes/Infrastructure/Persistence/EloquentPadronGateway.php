<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Infrastructure\Persistence;

use App\Modules\Estudiantes\Application\Contracts\PadronGateway;
use App\Modules\Estudiantes\Application\DTOs\EstudianteData;
use App\Modules\Estudiantes\Application\DTOs\FiltroListaData;
use App\Modules\Estudiantes\Application\DTOs\PaginaData;
use App\Modules\Estudiantes\Domain\Enums\EstadoConflicto;
use App\Modules\Estudiantes\Domain\Enums\OrigenEstudiante;
use Illuminate\Support\Facades\DB;

final class EloquentPadronGateway implements PadronGateway
{
    use ConsultasDePadron;

    public function listar(FiltroListaData $filtro): PaginaData
    {
        $consulta = DB::table('estudiantes as e')
            ->leftJoin('facultades as f', 'f.id', '=', 'e.facultad_id')
            ->leftJoin('carreras as c', 'c.id', '=', 'e.carrera_id');

        if ($filtro->facultad !== null) {
            $facultad = mb_strtolower($filtro->facultad);

            $consulta->whereRaw('(lower(f.clave) = ? or lower(f.sigla) = ?)', [$facultad, $facultad]);
        }

        if ($filtro->carreraId !== null) {
            $consulta->where('e.carrera_id', $filtro->carreraId);
        }

        $this->buscar($consulta, $filtro->buscar, [
            'e.apellidos',
            'e.nombres',
            'e.codigo_universitario',
            'e.documento_identidad',
        ]);

        $total = (clone $consulta)->count();
        $hoy = now()->toDateString();

        $filas = $consulta
            ->select([
                'e.id',
                'e.codigo_universitario',
                'e.documento_identidad',
                'e.nombres',
                'e.apellidos',
                'e.verificado',
                'f.sigla as facultad',
                'c.nombre as carrera',
            ])
            ->selectRaw(
                '(select count(*) from inscripciones i
                    join grupos g on g.id = i.grupo_id
                    join periodos p on p.id = g.periodo_id
                   where i.estudiante_id = e.id
                     and p.fecha_inicio <= ? and p.fecha_fin >= ?) as grupos',
                [$hoy, $hoy],
            )
            ->orderBy('e.apellidos')
            ->orderBy('e.nombres')
            ->orderBy('e.id')
            ->forPage($filtro->pagina, $filtro->porPagina)
            ->get();

        $datos = [];

        foreach ($filas as $fila) {
            $datos[] = [
                'id' => $this->entero($fila->id),
                'codigo' => $this->texto($fila->codigo_universitario),
                'nombre' => $this->nombreCompleto($fila->apellidos, $fila->nombres),
                'documento' => $this->texto($fila->documento_identidad),
                'facultad' => $this->texto($fila->facultad),
                'carrera' => $this->texto($fila->carrera),
                'grupos' => $this->entero($fila->grupos),
                'origen' => (bool) $fila->verificado ? 'Verificado' : 'Docente',
            ];
        }

        return new PaginaData($datos, $total, $filtro->pagina, $filtro->porPagina, $this->conteos());
    }

    public function ficha(int $estudianteId, string $dominioCorreo): ?array
    {
        $estudiante = DB::table('estudiantes as e')
            ->leftJoin('facultades as f', 'f.id', '=', 'e.facultad_id')
            ->leftJoin('carreras as c', 'c.id', '=', 'e.carrera_id')
            ->where('e.id', $estudianteId)
            ->first([
                'e.id',
                'e.codigo_universitario',
                'e.documento_identidad',
                'e.nombres',
                'e.apellidos',
                'e.verificado',
                'f.sigla as facultad',
                'c.nombre as carrera',
            ]);

        if ($estudiante === null) {
            return null;
        }

        $inscripciones = DB::table('inscripciones as i')
            ->join('grupos as g', 'g.id', '=', 'i.grupo_id')
            ->join('asignaturas as a', 'a.id', '=', 'g.asignatura_id')
            ->join('periodos as p', 'p.id', '=', 'g.periodo_id')
            ->leftJoin('docentes as d', 'd.id', '=', 'g.docente_id')
            ->where('i.estudiante_id', $estudianteId);

        $this->vigente($inscripciones, 'p');

        $filas = $inscripciones
            ->orderBy('a.nombre')
            ->orderBy('g.codigo')
            ->orderBy('g.id')
            ->get([
                'g.id as grupo_id',
                'g.codigo as grupo',
                'a.codigo as asignatura_codigo',
                'a.nombre as asignatura',
                'd.nombre_completo as docente',
                'p.codigo as periodo',
                'i.via',
            ]);

        $materias = [];

        foreach ($filas as $fila) {
            $via = OrigenEstudiante::tryFrom((string) $this->texto($fila->via));

            $materias[] = [
                'asignatura' => [
                    'codigo' => (string) $this->texto($fila->asignatura_codigo),
                    'nombre' => (string) $this->texto($fila->asignatura),
                ],
                'grupo' => (string) $this->texto($fila->grupo),
                'grupo_id' => $this->entero($fila->grupo_id),
                'docente' => $this->texto($fila->docente),
                'periodo' => (string) $this->texto($fila->periodo),
                'via' => ($via ?? OrigenEstudiante::Administracion)->etiqueta(),
            ];
        }

        $datos = get_object_vars($estudiante);
        $codigo = (string) $this->texto($datos['codigo_universitario'] ?? null);

        return [
            'id' => $this->entero($datos['id'] ?? null),
            'codigo' => $codigo,
            'nombre' => $this->nombreCompleto($datos['apellidos'] ?? null, $datos['nombres'] ?? null),
            'nombres' => (string) $this->texto($datos['nombres'] ?? null),
            'apellidos' => (string) $this->texto($datos['apellidos'] ?? null),
            'documento' => $this->texto($datos['documento_identidad'] ?? null),
            'correo' => $codigo.'@'.$dominioCorreo,
            'facultad' => $this->texto($datos['facultad'] ?? null),
            'carrera' => $this->texto($datos['carrera'] ?? null),
            'origen' => (bool) ($datos['verificado'] ?? false) ? 'Verificado' : 'Docente',
            'materias' => $materias,
        ];
    }

    public function resumen(): array
    {
        $inscripciones = DB::table('inscripciones as i')
            ->join('grupos as g', 'g.id', '=', 'i.grupo_id')
            ->join('periodos as p', 'p.id', '=', 'g.periodo_id');

        $this->vigente($inscripciones, 'p');

        return [
            'estudiantes' => DB::table('estudiantes')->count(),
            'inscripciones' => $inscripciones->count(),
            'cargados_por_docentes' => DB::table('estudiantes')->where('verificado', false)->count(),
            'conflictos_pendientes' => DB::table('conflictos_padron')
                ->where('estado', EstadoConflicto::Pendiente->value)
                ->count(),
        ];
    }

    /**
     * @param  list<string>  $codigos
     * @return array<string, EstudianteData>
     */
    public function porCodigos(array $codigos): array
    {
        $estudiantes = [];

        foreach (array_chunk($codigos, 1000) as $lote) {
            $filas = DB::table('estudiantes')
                ->whereIn('codigo_universitario', $lote)
                ->get(['id', 'codigo_universitario', 'documento_identidad', 'nombres', 'apellidos', 'verificado']);

            foreach ($filas as $fila) {
                $estudiante = $this->estudiante($fila);
                $estudiantes[$estudiante->codigoUniversitario] = $estudiante;
            }
        }

        return $estudiantes;
    }

    /**
     * @param  list<string>  $documentos
     * @return array<string, string>
     */
    public function codigosPorDocumento(array $documentos): array
    {
        $duenos = [];

        foreach (array_chunk($documentos, 1000) as $lote) {
            $filas = DB::table('estudiantes')
                ->whereIn('documento_identidad', $lote)
                ->get(['documento_identidad', 'codigo_universitario']);

            foreach ($filas as $fila) {
                $duenos[(string) $this->texto($fila->documento_identidad)] = (string) $this->texto($fila->codigo_universitario);
            }
        }

        return $duenos;
    }

    /**
     * Estudiantes por facultad, sobre todo el padron: son las cifras de
     * los filtros, no cambian con la busqueda.
     *
     * @return array<string, int>
     */
    private function conteos(): array
    {
        $conteos = ['todas' => DB::table('estudiantes')->count()];

        $filas = DB::table('facultades as f')
            ->leftJoin('estudiantes as e', 'e.facultad_id', '=', 'f.id')
            ->groupBy('f.id', 'f.sigla', 'f.orden')
            ->orderBy('f.orden')
            ->selectRaw('f.sigla, count(e.id) as total')
            ->get();

        foreach ($filas as $fila) {
            $conteos[(string) $this->texto($fila->sigla)] = $this->entero($fila->total);
        }

        return $conteos;
    }
}
