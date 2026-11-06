<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\ConsultaRespaldosGateway;
use App\Modules\Administracion\Application\DTOs\FiltroRespaldosData;
use App\Modules\Administracion\Application\DTOs\PaginaRespaldosData;
use App\Modules\Administracion\Application\DTOs\RespaldoData;
use Illuminate\Support\Facades\DB;

final class EloquentConsultaRespaldosGateway implements ConsultaRespaldosGateway
{
    public function listar(FiltroRespaldosData $filtro): PaginaRespaldosData
    {
        $tabla = DB::table('respaldos');

        $conteos = [
            'todos' => (clone $tabla)->count(),
            'programados' => (clone $tabla)->where('tipo', 'programado')->count(),
            'puntuales' => (clone $tabla)->where('tipo', 'puntual')->count(),
            'fallidos' => (clone $tabla)->where('estado', 'fallido')->count(),
        ];

        $ultimoRegistro = (clone $tabla)
            ->whereIn('estado', ['correcto', 'restaurado'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('created_at');

        $ultimo = is_string($ultimoRegistro) ? $ultimoRegistro : null;

        $consulta = clone $tabla;

        if ($filtro->filtro === 'programados') {
            $consulta->where('tipo', 'programado');
        } elseif ($filtro->filtro === 'puntuales') {
            $consulta->where('tipo', 'puntual');
        } elseif ($filtro->filtro === 'fallidos') {
            $consulta->where('estado', 'fallido');
        }

        $total = $consulta->count();

        $filas = $consulta
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->forPage($filtro->pagina, $filtro->porPagina)
            ->get();

        $elementos = [];
        foreach ($filas as $fila) {
            $elementos[] = $this->respaldo(get_object_vars($fila));
        }

        return new PaginaRespaldosData(
            filas: $elementos,
            total: $total,
            pagina: $filtro->pagina,
            porPagina: $filtro->porPagina,
            conteos: $conteos,
            ultimo: $ultimo,
        );
    }

    public function buscar(int $id): ?RespaldoData
    {
        $fila = DB::table('respaldos')->where('id', $id)->first();

        if ($fila === null) {
            return null;
        }

        return $this->respaldo(get_object_vars($fila));
    }

    /**
     * @param  array<mixed>  $fila
     */
    private function respaldo(array $fila): RespaldoData
    {
        return new RespaldoData(
            id: $this->entero($fila['id'] ?? null),
            archivo: $this->texto($fila['archivo'] ?? null) ?? '',
            tipo: $this->texto($fila['tipo'] ?? null) ?? '',
            tamanoBytes: $this->entero($fila['tamano_bytes'] ?? null),
            estado: $this->texto($fila['estado'] ?? null) ?? '',
            error: $this->texto($fila['error'] ?? null),
            versionEsquema: $this->texto($fila['version_esquema'] ?? null),
            usuarioId: isset($fila['usuario_id']) && is_numeric($fila['usuario_id']) ? (int) $fila['usuario_id'] : null,
            fecha: $this->texto($fila['created_at'] ?? null) ?? '',
            createdAt: $this->texto($fila['created_at'] ?? null) ?? '',
        );
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
