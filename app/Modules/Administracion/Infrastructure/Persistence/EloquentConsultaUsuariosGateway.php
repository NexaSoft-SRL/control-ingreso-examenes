<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\ConsultaUsuariosGateway;
use App\Modules\Administracion\Application\DTOs\FiltroUsuariosData;
use App\Modules\Administracion\Application\DTOs\PaginaUsuariosData;
use App\Modules\Administracion\Application\DTOs\UsuarioConRolData;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentConsultaUsuariosGateway implements ConsultaUsuariosGateway
{
    /**
     * Para buscar sin distinguir mayusculas ni tildes.
     */
    private const CON_TILDE = 'áéíóúüñ';

    private const SIN_TILDE = 'aeiouun';

    public function listar(FiltroUsuariosData $filtro): PaginaUsuariosData
    {
        $consulta = $this->base();

        if ($filtro->buscar !== null && trim($filtro->buscar) !== '') {
            $patron = '%'.$this->normalizar($filtro->buscar).'%';

            $consulta->where(function (Builder $donde) use ($patron): void {
                foreach (['usuario.nombre', 'usuario.usuario', 'usuario.correo'] as $columna) {
                    $donde->orWhereRaw(
                        sprintf(
                            "translate(lower(coalesce(%s, '')), '%s', '%s') like ?",
                            $columna,
                            self::CON_TILDE,
                            self::SIN_TILDE,
                        ),
                        [$patron],
                    );
                }
            });
        }

        if ($filtro->rol !== null) {
            $consulta->where('rol.name', $filtro->rol);
        }

        if ($filtro->soloBloqueadas) {
            $consulta->where('usuario.is_active', false);
        }

        $total = $consulta->count();

        $filas = $consulta
            ->orderByDesc('usuario.created_at')
            ->orderByDesc('usuario.id')
            ->forPage($filtro->pagina, $filtro->porPagina)
            ->get();

        $cuentas = [];

        foreach ($filas as $fila) {
            $cuentas[] = $this->cuenta(get_object_vars($fila));
        }

        return new PaginaUsuariosData(
            filas: $cuentas,
            total: $total,
            pagina: $filtro->pagina,
            porPagina: $filtro->porPagina,
            conteos: $this->conteos(),
            cuentas: DB::table('usuarios')->count(),
        );
    }

    public function buscar(int $usuarioId): ?UsuarioConRolData
    {
        $fila = $this->base()->where('usuario.id', $usuarioId)->first();

        return $fila === null ? null : $this->cuenta(get_object_vars($fila));
    }

    private function base(): Builder
    {
        return DB::table('usuarios as usuario')
            ->leftJoin('roles as rol', 'rol.id', '=', 'usuario.role_id')
            ->select([
                'usuario.id',
                'usuario.nombre',
                'usuario.usuario',
                'usuario.correo',
                'usuario.is_active',
                'rol.name as rol',
            ]);
    }

    /**
     * Cuentas de cada rol existente (los de inicio primero) y bloqueadas,
     * sobre todas las cuentas: son las cifras de los filtros y no cambian
     * al filtrar.
     *
     * @return array<string, int>
     */
    private function conteos(): array
    {
        $conteos = [];

        $porRol = DB::table('roles as rol')
            ->leftJoin('usuarios as usuario', 'usuario.role_id', '=', 'rol.id')
            ->groupBy('rol.id', 'rol.name', 'rol.es_sistema')
            ->orderByDesc('rol.es_sistema')
            ->orderBy('rol.id')
            ->selectRaw('rol.name as rol, count(usuario.id) as cuentas')
            ->get();

        foreach ($porRol as $fila) {
            $datos = get_object_vars($fila);
            $rol = $this->texto($datos['rol'] ?? null);

            if ($rol !== null) {
                $conteos[$rol] = $this->entero($datos['cuentas'] ?? null);
            }
        }

        $conteos['bloqueadas'] = DB::table('usuarios')->where('is_active', false)->count();

        return $conteos;
    }

    /**
     * @param  array<mixed>  $fila
     */
    private function cuenta(array $fila): UsuarioConRolData
    {
        return new UsuarioConRolData(
            id: $this->entero($fila['id'] ?? null),
            nombre: $this->texto($fila['nombre'] ?? null) ?? '',
            usuario: $this->texto($fila['usuario'] ?? null) ?? '',
            correo: $this->texto($fila['correo'] ?? null),
            activo: (bool) ($fila['is_active'] ?? false),
            rol: $this->texto($fila['rol'] ?? null),
        );
    }

    /**
     * El texto buscado, en minusculas, sin tildes y con los comodines de
     * LIKE desactivados.
     */
    private function normalizar(string $texto): string
    {
        $texto = strtr(mb_strtolower(trim($texto)), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        return addcslashes($texto, '%_\\');
    }

    private function texto(mixed $valor): ?string
    {
        return is_string($valor) && $valor !== '' ? $valor : null;
    }

    private function entero(mixed $valor): int
    {
        return is_numeric($valor) ? (int) $valor : 0;
    }
}
