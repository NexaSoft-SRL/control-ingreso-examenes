<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Examenes\Application\Contracts\CodigoAulaGateway;
use App\Modules\Examenes\Application\DTOs\CodigoAulaData;
use App\Modules\Examenes\Domain\Models\AccesoAula;
use App\Modules\Examenes\Domain\Models\ExamenAula;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EloquentCodigoAulaGateway implements CodigoAulaGateway
{
    use ConvierteFilas;

    /**
     * Zona en que se informan los instantes al cliente.
     */
    private const INTENTOS = 20;

    /**
     * SQLSTATE de una violacion de indice unico.
     */
    private const VIOLACION_DE_UNICO = '23505';

    public function __construct(
        private readonly BitacoraGateway $bitacora,
    ) {}

    public function consultar(int $examenId, int $aulaId): ?CodigoAulaData
    {
        $fila = DB::table('examen_aula as asignada')
            ->join('examenes as examen', 'examen.id', '=', 'asignada.examen_id')
            ->join('users as encargado', 'encargado.id', '=', 'asignada.encargado_id')
            ->leftJoin('docentes as docente', 'docente.user_id', '=', 'encargado.id')
            ->where('asignada.examen_id', $examenId)
            ->where('asignada.aula_id', $aulaId)
            ->first([
                'asignada.id',
                'asignada.codigo_acceso',
                'examen.fecha',
                'examen.hora_inicio',
                'encargado.name as encargado',
                'docente.nombre_completo as encargado_docente',
            ]);

        if ($fila === null) {
            return null;
        }

        $columnas = $this->columnas($fila);
        $asignada = ExamenAula::find($this->entero($columnas['id'] ?? null));

        return new CodigoAulaData(
            codigo: $this->texto($columnas['codigo_acceso'] ?? null),
            generadoEn: $asignada?->codigo_generado_en?->toIso8601String(),
            encargado: $this->texto($columnas['encargado_docente'] ?? null)
                ?? $this->cadena($columnas['encargado'] ?? null),
            examen: $this->fecha($columnas['fecha'] ?? null).' '.$this->hora($columnas['hora_inicio'] ?? null),
        );
    }

    public function generar(int $examenId, int $aulaId, int $usuarioId, bool $regenerar): ?CodigoAulaData
    {
        for ($intento = 0; $intento < self::INTENTOS; $intento++) {
            $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            if (ExamenAula::where('codigo_acceso', $codigo)->exists()) {
                continue;
            }

            try {
                $encontrada = $this->guardar($examenId, $aulaId, $usuarioId, $regenerar, $codigo);
            } catch (QueryException $error) {
                // Otro docente genero el mismo codigo a la vez: se prueba
                // con otro.
                if ($error->getCode() === self::VIOLACION_DE_UNICO) {
                    continue;
                }

                throw $error;
            }

            return $encontrada ? $this->consultar($examenId, $aulaId) : null;
        }

        throw new RuntimeException('No se pudo generar un código de aula libre.');
    }

    private function guardar(int $examenId, int $aulaId, int $usuarioId, bool $regenerar, string $codigo): bool
    {
        return DB::transaction(function () use ($examenId, $aulaId, $usuarioId, $regenerar, $codigo): bool {
            $asignada = ExamenAula::where('examen_id', $examenId)
                ->where('aula_id', $aulaId)
                ->lockForUpdate()
                ->first();

            if (! $asignada instanceof ExamenAula) {
                return false;
            }

            $anterior = $asignada->codigo_acceso;

            // Dos consultas a la vez: la segunda se queda con el codigo que
            // genero la primera.
            if ($anterior !== null && ! $regenerar) {
                return true;
            }

            $asignada->codigo_acceso = $codigo;
            $asignada->setAttribute('codigo_generado_en', now());
            $asignada->save();

            // El codigo anterior deja de servir: quien entro con el, sale.
            if ($anterior !== null) {
                AccesoAula::where('examen_aula_id', $asignada->id)
                    ->whereIn('estado', ['PENDIENTE', 'AUTORIZADO'])
                    ->update(['estado' => 'CORTADO', 'resuelto_por' => $usuarioId, 'resuelto_en' => now()]);
            }

            $this->bitacora->registrar(
                $usuarioId,
                $anterior === null ? 'codigo_aula.generar' : 'codigo_aula.regenerar',
                'examen_aula',
                $asignada->id,
                $anterior === null
                    ? 'Código de acceso del aula generado.'
                    : 'Código de acceso del aula regenerado: el anterior deja de servir y sus accesos se cierran.',
            );

            return true;
        }, 3);
    }
}
