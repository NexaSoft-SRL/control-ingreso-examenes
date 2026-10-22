<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Infrastructure\Persistence;

use App\Modules\Examenes\Application\Contracts\AuxiliaresDocenteGateway;
use App\Modules\Examenes\Domain\Models\DocenteAuxiliar;
use App\Modules\Examenes\Domain\Models\ExamenAulaPersonal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentAuxiliaresDocenteGateway implements AuxiliaresDocenteGateway
{
    public function buscar(string $termino): array
    {
        $normalizar = static fn (string $texto): string => strtr(
            mb_strtolower(trim($texto)),
            [
                'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
                'ü' => 'u', 'ñ' => 'n',
            ]
        );
        $busqueda = $normalizar($termino);

        $driver = DB::connection()->getDriverName();
        $expr = $driver === 'pgsql'
            ? "TRANSLATE(LOWER(%s), 'áéíóúüñÁÉÍÓÚÜÑ', 'aeiouunaeiouun')"
            : 'LOWER(%s)';

        $filas = DB::table('usuarios as u')
            ->join('roles as r', 'r.id', '=', 'u.role_id')
            ->where('r.name', 'Auxiliar')
            ->where('u.is_active', true)
            ->where(function (Builder $consulta) use ($busqueda, $expr): void {
                $consulta->whereRaw(sprintf($expr, 'u.nombre').' LIKE ?', ["%{$busqueda}%"])
                    ->orWhereRaw(sprintf($expr, 'u.usuario').' LIKE ?', ["%{$busqueda}%"])
                    ->orWhereRaw(sprintf($expr, 'u.correo').' LIKE ?', ["%{$busqueda}%"]);
            })
            ->orderBy('u.nombre')
            ->limit(20)
            ->get(['u.id', 'u.nombre', 'u.usuario', 'u.correo']);

        /** @var list<array{id: int, nombre: string, usuario: string, codigo: string, correo: string}> $resultado */
        $resultado = array_values($filas->map(fn (object $fila): array => $this->formatear($fila))->all());

        return $resultado;
    }

    public function listarDelDocente(int $docenteId): array
    {
        $propios = DB::table('docente_auxiliares')
            ->where('docente_id', $docenteId)
            ->pluck('auxiliar_id');

        $enExamenes = DB::table('examen_aula_personal as p')
            ->join('examenes as e', 'e.id', '=', 'p.examen_id')
            ->where('e.creado_por', $docenteId)
            ->whereDate('e.fecha', '>=', now()->toDateString())
            ->pluck('p.usuario_id');

        $todosIds = [];
        foreach ($propios->concat($enExamenes) as $id) {
            if (is_numeric($id)) {
                $todosIds[(int) $id] = (int) $id;
            }
        }
        $todosIds = array_values($todosIds);

        if ($todosIds === []) {
            return [];
        }

        $filas = DB::table('usuarios as u')
            ->join('roles as r', 'r.id', '=', 'u.role_id')
            ->whereIn('u.id', $todosIds)
            ->where('r.name', 'Auxiliar')
            ->where('u.is_active', true)
            ->orderBy('u.nombre')
            ->get(['u.id', 'u.nombre', 'u.usuario', 'u.correo']);

        /** @var list<array{id: int, nombre: string, usuario: string, codigo: string, correo: string}> $resultado */
        $resultado = array_values($filas->map(fn (object $fila): array => $this->formatear($fila))->all());

        return $resultado;
    }

    public function buscarPorCodigoOCorreo(string $codigo, string $correo): ?array
    {
        /** @var object{id: int, rol_nombre: string, nombre: string}|null $existente */
        $existente = DB::table('usuarios as u')
            ->join('roles as r', 'r.id', '=', 'u.role_id')
            ->where('u.correo', $correo)
            ->orWhere('u.usuario', $codigo)
            ->select(['u.id', 'u.nombre', 'r.name as rol_nombre'])
            ->first();

        if ($existente === null) {
            return null;
        }

        return [
            'id' => (int) $existente->id,
            'nombre' => (string) $existente->nombre,
            'rol_nombre' => (string) $existente->rol_nombre,
        ];
    }

    public function agregar(int $docenteId, int $auxiliarId): void
    {
        $yaEnLista = DB::table('docente_auxiliares')
            ->where('docente_id', $docenteId)
            ->where('auxiliar_id', $auxiliarId)
            ->exists();

        if (! $yaEnLista) {
            DocenteAuxiliar::create([
                'docente_id' => $docenteId,
                'auxiliar_id' => $auxiliarId,
            ]);
        }
    }

    public function estaEnLista(int $docenteId, int $auxiliarId): bool
    {
        return DB::table('docente_auxiliares')
            ->where('docente_id', $docenteId)
            ->where('auxiliar_id', $auxiliarId)
            ->exists();
    }

    public function existeUsuario(int $auxiliarId): bool
    {
        return DB::table('usuarios')->where('id', $auxiliarId)->exists();
    }

    public function tieneExamenPendiente(int $docenteId, int $auxiliarId): bool
    {
        return DB::table('examen_aula_personal as p')
            ->join('examenes as e', 'e.id', '=', 'p.examen_id')
            ->where('e.creado_por', $docenteId)
            ->where('p.usuario_id', $auxiliarId)
            ->whereDate('e.fecha', '>=', now()->toDateString())
            ->exists();
    }

    public function quitar(int $docenteId, int $auxiliarId): void
    {
        DocenteAuxiliar::where('docente_id', $docenteId)
            ->where('auxiliar_id', $auxiliarId)
            ->delete();

        $examenesPropios = DB::table('examenes')->where('creado_por', $docenteId)->pluck('id');

        // Aulas donde estaba a cargo vuelven al docente creador
        ExamenAulaPersonal::whereIn('examen_id', $examenesPropios)
            ->where('usuario_id', $auxiliarId)
            ->where('rol', ExamenAulaPersonal::ROL_A_CARGO)
            ->update(['usuario_id' => $docenteId]);

        // Aulas donde estaba en apoyo se eliminan
        ExamenAulaPersonal::whereIn('examen_id', $examenesPropios)
            ->where('usuario_id', $auxiliarId)
            ->where('rol', ExamenAulaPersonal::ROL_APOYO)
            ->delete();
    }

    /**
     * @return array{id: int, nombre: string, usuario: string, codigo: string, correo: string}
     */
    private function formatear(object $fila): array
    {
        $cols = (array) $fila;
        $id = isset($cols['id']) && is_numeric($cols['id']) ? (int) $cols['id'] : 0;
        $nombre = isset($cols['nombre']) && is_string($cols['nombre']) ? $cols['nombre'] : '';
        $usuario = isset($cols['usuario']) && is_string($cols['usuario']) ? $cols['usuario'] : '';
        $correo = isset($cols['correo']) && is_string($cols['correo']) ? $cols['correo'] : '';
        $partes = explode('@', $correo);
        $codigo = $usuario !== '' ? $usuario : $partes[0];

        return [
            'id' => $id,
            'nombre' => $nombre,
            'usuario' => $usuario,
            'codigo' => $codigo,
            'correo' => $correo,
        ];
    }

    public function obtenerPorId(int $auxiliarId): ?array
    {
        /** @var object{id: int, nombre: string, usuario: string, correo: string}|null $fila */
        $fila = DB::table('usuarios as u')
            ->join('roles as r', 'r.id', '=', 'u.role_id')
            ->where('u.id', $auxiliarId)
            ->where('r.name', 'Auxiliar')
            ->where('u.is_active', true)
            ->first(['u.id', 'u.nombre', 'u.usuario', 'u.correo']);

        if ($fila === null) {
            return null;
        }

        return [
            'id' => (int) $fila->id,
            'nombre' => (string) $fila->nombre,
            'usuario' => (string) $fila->usuario,
            'correo' => (string) $fila->correo,
        ];
    }

    public function obtenerUsuario(int $usuarioId): ?array
    {
        /** @var object{id: int, nombre: string, usuario: string}|null $fila */
        $fila = DB::table('usuarios')
            ->where('id', $usuarioId)
            ->first(['id', 'nombre', 'usuario']);

        if ($fila === null) {
            return null;
        }

        return [
            'id' => (int) $fila->id,
            'nombre' => (string) $fila->nombre,
            'usuario' => (string) $fila->usuario,
        ];
    }
}
