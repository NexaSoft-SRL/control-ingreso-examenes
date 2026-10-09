<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Estudiantes\Application\Contracts\ConflictoGateway;
use App\Modules\Estudiantes\Application\DTOs\ConflictoData;
use App\Modules\Estudiantes\Application\DTOs\FiltroListaData;
use App\Modules\Estudiantes\Application\DTOs\PaginaData;
use App\Modules\Estudiantes\Domain\Enums\EstadoConflicto;
use App\Modules\Estudiantes\Domain\Enums\OrigenEstudiante;
use App\Modules\Estudiantes\Domain\Enums\ResolucionConflicto;
use App\Modules\Estudiantes\Domain\Enums\TipoConflicto;
use App\Modules\Estudiantes\Domain\Exceptions\ConflictoYaResueltoException;
use App\Modules\Estudiantes\Domain\Exceptions\DocumentoEnUsoException;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

final class EloquentConflictoGateway implements ConflictoGateway
{
    use ConsultasDePadron;

    /**
     * Las fechas se muestran en la hora de Cochabamba; la base guarda UTC.
     */
    private const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    public function __construct(
        private readonly BitacoraGateway $bitacora,
    ) {}

    public function listar(FiltroListaData $filtro): PaginaData
    {
        $consulta = DB::table('conflictos_padron as c')
            ->join('estudiantes as e', 'e.id', '=', 'c.estudiante_id')
            ->leftJoin('usuarios as u', 'u.id', '=', 'c.reportado_por')
            ->leftJoin('docentes as d', 'd.user_id', '=', 'u.id')
            ->leftJoin('grupos as g', 'g.id', '=', 'c.grupo_id')
            ->leftJoin('asignaturas as a', 'a.id', '=', 'g.asignatura_id');

        if ($filtro->estado !== null) {
            $consulta->where('c.estado', $filtro->estado);
        }

        $total = (clone $consulta)->count();

        $filas = $consulta
            ->orderBy('c.id')
            ->forPage($filtro->pagina, $filtro->porPagina)
            ->get([
                'c.id',
                'c.estudiante_id',
                'c.grupo_id',
                'c.tipo',
                'c.estado',
                'c.via',
                'c.documento_nuevo',
                'c.nombres_nuevos',
                'c.apellidos_nuevos',
                'c.created_at',
                'e.codigo_universitario',
                'e.documento_identidad',
                'e.nombres',
                'e.apellidos',
                'e.origen',
                'e.created_at as estudiante_creado_en',
                'u.nombre as usuario',
                'd.nombre_completo as docente',
                'g.codigo as grupo',
                'a.nombre as asignatura',
            ]);

        $estudianteIds = [];

        foreach ($filas as $fila) {
            $estudianteIds[] = $this->entero($fila->estudiante_id);
        }

        $primeras = $this->primerasCargasDeDocente($estudianteIds);
        $datos = [];

        foreach ($filas as $fila) {
            $pendiente = $this->texto($fila->estado) === EstadoConflicto::Pendiente->value;
            $tipo = TipoConflicto::tryFrom((string) $this->texto($fila->tipo));

            $guardadoPor = $this->texto($fila->origen) === OrigenEstudiante::Docente->value
                ? ($primeras[$this->entero($fila->estudiante_id)] ?? OrigenEstudiante::Docente->etiqueta())
                : $this->porAdministracion($fila->estudiante_creado_en);

            $nuevoPor = $this->texto($fila->via) === OrigenEstudiante::Docente->value
                ? $this->porDocente($fila->docente ?? $fila->usuario, $fila->asignatura, $fila->grupo)
                : $this->porAdministracion($fila->created_at);

            $datos[] = [
                'id' => $this->entero($fila->id),
                'codigo' => $this->texto($fila->codigo_universitario),
                'tipo' => $tipo?->etiqueta() ?? $this->texto($fila->tipo),
                'estado' => $pendiente ? 'pendiente' : 'resuelto',
                'guardado' => [
                    'nombre' => $this->nombreCompleto($fila->apellidos, $fila->nombres),
                    'documento' => $this->texto($fila->documento_identidad),
                    'por' => $guardadoPor,
                ],
                'nuevo' => [
                    'nombre' => $this->nombreCompleto($fila->apellidos_nuevos, $fila->nombres_nuevos),
                    'documento' => $this->texto($fila->documento_nuevo),
                    'por' => $nuevoPor,
                ],
                'inscripcion_en_espera' => $pendiente && $fila->grupo_id !== null,
            ];
        }

        return new PaginaData($datos, $total, $filtro->pagina, $filtro->porPagina, [
            'pendientes' => DB::table('conflictos_padron')
                ->where('estado', EstadoConflicto::Pendiente->value)
                ->count(),
            'resueltos' => DB::table('conflictos_padron')
                ->where('estado', EstadoConflicto::Resuelto->value)
                ->count(),
        ]);
    }

    public function buscar(int $conflictoId): ?ConflictoData
    {
        $fila = DB::table('conflictos_padron as c')
            ->join('estudiantes as e', 'e.id', '=', 'c.estudiante_id')
            ->where('c.id', $conflictoId)
            ->first(['c.id', 'c.estudiante_id', 'c.documento_nuevo', 'c.estado', 'e.codigo_universitario']);

        if ($fila === null) {
            return null;
        }

        $fila = get_object_vars($fila);

        return new ConflictoData(
            id: $this->entero(($fila['id'] ?? null)),
            estudianteId: $this->entero(($fila['estudiante_id'] ?? null)),
            codigoUniversitario: (string) $this->texto(($fila['codigo_universitario'] ?? null)),
            documentoNuevo: $this->texto(($fila['documento_nuevo'] ?? null)),
            resuelto: $this->texto(($fila['estado'] ?? null)) !== EstadoConflicto::Pendiente->value,
        );
    }

