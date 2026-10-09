<?php

declare(strict_types=1);

namespace App\Modules\Academico\Infrastructure\Persistence;

use App\Modules\Academico\Application\Contracts\ConsultaDocentesGateway;
use App\Modules\Academico\Application\DTOs\FiltroDocentesData;
use App\Modules\Academico\Application\DTOs\PaginaData;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentConsultaDocentesGateway implements ConsultaDocentesGateway
{
    use LeeFilas;

    public function listar(FiltroDocentesData $filtro, array $periodoIds, PaginaData $pagina): array
    {
        $consulta = DB::table('docentes as d')
            ->leftJoin('usuarios as u', 'u.id', '=', 'd.user_id');

        if ($filtro->sinCuenta) {
            $consulta->whereNull('d.user_id');
        }

        if ($this->hayTexto($filtro->facultad)) {
            $facultadId = $this->facultadId((string) $filtro->facultad);

            $consulta->whereExists(function (Builder $grupos) use ($periodoIds, $facultadId): void {
                $grupos->selectRaw('1')
                    ->from('grupos as g')
                    ->whereColumn('g.docente_id', 'd.id')
                    ->where('g.facultad_id', $facultadId);
                $this->enPeriodos($grupos, 'g.periodo_id', $periodoIds);
            });
        }

        if ($filtro->variasFacultades) {
            $consulta->whereIn('d.id', $this->conVariasFacultades($periodoIds));
        }

        if ($this->hayTexto($filtro->buscar)) {
            $consulta->whereRaw(
                $this->sinTildes('d.nombre_completo').' like ?',
                [$this->patron((string) $filtro->buscar)],
            );
        }

        $total = (clone $consulta)->count();

        $filas = $consulta
            ->orderByRaw($this->sinTildes('d.nombre_completo'))
            ->orderBy('d.id')
            ->forPage($pagina->pagina, $pagina->porPagina)
            ->get(['d.id', 'd.nombre_completo', 'd.user_id', 'u.password_changed_at']);

        $ids = [];

        foreach ($filas as $fila) {
            $ids[] = $this->entero($fila->id);
        }

        $facultades = $this->facultadesDe($ids, $periodoIds);
        $docentes = [];

        foreach ($filas as $fila) {
            $id = $this->entero($fila->id);

            $docentes[] = [
                'id' => $id,
                'nombre' => $this->cadena($fila->nombre_completo),
                'facultades' => array_map(
                    static fn (array $facultad): array => [
                        'sigla' => $facultad['sigla'],
                        'grupos' => $facultad['grupos'],
                    ],
                    $facultades[$id] ?? [],
                ),
                'cuenta' => $this->estadoDeCuenta($fila->user_id, $fila->password_changed_at),
            ];
        }

        return ['filas' => $docentes, 'total' => $total];
    }

    public function conteos(array $periodoIds): array
    {
        $conteos = ['todas' => DB::table('docentes')->count()];

        $porFacultad = DB::table('grupos as g')
            ->whereNotNull('g.docente_id')
            ->groupBy('g.facultad_id')
            ->selectRaw('g.facultad_id, count(distinct g.docente_id) as total');
        $this->enPeriodos($porFacultad, 'g.periodo_id', $periodoIds);

        $totales = [];

        foreach ($porFacultad->get() as $fila) {
            $totales[$this->entero($fila->facultad_id)] = $this->entero($fila->total);
        }

        foreach (DB::table('facultades')->orderBy('orden')->orderBy('id')->get(['id', 'sigla']) as $fila) {
            $conteos[$this->cadena($fila->sigla)] = $totales[$this->entero($fila->id)] ?? 0;
        }

        $conteos['sin_cuenta'] = DB::table('docentes')->whereNull('user_id')->count();
        $conteos['varias_facultades'] = DB::query()
            ->fromSub($this->conVariasFacultades($periodoIds), 'varias')
            ->count();

        return $conteos;
    }

    public function detalle(int $docenteId, array $periodoIds): ?array
    {
        $fila = $this->primera(
            DB::table('docentes as d')
                ->leftJoin('usuarios as u', 'u.id', '=', 'd.user_id')
                ->where('d.id', $docenteId),
            ['d.id', 'd.nombre_completo', 'd.user_id', 'u.usuario', 'u.correo', 'u.password_changed_at'],
        );

        if ($fila === null) {
            return null;
        }

        $materias = DB::table('grupos as g')
            ->join('asignaturas as a', 'a.id', '=', 'g.asignatura_id')
            ->where('g.docente_id', $docenteId)
            ->distinct();
        $this->enPeriodos($materias, 'g.periodo_id', $periodoIds);

        $porFacultad = [];

        foreach ($materias->get(['g.facultad_id', 'a.nombre']) as $materia) {
            $porFacultad[$this->entero($materia->facultad_id)][] = $this->cadena($materia->nombre);
        }

        foreach ($porFacultad as $facultadId => $nombres) {
            usort($nombres, fn (string $a, string $b): int => strcmp($this->normalizar($a), $this->normalizar($b)));
            $porFacultad[$facultadId] = $nombres;
        }

        $facultades = [];
        $grupos = 0;

        foreach ($this->facultadesDe([$docenteId], $periodoIds)[$docenteId] ?? [] as $facultad) {
            $grupos += $facultad['grupos'];

            $facultades[] = [
                'sigla' => $facultad['sigla'],
                'grupos' => $facultad['grupos'],
                'materias' => $porFacultad[$facultad['id']] ?? [],
            ];
        }

        $cuenta = null;

        if ($fila->user_id !== null) {
            $cuenta = [
                'estado' => $this->estadoDeCuenta($fila->user_id, $fila->password_changed_at),
                'usuario' => $this->cadena($fila->usuario),
                'correo' => $this->texto($fila->correo),
            ];
        }

        return [
            'id' => $this->entero($fila->id),
            'nombre' => $this->cadena($fila->nombre_completo),
            'grupos' => $grupos,
            'facultades' => $facultades,
            'cuenta' => $cuenta,
        ];
    }

    private function estadoDeCuenta(mixed $usuarioId, mixed $contrasenaCambiada): string
    {
        if ($usuarioId === null) {
            return 'sin_cuenta';
        }

        return $contrasenaCambiada === null ? 'temporal' : 'activa';
    }

    /**
     * Subconsulta con los docentes que dictan en mas de una facultad.
     *
     * @param  list<int>  $periodoIds
     */
    private function conVariasFacultades(array $periodoIds): Builder
    {
        $varias = DB::table('grupos as gv')
            ->select('gv.docente_id')
            ->whereNotNull('gv.docente_id')
            ->groupBy('gv.docente_id')
            ->havingRaw('count(distinct gv.facultad_id) > 1');
        $this->enPeriodos($varias, 'gv.periodo_id', $periodoIds);

        return $varias;
    }

    /**
     * Las facultades de cada docente con sus grupos, en el orden del
     * catalogo.
     *
     * @param  list<int>  $docenteIds
     * @param  list<int>  $periodoIds
     * @return array<int, list<array{id: int, sigla: string, grupos: int}>>
     */
    private function facultadesDe(array $docenteIds, array $periodoIds): array
    {
        if ($docenteIds === []) {
            return [];
        }

        $consulta = DB::table('grupos as g')
            ->join('facultades as f', 'f.id', '=', 'g.facultad_id')
            ->whereIn('g.docente_id', $docenteIds)
            ->groupBy('g.docente_id', 'f.id', 'f.sigla', 'f.orden')
            ->orderBy('f.orden')
            ->orderBy('f.id')
            ->selectRaw('g.docente_id, f.id as facultad_id, f.sigla, count(*) as total');
        $this->enPeriodos($consulta, 'g.periodo_id', $periodoIds);

        $facultades = [];

        foreach ($consulta->get() as $fila) {
            $facultades[$this->entero($fila->docente_id)][] = [
                'id' => $this->entero($fila->facultad_id),
                'sigla' => $this->cadena($fila->sigla),
                'grupos' => $this->entero($fila->total),
            ];
        }

        return $facultades;
    }
}
