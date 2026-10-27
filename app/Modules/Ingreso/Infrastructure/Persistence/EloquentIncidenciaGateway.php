<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Ingreso\Application\Contracts\IncidenciaGateway;
use App\Modules\Ingreso\Application\DTOs\NuevaIncidenciaData;
use App\Modules\Ingreso\Domain\Enums\OrigenIncidencia;
use App\Modules\Ingreso\Domain\Enums\TipoIncidencia;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Las incidencias en un solo modelo (`incidencias`). Las del sistema son
 * los intentos que rechaza la puerta: solo el ingreso repetido se lista y
 * se atiende; el resto alimenta los informes de intentos.
 */
final class EloquentIncidenciaGateway implements IncidenciaGateway
{
    use LeeFilas;

    private const UNICO_EXPULSION = 'uq_expulsion_por_ingreso';

    public function __construct(
        private readonly BitacoraGateway $bitacora,
    ) {}

    public function registrar(NuevaIncidenciaData $datos, ?string $operacion = null): ?int
    {
        try {
            return DB::transaction(function () use ($datos, $operacion): int {
                $ahora = now();

                $id = DB::table('incidencias')->insertGetId([
                    'examen_id' => $datos->examenId,
                    'aula_id' => $datos->aulaId,
                    'estudiante_id' => $datos->estudianteId,
                    'estudiante_texto' => $datos->estudianteTexto,
                    'ingreso_id' => $datos->ingresoId,
                    'tipo' => $datos->tipo->value,
                    'origen' => $datos->origen->value,
                    'causa' => $datos->causa?->value,
                    'detalle' => trim($datos->detalle),
                    'evidencia' => $datos->evidencia,
                    'ocurrida_en' => $ahora,
                    'reportada_por' => $datos->operador->usuarioId,
                    'acceso_codigo_aula_id' => $datos->operador->accesoId,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);

                if ($operacion !== null) {
                    $this->bitacora->registrar(
                        $datos->operador->usuarioId,
                        $operacion,
                        'incidencias',
                        $id,
                        sprintf(
                            '%s%s%s%s: %s',
                            $datos->tipo->etiqueta(),
                            $this->dondeOcurrio($datos->examenId, $datos->aulaId),
                            $datos->estudianteTexto === null ? '' : ' · '.$datos->estudianteTexto,
                            $datos->operador->esAcceso()
                                ? sprintf(', con código de aula (%s)', $datos->operador->nombre ?? '—')
                                : '',
                            trim($datos->detalle),
                        ),
                    );
                }

                return $id;
            }, 3);
        } catch (QueryException $error) {
            if (
                ($error->errorInfo[0] ?? null) === '23505'
                && str_contains($error->getMessage(), self::UNICO_EXPULSION)
            ) {
                return null;
            }

            throw $error;
        }
    }

    public function listar(int $examenId, ?int $aulaId, bool $soloSinAtender): array
    {
        $base = $this->consulta()->where('incidencia.examen_id', $examenId);

        if ($aulaId !== null) {
            $base->where('incidencia.aula_id', $aulaId);
        }

        $todas = (clone $base)->count();
        $sinAtender = (clone $base)->whereNull('incidencia.atendida_en')->count();

        if ($soloSinAtender) {
            $base->whereNull('incidencia.atendida_en');
        }

        $data = [];

        foreach ($base->orderByDesc('incidencia.ocurrida_en')->orderByDesc('incidencia.id')->get() as $fila) {
            $incidencia = $this->filaDe($this->columnas($fila));
            unset($incidencia['examen_id']);
            $data[] = $incidencia;
        }

        return ['data' => $data, 'sin_atender' => $sinAtender, 'todas' => $todas];
    }

    public function reportada(int $incidenciaId): ?array
    {
        $fila = $this->consulta()->where('incidencia.id', $incidenciaId)->first();

        return $fila === null ? null : $this->filaDe($this->columnas($fila));
    }

    public function atender(int $incidenciaId, int $usuarioId): bool
    {
        return DB::transaction(function () use ($incidenciaId, $usuarioId): bool {
            $ahora = now();

            // Atomico: de dos que atienden a la vez, uno solo la cambia.
            $cambiadas = DB::table('incidencias')
                ->where('id', $incidenciaId)
                ->whereNull('atendida_en')
                ->update([
                    'atendida_en' => $ahora,
                    'atendida_por' => $usuarioId,
                    'updated_at' => $ahora,
                ]);

            if ($cambiadas === 0) {
                return false;
            }

            $fila = DB::table('incidencias')->where('id', $incidenciaId)->first(['tipo', 'examen_id', 'aula_id']);
            $columnas = $fila === null ? [] : $this->columnas($fila);

            $this->bitacora->registrar(
                $usuarioId,
                'incidencia.atender',
                'incidencias',
                $incidenciaId,
                sprintf(
                    'Atendió: %s%s.',
                    TipoIncidencia::tryFrom($this->texto($columnas['tipo'] ?? null) ?? '')?->etiqueta() ?? 'Incidencia',
                    $this->dondeOcurrio(
                        $this->enteroONulo($columnas['examen_id'] ?? null),
                        $this->enteroONulo($columnas['aula_id'] ?? null),
                    ),
                ),
            );

            return true;
        }, 3);
    }

    public function corregir(
        int $incidenciaId,
        TipoIncidencia $tipo,
        string $detalle,
        ?string $evidencia,
        int $usuarioId,
        ?array $estudiante = null,
    ): ?string {
        return DB::transaction(function () use ($incidenciaId, $tipo, $detalle, $evidencia, $usuarioId, $estudiante): ?string {
            $ahora = now();
            $previa = DB::table('incidencias')->where('id', $incidenciaId)->lockForUpdate()->value('evidencia');

            $cambios = array_filter([
                'tipo' => $tipo->value,
                'detalle' => trim($detalle),
                'evidencia' => $evidencia,
                'corregida_en' => $ahora,
                'corregida_por' => $usuarioId,
                'updated_at' => $ahora,
            ], static fn (mixed $valor): bool => $valor !== null);

            if ($estudiante !== null) {
                $cambios['estudiante_texto'] = $estudiante['texto'];
                $cambios['estudiante_id'] = $estudiante['id'];
            }

            DB::table('incidencias')->where('id', $incidenciaId)->update($cambios);

            $this->bitacora->registrar(
                $usuarioId,
                'incidencia.corregir',
                'incidencias',
                $incidenciaId,
                sprintf('Corrigió: %s · %s', $tipo->etiqueta(), trim($detalle)),
            );

            return $evidencia !== null && is_string($previa) ? $previa : null;
        }, 3);
    }

    public function evidencia(int $incidenciaId): ?string
    {
        $ruta = DB::table('incidencias')->where('id', $incidenciaId)->value('evidencia');

        return is_string($ruta) && $ruta !== '' ? $ruta : null;
    }

    public function estudianteDelExamen(int $examenId, string $identificador): ?int
    {
        $valor = trim($identificador);
        $normalizado = mb_strtoupper(str_replace([' ', '-'], '', $valor));

        $id = DB::table('estudiantes as e')
            ->where(static function (Builder $donde) use ($valor, $normalizado): void {
                $donde->where('e.codigo_universitario', $valor)
                    ->orWhereRaw("upper(replace(replace(e.documento_identidad, ' ', ''), '-', '')) = ?", [$normalizado]);
            })
            ->whereExists(static function (Builder $inscrito) use ($examenId): void {
                $inscrito->selectRaw('1')
                    ->from('inscripciones as i')
                    ->join('examen_grupo as eg', 'eg.grupo_id', '=', 'i.grupo_id')
                    ->whereColumn('i.estudiante_id', 'e.id')
                    ->where('eg.examen_id', $examenId);
            })
            ->orderBy('e.id')
            ->value('e.id');

        return is_numeric($id) ? (int) $id : null;
    }

    public function examenTieneAula(int $examenId, int $aulaId): bool
    {
        return DB::table('examen_aula')
            ->where('examen_id', $examenId)
            ->where('aula_id', $aulaId)
            ->exists();
    }

    public function fechaDelExamen(int $examenId): ?string
    {
        $fecha = DB::table('examenes')->where('id', $examenId)->value('fecha');

        return is_string($fecha) ? substr($fecha, 0, 10) : null;
    }

    // ' en Cálculo I, aula 691A'; vacio si no hay examen ni aula.
    private function dondeOcurrio(?int $examenId, ?int $aulaId): string
    {
        $asignatura = $examenId === null ? null : $this->texto(DB::table('examenes')
            ->join('asignaturas', 'asignaturas.id', '=', 'examenes.asignatura_id')
            ->where('examenes.id', $examenId)
            ->value('asignaturas.nombre'));
        $aula = $aulaId === null ? null : $this->texto(DB::table('aulas')->where('id', $aulaId)->value('nombre'));

        return implode(', ', array_filter([
            $asignatura === null ? null : ' en '.$asignatura,
            $aula === null ? null : ($asignatura === null ? ' en el aula ' : 'aula ').$aula,
        ]));
    }

    /**
     * Las que reporto una persona y los ingresos repetidos que asienta la
     * puerta.
     */
    private function consulta(): Builder
    {
        return DB::table('incidencias as incidencia')
            ->leftJoin('aulas as aula', 'aula.id', '=', 'incidencia.aula_id')
            ->leftJoin('estudiantes as estudiante', 'estudiante.id', '=', 'incidencia.estudiante_id')
            ->leftJoin('usuarios as autor', 'autor.id', '=', 'incidencia.reportada_por')
            ->leftJoin('roles as rol', 'rol.id', '=', 'autor.role_id')
            ->leftJoin('accesos_codigo_aula as acceso', 'acceso.id', '=', 'incidencia.acceso_codigo_aula_id')
            ->leftJoin('usuarios as atendio', 'atendio.id', '=', 'incidencia.atendida_por')
            ->leftJoin('usuarios as corrigio', 'corrigio.id', '=', 'incidencia.corregida_por')
            ->where(static function (Builder $donde): void {
                $donde->where('incidencia.origen', OrigenIncidencia::Persona->value)
                    ->orWhere('incidencia.tipo', TipoIncidencia::IngresoRepetido->value);
            })
            ->select([
                'incidencia.id',
                'incidencia.examen_id',
                'incidencia.aula_id',
                'incidencia.tipo',
                'incidencia.detalle',
                'incidencia.estudiante_texto',
                'incidencia.ocurrida_en',
                'incidencia.atendida_en',
                'incidencia.evidencia',
                'incidencia.corregida_en',
                'aula.nombre as aula',
                'estudiante.codigo_universitario',
                'estudiante.nombres',
                'estudiante.apellidos',
                'autor.nombre as autor',
                'rol.name as rol',
                'acceso.nombre_colaborador as acceso',
                'atendio.nombre as atendio',
                'corrigio.nombre as corrigio',
            ]);
    }

    /**
     * @param  array<string, mixed>  $columnas
     * @return array<string, mixed>
     */
    private function filaDe(array $columnas): array
    {
        $tipo = $this->texto($columnas['tipo'] ?? null) ?? '';
        $codigo = $this->texto($columnas['codigo_universitario'] ?? null);
        $acceso = $this->texto($columnas['acceso'] ?? null);
        $autor = $this->texto($columnas['autor'] ?? null);
        $atendidaEn = $this->texto($columnas['atendida_en'] ?? null);
        $corregidaEn = $this->texto($columnas['corregida_en'] ?? null);
        $gravedad = TipoIncidencia::tryFrom($tipo)?->gravedad();

        // «Apellidos Nombres · código», o lo que escribio quien reporto.
        $estudiante = $codigo === null
            ? $this->texto($columnas['estudiante_texto'] ?? null)
            : trim(($this->texto($columnas['apellidos'] ?? null) ?? '').' '.($this->texto($columnas['nombres'] ?? null) ?? '')).' · '.$codigo;

        return [
            'id' => $this->entero($columnas['id'] ?? null),
            'examen_id' => $this->enteroONulo($columnas['examen_id'] ?? null),
            'tipo' => $tipo,
            'tipo_texto' => TipoIncidencia::tryFrom($tipo)?->etiqueta() ?? $tipo,
            'gravedad' => $gravedad?->value,
            'gravedad_texto' => $gravedad?->etiqueta(),
            'aula_id' => $this->enteroONulo($columnas['aula_id'] ?? null),
            'aula' => $this->texto($columnas['aula'] ?? null),
            'estudiante' => $estudiante,
            'detalle' => $this->texto($columnas['detalle'] ?? null),
            'hora' => substr($this->texto($columnas['ocurrida_en'] ?? null) ?? '', 11, 5),
            'ocurrida_en' => $this->iso($this->texto($columnas['ocurrida_en'] ?? null)),
            'por' => $autor ?? $acceso,
            'rol' => $autor === null && $acceso !== null
                ? 'Código de aula'
                : $this->texto($columnas['rol'] ?? null),
            'atendida' => $atendidaEn !== null,
            'atendida_por' => $atendidaEn === null ? null : $this->texto($columnas['atendio'] ?? null),
            'atendida_en' => $this->iso($atendidaEn),
            'evidencia' => $this->texto($columnas['evidencia'] ?? null) !== null,
            'corregida' => $corregidaEn !== null,
            'corregida_por' => $corregidaEn === null ? null : $this->texto($columnas['corrigio'] ?? null),
        ];
    }
}
