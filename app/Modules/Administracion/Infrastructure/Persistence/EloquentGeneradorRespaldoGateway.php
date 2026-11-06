<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\ConsultaRespaldosGateway;
use App\Modules\Administracion\Application\Contracts\ExportadorBaseDeDatos;
use App\Modules\Administracion\Application\Contracts\GeneradorRespaldoGateway;
use App\Modules\Administracion\Application\DTOs\RespaldoData;
use App\Modules\Administracion\Domain\Exceptions\RespaldoFallidoException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * HU-25: Generador de respaldos en disco seguro y registro en la base de datos.
 */
final readonly class EloquentGeneradorRespaldoGateway implements GeneradorRespaldoGateway
{
    public function __construct(
        private ExportadorBaseDeDatos $exportador,
        private ConsultaRespaldosGateway $consultas,
    ) {}

    public function generar(string $tipo, ?int $usuarioId = null): RespaldoData
    {
        $zona = is_string(config('umss.zona_horaria')) ? config('umss.zona_horaria') : 'America/La_Paz';
        $timestamp = now($zona)->format('Ymd-His');
        $base = "respaldo-{$timestamp}";
        $nombreArchivo = "{$base}.sql";

        $directorioConfig = config('respaldo.directorio');
        $directorio = is_string($directorioConfig) ? $directorioConfig : 'respaldos';

        $discoConfig = config('respaldo.disco');
        $discoNombre = is_string($discoConfig) ? $discoConfig : 'local';
        $disco = Storage::disk($discoNombre);

        $contador = 1;
        while (
            DB::table('respaldos')->where('archivo', $nombreArchivo)->exists()
            || $disco->exists("{$directorio}/{$nombreArchivo}")
        ) {
            $nombreArchivo = sprintf('%s_%d.sql', $base, $contador++);
        }

        $versionConfig = config('respaldo.version_esquema');
        $versionEsquema = is_string($versionConfig) ? $versionConfig : null;
        if ($versionEsquema === null) {
            $ultima = DB::table('migrations')->orderByDesc('id')->value('migration');
            $versionEsquema = is_string($ultima) ? $ultima : '2026_10_28_000100_create_respaldos_table';
        }

        try {
            $contenidoSql = $this->exportador->exportar($versionEsquema, $tipo);
            $ruta = "{$directorio}/{$nombreArchivo}";

            $guardado = $disco->put($ruta, $contenidoSql);
            if (! $guardado) {
                throw new RuntimeException("No se pudo escribir el archivo {$nombreArchivo} en disco seguro.");
            }

            $tamanoBytes = strlen($contenidoSql);
            $ahora = now($zona)->format('Y-m-d H:i:s');

            $id = (int) DB::table('respaldos')->insertGetId([
                'archivo' => $nombreArchivo,
                'tipo' => $tipo,
                'tamano_bytes' => $tamanoBytes,
                'estado' => 'correcto',
                'error' => null,
                'version_esquema' => $versionEsquema,
                'usuario_id' => $usuarioId,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);

            $respaldo = $this->consultas->buscar($id);
            if ($respaldo === null) {
                throw new RuntimeException("No se pudo recuperar el respaldo con id {$id}.");
            }

            return $respaldo;
        } catch (Throwable $error) {
            $idFallo = null;

            try {
                $ahora = now($zona)->format('Y-m-d H:i:s');
                $idFallo = (int) DB::table('respaldos')->insertGetId([
                    'archivo' => $nombreArchivo,
                    'tipo' => $tipo,
                    'tamano_bytes' => 0,
                    'estado' => 'fallido',
                    'error' => $error->getMessage(),
                    'version_esquema' => $versionEsquema,
                    'usuario_id' => $usuarioId,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            } catch (Throwable) {
                // Preservar la excepción original si la inserción de fallo no responde
            }

            throw new RespaldoFallidoException(
                $error->getMessage(),
                $idFallo,
                $error
            );
        }
    }
}
