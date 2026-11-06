<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\ConsultaRespaldosGateway;
use App\Modules\Administracion\Application\Contracts\RestauradorRespaldoGateway;
use App\Modules\Administracion\Application\DTOs\RespaldoData;
use App\Modules\Administracion\Domain\Exceptions\ArchivoRespaldoNoEncontradoException;
use App\Modules\Administracion\Domain\Exceptions\RespaldoFallidoException;
use App\Modules\Administracion\Domain\Exceptions\RespaldoIncompatibleException;
use App\Modules\Administracion\Domain\Exceptions\RespaldoNoEncontradoException;
use App\Modules\Administracion\Domain\Models\Respaldo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * HU-25: Implementación Eloquent para la restauración y descarga de respaldos.
 */
final class EloquentRestauradorRespaldoGateway implements RestauradorRespaldoGateway
{
    public function __construct(
        private readonly ConsultaRespaldosGateway $consultas,
    ) {}

    public function obtenerArchivo(int $respaldoId): array
    {
        /** @var Respaldo|null $respaldo */
        $respaldo = Respaldo::find($respaldoId);

        if ($respaldo === null) {
            throw new RespaldoNoEncontradoException('Respaldo no encontrado.');
        }

        if (strtolower((string) $respaldo->estado) === 'fallido') {
            throw new RespaldoFallidoException('Ese respaldo falló: no hay archivo que descargar.', $respaldo->id);
        }

        $discoNombre = is_string(config('respaldo.disco')) ? config('respaldo.disco') : 'local';
        $directorio = is_string(config('respaldo.directorio')) ? config('respaldo.directorio') : 'respaldos';
        $ruta = trim($directorio, '/').'/'.$respaldo->archivo;

        $disco = Storage::disk($discoNombre);

        if (! $disco->exists($ruta)) {
            throw new ArchivoRespaldoNoEncontradoException('El archivo de ese respaldo ya no está en disco.');
        }

        $contenido = $disco->get($ruta);

        if (! is_string($contenido)) {
            throw new ArchivoRespaldoNoEncontradoException('El archivo de ese respaldo ya no está en disco.');
        }

        return [
            'nombre' => $respaldo->archivo,
            'contenido' => $contenido,
        ];
    }

    public function restaurar(int $respaldoId): RespaldoData
    {
        /** @var Respaldo|null $respaldo */
        $respaldo = Respaldo::find($respaldoId);

        if ($respaldo === null) {
            throw new RespaldoNoEncontradoException('Respaldo no encontrado.');
        }

        if (strtolower((string) $respaldo->estado) === 'fallido') {
            throw new RespaldoFallidoException('Ese respaldo falló: no se puede restaurar.', $respaldo->id);
        }

        $versionActual = $this->versionActual();

        if ($respaldo->version_esquema !== $versionActual) {
            $respaldo->estado = 'incompatible';
            $respaldo->error = 'Versión de esquema incompatible: '.$respaldo->version_esquema.' (actual: '.$versionActual.')';
            $respaldo->save();

            throw new RespaldoIncompatibleException(
                'El respaldo corresponde a una versión anterior incompatible.',
                $respaldo->id
            );
        }

        $discoNombre = is_string(config('respaldo.disco')) ? config('respaldo.disco') : 'local';
        $directorio = is_string(config('respaldo.directorio')) ? config('respaldo.directorio') : 'respaldos';
        $ruta = trim($directorio, '/').'/'.$respaldo->archivo;

        $disco = Storage::disk($discoNombre);

        if (! $disco->exists($ruta)) {
            throw new ArchivoRespaldoNoEncontradoException('El archivo de ese respaldo ya no está en disco.');
        }

        $sql = $disco->get($ruta);

        if (! is_string($sql) || trim($sql) === '') {
            throw new RespaldoFallidoException('El archivo de respaldo está vacío o corrupto.', $respaldo->id);
        }

        try {
            DB::unprepared($sql);
        } catch (Throwable $error) {
            if (DB::connection()->getDriverName() === 'pgsql') {
                try {
                    DB::unprepared("SET session_replication_role = 'origin';");
                } catch (Throwable) {
                    // ignorar
                }
            }

            throw new RuntimeException(
                'Error al ejecutar la restauración SQL: '.$error->getMessage(),
                0,
                $error
            );
        }

        // Marcar previos como correcto y el actual como restaurado (Criterio 8)
        Respaldo::where('estado', 'restaurado')
            ->where('id', '!=', $respaldo->id)
            ->update(['estado' => 'correcto']);

        $respaldo->estado = 'restaurado';
        $respaldo->error = null;
        $respaldo->save();

        $datos = $this->consultas->buscar($respaldo->id);

        if ($datos === null) {
            throw new RespaldoNoEncontradoException('No se pudo recuperar el respaldo restaurado.');
        }

        return $datos;
    }

    private function versionActual(): string
    {
        $versionConfig = config('respaldo.version_esquema');
        if (is_string($versionConfig) && $versionConfig !== '') {
            return $versionConfig;
        }

        $ultima = DB::table('migrations')->orderByDesc('id')->value('migration');

        return is_string($ultima) ? $ultima : '2026_10_28_000100_create_respaldos_table';
    }
}
