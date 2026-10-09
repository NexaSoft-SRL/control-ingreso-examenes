<?php

declare(strict_types=1);

namespace App\Modules\Academico\Infrastructure\Persistence;

use App\Modules\Academico\Application\Contracts\ImportacionGateway;
use App\Modules\Academico\Application\DTOs\ResumenImportacionData;
use App\Modules\Academico\Domain\Enums\EstadoImportacion;
use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use Closure;
use Illuminate\Support\Facades\DB;
use stdClass;

final class EloquentImportacionGateway implements ImportacionGateway
{
    public function __construct(
        private readonly BitacoraGateway $bitacora,
    ) {}

    public function facultad(string $clave): ?array
    {
        $fila = DB::table('facultades')->where('clave', $clave)->first();

        if (! $fila instanceof stdClass || ! is_numeric($fila->id)) {
            return null;
        }

        return [
            'id' => (int) $fila->id,
            'clave' => $clave,
            'sigla' => is_string($fila->sigla) ? $fila->sigla : mb_strtoupper($clave),
            'codigo_umss' => is_string($fila->codigo_umss) ? $fila->codigo_umss : '',
        ];
    }

    public function enCurso(int $facultadId, int $minutos = 5): bool
    {
        return DB::table('importaciones_oferta')
            ->where('facultad_id', $facultadId)
            ->where('estado', EstadoImportacion::Importando->value)
            ->where('iniciada_en', '>=', now()->subMinutes($minutos))
            ->exists();
    }

    public function iniciar(int $facultadId, ?int $usuarioId): int
    {
        return (int) DB::table('importaciones_oferta')->insertGetId([
            'facultad_id' => $facultadId,
            'estado' => EstadoImportacion::Importando->value,
            'ejecutada_por' => $usuarioId,
            'iniciada_en' => now(),
        ]);
    }

    public function enTransaccion(Closure $operacion): mixed
    {
        return DB::transaction($operacion);
    }

    public function completar(int $importacionId, ResumenImportacionData $resumen, ?int $usuarioId): void
    {
        DB::table('importaciones_oferta')->where('id', $importacionId)->update([
            'estado' => EstadoImportacion::Importada->value,
            'periodo_codigo' => $resumen->periodoCodigo,
            'fecha_fuente' => $resumen->fechaFuente,
            'resumen' => json_encode($resumen->resumen(), JSON_THROW_ON_ERROR),
            'error' => null,
            'terminada_en' => now(),
        ]);

        $this->bitacora->registrar(
            $usuarioId,
            'oferta.importar',
            'importaciones_oferta',
            $importacionId,
            sprintf(
                'Oferta de %s importada: %d carreras, %d asignaturas, %d grupos, %d docentes.',
                $resumen->sigla,
                $resumen->carreras,
                $resumen->asignaturas,
                $resumen->grupos,
                $resumen->docentes,
            ),
        );
    }

    public function fallar(int $importacionId, string $error): void
    {
        DB::table('importaciones_oferta')->where('id', $importacionId)->update([
            'estado' => EstadoImportacion::Fallo->value,
            'error' => mb_substr($error, 0, 2000),
            'terminada_en' => now(),
        ]);
    }
}
