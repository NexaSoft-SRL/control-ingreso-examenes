<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Ingreso\Application\Contracts\IncidenciaGateway;
use App\Modules\Ingreso\Application\DTOs\NuevaIncidenciaData;
use App\Modules\Ingreso\Domain\Enums\CausaIntento;
use App\Modules\Ingreso\Domain\Enums\Gravedad;
use App\Modules\Ingreso\Domain\Enums\OrigenIncidencia;
use App\Modules\Ingreso\Domain\Enums\TipoIncidencia;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

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
            /** @var int $id */
            $id = DB::transaction(function () use ($datos, $operacion): int {
                $ahora = now();

                $id = DB::table('incidencias')->insertGetId([
                    'examen_id' => $datos->examenId,
                    'aula_id' => $datos->aulaId,
                    'estudiante_id' => $datos->estudianteId,
                    'estudiante_texto' => $datos->estudianteTexto,
                    'ingreso_id' => $datos->ingresoId,
                    'tipo' => $datos->tipo->value,
                    'gravedad' => ($datos->gravedad ?? $datos->tipo->gravedad())->value,
                    'tipo_texto' => $datos->tipoTexto === null || trim($datos->tipoTexto) === '' ? null : trim($datos->tipoTexto),
                    'origen' => $datos->origen->value,
                    'causa' => $datos->causa?->value,
                    'examen_correspondiente_id' => $datos->examenCorrespondienteId,
                    'detalle' => $datos->detalle,
                    'ocurrida_en' => $ahora,
                    'reportada_por' => $datos->operador->usuarioId,
                    'acceso_aula_id' => $datos->operador->accesoAulaId,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);

                $this->agregarEvidencias($id, $datos->evidencias, $datos->operador->usuarioId, 0);

                if ($operacion !== null) {
                    $this->bitacora->registrar(
                        $datos->operador->usuarioId,
                        $operacion,
                        'incidencias',
                        $id,
                        sprintf(
                            '%s (%s)%s%s%s: %s',
                            $datos->tipoTexto !== null && trim($datos->tipoTexto) !== '' ? trim($datos->tipoTexto) : $datos->tipo->etiqueta(),
                            mb_strtolower(($datos->gravedad ?? $datos->tipo->gravedad())->etiqueta()),
                            $this->dondeOcurrio($datos->examenId, $datos->aulaId),
                            $datos->estudianteTexto === null ? '' : ' · '.$datos->estudianteTexto,
                            $datos->operador->esAcceso() ? ', con código de aula' : '',
                            $datos->detalle,
                        ),
                        $datos->operador->esAcceso() ? ($datos->operador->nombre ?? 'Código de aula') : null,
                    );
                }

                return $id;
            }, 3);
        } catch (QueryException $error) {
            if (
                $error->getCode() === '23505'
                && str_contains($error->getMessage(), self::UNICO_EXPULSION)
            ) {
                return null;
            }

            throw $error;
        }

        return $id;
    }

    /**
     * @return array{data: list<array<string, mixed>>, sin_atender: int, todas: int}
     */
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

    /**
     * @return array<string, mixed>|null
     */
    public function reportada(int $incidenciaId): ?array
    {
        $fila = $this->consulta()->where('incidencia.id', $incidenciaId)->first();

        return $fila === null ? null : $this->filaDe($this->columnas($fila));
    }

    public function atender(int $incidenciaId, int $usuarioId): bool
    {
        /** @var bool $atendida */
        $atendida = DB::transaction(function () use ($incidenciaId, $usuarioId): bool {
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

            $this->bitacora->registrar(
                $usuarioId,
                'incidencia.atender',
                'incidencias',
                $incidenciaId,
                sprintf('Incidencia %d atendida.', $incidenciaId),
            );

            return true;
        }, 3);

        return $atendida;
    }

    public function corregir(
        int $incidenciaId,
        TipoIncidencia $tipo,
        string $detalle,
        array $evidencias,
        int $usuarioId,
        ?array $estudiante = null,
        ?Gravedad $gravedad = null,
        ?string $tipoTexto = null,
    ): void {
        DB::transaction(function () use ($incidenciaId, $tipo, $detalle, $evidencias, $usuarioId, $estudiante, $gravedad, $tipoTexto): void {
            $ahora = now();
            DB::table('incidencias')->where('id', $incidenciaId)->lockForUpdate()->value('id');

            $cambios = [
                'tipo' => $tipo->value,
                'detalle' => $detalle,
                'corregida_en' => $ahora,
                'corregida_por' => $usuarioId,
                'updated_at' => $ahora,
            ];

            if ($gravedad !== null) {
                $cambios['gravedad'] = $gravedad->value;
            }

            // Un tipo del sistema no lleva nombre propio; una no prevista, el suyo.
            if ($tipo !== TipoIncidencia::Otra) {
                $cambios['tipo_texto'] = null;
            } elseif ($tipoTexto !== null) {
                $cambios['tipo_texto'] = trim($tipoTexto) === '' ? null : trim($tipoTexto);
            }

            if ($estudiante !== null) {
                $cambios['estudiante_texto'] = $estudiante['texto'];
                $cambios['estudiante_id'] = $estudiante['id'];
            }

            DB::table('incidencias')->where('id', $incidenciaId)->update($cambios);
            $this->agregarEvidencias($incidenciaId, $evidencias, $usuarioId, $this->cuantasEvidencias($incidenciaId));

            $this->bitacora->registrar(
                $usuarioId,
                'incidencia.corregir',
                'incidencias',
                $incidenciaId,
                sprintf(
                    'Incidencia %d corregida: %s%s. %s%s',
                    $incidenciaId,
                    $tipo === TipoIncidencia::Otra && $tipoTexto !== null && trim($tipoTexto) !== '' ? trim($tipoTexto) : $tipo->etiqueta(),
                    $gravedad === null ? '' : ' ('.mb_strtolower($gravedad->etiqueta()).')',
                    $detalle,
                    $evidencias === [] ? '' : sprintf(' (%d %s de evidencia)', count($evidencias), count($evidencias) === 1 ? 'foto' : 'fotos'),
                ),
            );
        }, 3);
    }

    public function cuantasEvidencias(int $incidenciaId): int
    {
        return DB::table('evidencias_incidencia')->where('incidencia_id', $incidenciaId)->count();
    }

    /**
     * Las fotos van despues de las `$hay` que ya tenia, en orden.
     *
     * @param  list<string>  $rutas
     */
    private function agregarEvidencias(int $incidenciaId, array $rutas, ?int $usuarioId, int $hay): void
    {
        $ahora = now();

        foreach ($rutas as $i => $ruta) {
            DB::table('evidencias_incidencia')->insert([
                'incidencia_id' => $incidenciaId,
                'ruta' => $ruta,
                'orden' => $hay + $i + 1,
                'subida_por' => $usuarioId,
                'created_at' => $ahora,
            ]);
        }
    }

    public function repetidoReciente(int $ingresoId, int $aulaId, int $minutos): bool
    {
        return DB::table('incidencias')
            ->where('tipo', TipoIncidencia::IngresoRepetido->value)
            ->where('ingreso_id', $ingresoId)
            ->where('aula_id', $aulaId)
            ->where('ocurrida_en', '>=', now()->subMinutes($minutos))
            ->exists();
    }

    // ' en Calculo I, aula 691A'; vacio si no hay examen ni aula.
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

    public function intentoReciente(int $estudianteId, int $aulaId, CausaIntento $causa, int $minutos): bool
    {
        return DB::table('incidencias')
            ->where('origen', OrigenIncidencia::Sistema->value)
            ->where('estudiante_id', $estudianteId)
            ->where('aula_id', $aulaId)
            ->where('causa', $causa->value)
            ->where('ocurrida_en', '>=', now()->subMinutes($minutos))
            ->exists();
    }

    public function ponerVideo(int $incidenciaId, string $ruta, string $tipo, ?int $usuarioId): bool
    {
        $puesto = DB::table('incidencias')
            ->where('id', $incidenciaId)
            ->whereNull('video')
            ->update(['video' => $ruta, 'video_tipo' => $tipo, 'updated_at' => now()]) > 0;

        if ($puesto) {
            $this->bitacora->registrar($usuarioId, 'incidencia.video', 'incidencias', $incidenciaId, sprintf('Video de evidencia en la incidencia %d.', $incidenciaId));
        }

        return $puesto;
    }

    public function video(int $incidenciaId): ?array
    {
        $fila = DB::table('incidencias')->where('id', $incidenciaId)->first(['video', 'video_tipo']);
        $c = $fila === null ? [] : $this->columnas($fila);
        $ruta = $this->texto($c['video'] ?? null);
        $tipo = $this->texto($c['video_tipo'] ?? null);

        return $ruta === null || $tipo === null ? null : ['ruta' => $ruta, 'tipo' => $tipo];
    }

    public function evidencia(int $incidenciaId, int $numero = 1): ?string
    {
        $ruta = DB::table('evidencias_incidencia')
            ->where('incidencia_id', $incidenciaId)
            ->where('orden', $numero)
            ->value('ruta');

        return is_string($ruta) && $ruta !== '' ? $ruta : null;
    }

    /**
     * Las que reporto una persona y los ingresos repetidos que asienta la
     * puerta: el resto de las del sistema alimenta el reporte de intentos
     * y no se muestra ni se atiende.
     */
    private function consulta(): Builder
    {
        return DB::table('incidencias as incidencia')
            ->leftJoin('aulas as aula', 'aula.id', '=', 'incidencia.aula_id')
            ->leftJoin('estudiantes as estudiante', 'estudiante.id', '=', 'incidencia.estudiante_id')
            ->leftJoin('users as autor', 'autor.id', '=', 'incidencia.reportada_por')
            ->leftJoin('roles as rol', 'rol.id', '=', 'autor.role_id')
            ->leftJoin('accesos_aula as acceso', 'acceso.id', '=', 'incidencia.acceso_aula_id')
            ->leftJoin('users as atendio', 'atendio.id', '=', 'incidencia.atendida_por')
            ->leftJoin('users as corrigio', 'corrigio.id', '=', 'incidencia.corregida_por')
            ->where(static function (Builder $donde): void {
                $donde->where('incidencia.origen', OrigenIncidencia::Persona->value)
                    ->orWhere('incidencia.tipo', TipoIncidencia::IngresoRepetido->value);
            })
            ->select([
                'incidencia.id',
                'incidencia.examen_id',
                'incidencia.aula_id',
                'incidencia.tipo',
                'incidencia.gravedad',
                'incidencia.tipo_texto',
                'incidencia.detalle',
                'incidencia.estudiante_texto',
                'incidencia.ocurrida_en',
                'incidencia.atendida_en',
                'incidencia.corregida_en',
                'aula.nombre as aula',
                'estudiante.codigo_universitario',
                'estudiante.nombres',
                'estudiante.apellidos',
                'autor.name as autor',
                'rol.name as rol',
                'acceso.nombre as acceso',
                'atendio.name as atendio',
                'corrigio.name as corrigio',
            ])
            ->selectRaw('(select count(*) from evidencias_incidencia as foto where foto.incidencia_id = incidencia.id) as evidencias')
            ->selectRaw('(incidencia.video is not null) as con_video');
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

        $estudiante = $codigo === null
            ? $this->texto($columnas['estudiante_texto'] ?? null)
            : $this->nombreDe($columnas['apellidos'] ?? null, $columnas['nombres'] ?? null).' · '.$codigo;

        $gravedad = Gravedad::tryFrom($this->texto($columnas['gravedad'] ?? null) ?? '') ?? TipoIncidencia::tryFrom($tipo)?->gravedad();
        $propio = $this->texto($columnas['tipo_texto'] ?? null);
        $corregidaEn = $this->texto($columnas['corregida_en'] ?? null);

        return [
            'id' => $this->entero($columnas['id'] ?? null),
            'examen_id' => $this->enteroONulo($columnas['examen_id'] ?? null),
            'tipo' => $tipo,
            'tipo_texto' => $propio ?? TipoIncidencia::tryFrom($tipo)?->etiqueta() ?? $tipo,
            // El nombre de una incidencia no prevista, tal como se escribio.
            'nombre_propio' => $propio,
            'gravedad' => $gravedad?->value,
            'gravedad_texto' => $gravedad?->etiqueta(),
            'aula_id' => $this->enteroONulo($columnas['aula_id'] ?? null),
            'aula' => $this->texto($columnas['aula'] ?? null),
            'estudiante' => $estudiante,
            'detalle' => $this->texto($columnas['detalle'] ?? null),
            'hora' => $this->hora($this->texto($columnas['ocurrida_en'] ?? null), false),
            'por' => $autor ?? $acceso,
            'rol' => $autor === null && $acceso !== null
                ? 'Código de aula'
                : $this->texto($columnas['rol'] ?? null),
            'atendida' => $atendidaEn !== null,
            'atendida_por' => $atendidaEn === null ? null : $this->texto($columnas['atendio'] ?? null),
            'atendida_en' => $this->iso($atendidaEn),
            'evidencia' => $this->entero($columnas['evidencias'] ?? 0) > 0,
            // Cuantas fotos: se piden de a una, por su numero.
            'evidencias' => $this->entero($columnas['evidencias'] ?? 0),
            // Un video corto de evidencia, aparte de las fotos.
            'video' => (bool) ($columnas['con_video'] ?? false),
            'corregida' => $corregidaEn !== null,
            'corregida_por' => $corregidaEn === null ? null : $this->texto($columnas['corrigio'] ?? null),
        ];
    }
}