    public function resolver(int $conflictoId, ResolucionConflicto $resolucion, int $usuarioId): void
    {
        DB::transaction(function () use ($conflictoId, $resolucion, $usuarioId): void {
            $conflicto = DB::table('conflictos_padron')
                ->where('id', $conflictoId)
                ->lockForUpdate()
                ->first();

            if ($conflicto === null) {
                return;
            }

            $conflicto = get_object_vars($conflicto);

            // Dos administradores a la vez: el segundo llega aqui con el
            // conflicto ya cerrado.
            if ($this->texto(($conflicto['estado'] ?? null)) !== EstadoConflicto::Pendiente->value) {
                throw new ConflictoYaResueltoException;
            }

            $ahora = now();
            $estudianteId = $this->entero(($conflicto['estudiante_id'] ?? null));
            $documento = $this->texto(($conflicto['documento_nuevo'] ?? null));

            if ($resolucion === ResolucionConflicto::UsarCarga) {
                $ajeno = $documento !== null && DB::table('estudiantes')
                    ->where('documento_identidad', $documento)
                    ->where('id', '<>', $estudianteId)
                    ->exists();

                if ($ajeno) {
                    throw new DocumentoEnUsoException;
                }

                $cambios = [
                    'nombres' => $this->texto(($conflicto['nombres_nuevos'] ?? null)),
                    'apellidos' => $this->texto(($conflicto['apellidos_nuevos'] ?? null)),
                    'verificado' => true,
                    'updated_at' => $ahora,
                ];

                if ($documento !== null) {
                    $cambios['documento_identidad'] = $documento;
                }

                DB::table('estudiantes')->where('id', $estudianteId)->update($cambios);
            }

            $grupoId = $this->enteroONulo($conflicto['grupo_id'] ?? null);

            if ($grupoId !== null) {
                DB::table('inscripciones')->insertOrIgnore([
                    'estudiante_id' => $estudianteId,
                    'grupo_id' => $grupoId,
                    'via' => $this->texto(($conflicto['via'] ?? null)),
                    'cargada_por' => $this->enteroONulo(($conflicto['reportado_por'] ?? null)),
                    'carga_id' => $this->enteroONulo(($conflicto['carga_id'] ?? null)),
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }

            DB::table('conflictos_padron')->where('id', $conflictoId)->update([
                'estado' => EstadoConflicto::Resuelto->value,
                'resolucion' => $resolucion->value,
                'resuelto_por' => $usuarioId,
                'resuelto_en' => $ahora,
                'updated_at' => $ahora,
            ]);

            $codigo = $this->texto(
                DB::table('estudiantes')->where('id', $estudianteId)->value('codigo_universitario')
            );

            $this->bitacora->registrar(
                $usuarioId,
                'padron.resolver_conflicto',
                'conflictos_padron',
                $conflictoId,
                sprintf(
                    'Conflicto de %s resuelto: %s.',
                    $codigo,
                    $resolucion === ResolucionConflicto::UsarCarga
                        ? 'se usa la carga nueva'
                        : 'se mantiene el padrón',
                ),
            );
        }, 3);
    }

    /**
     * Quien trajo primero a cada estudiante de origen docente: su primera
     * inscripcion por esa via.
     *
     * @param  list<int>  $estudianteIds
     * @return array<int, string>
     */
    private function primerasCargasDeDocente(array $estudianteIds): array
    {
        if ($estudianteIds === []) {
            return [];
        }

        $filas = DB::table('inscripciones as i')
            ->join('grupos as g', 'g.id', '=', 'i.grupo_id')
            ->join('asignaturas as a', 'a.id', '=', 'g.asignatura_id')
            ->leftJoin('usuarios as u', 'u.id', '=', 'i.cargada_por')
            ->leftJoin('docentes as d', 'd.user_id', '=', 'u.id')
            ->whereIn('i.estudiante_id', $estudianteIds)
            ->where('i.via', OrigenEstudiante::Docente->value)
            ->orderBy('i.id')
            ->get([
                'i.estudiante_id',
                'u.nombre as usuario',
                'd.nombre_completo as docente',
                'g.codigo as grupo',
                'a.nombre as asignatura',
            ]);

        $primeras = [];

        foreach ($filas as $fila) {
            $estudianteId = $this->entero($fila->estudiante_id);

            if (! isset($primeras[$estudianteId])) {
                $primeras[$estudianteId] = $this->porDocente(
                    $fila->docente ?? $fila->usuario,
                    $fila->asignatura,
                    $fila->grupo,
                );
            }
        }

        return $primeras;
    }

    private function porDocente(mixed $nombre, mixed $asignatura, mixed $grupo): string
    {
        $nombre = $this->texto($nombre) ?? OrigenEstudiante::Docente->etiqueta();
        $asignatura = $this->texto($asignatura);

        return $asignatura === null
            ? $nombre
            : "{$nombre} · {$asignatura}, grupo {$this->texto($grupo)}";
    }

    private function porAdministracion(mixed $instante): string
    {
        $etiqueta = OrigenEstudiante::Administracion->etiqueta();
        $texto = $this->texto($instante);

        if ($texto === null) {
            return $etiqueta;
        }

        try {
            $fecha = new DateTimeImmutable($texto);
        } catch (Throwable) {
            return $etiqueta;
        }

        return sprintf(
            '%s · %d %s %s',
            $etiqueta,
            (int) $fecha->format('j'),
            self::MESES[(int) $fecha->format('n') - 1],
            $fecha->format('Y'),
        );
    }
}
