<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Examenes\Application\DTOs\AulaDeExamenData;
use App\Modules\Examenes\Application\DTOs\ExamenActualData;
use App\Modules\Examenes\Application\DTOs\GuardarExamenData;
use App\Modules\Examenes\Domain\Models\Examen;
use App\Modules\Examenes\Domain\Models\ExamenAula;
use App\Modules\Examenes\Domain\Models\ExamenAulaApoyo;
use App\Modules\Examenes\Domain\Models\ExamenGrupo;
use App\Modules\Examenes\Domain\Models\ExamenNorma;
use App\Modules\Examenes\Domain\Models\PlantillaNorma;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Las habilitaciones, los codigos QR, los ingresos y las incidencias son de
 * otros modulos: se tocan por nombre de tabla, sin importar sus modelos.
 */
final class EloquentExamenGateway implements ExamenGateway
{
    use ConvierteFilas;

    public function __construct(
        private readonly BitacoraGateway $bitacora,
    ) {}

    public function existe(int $examenId): bool
    {
        return Examen::whereKey($examenId)->exists();
    }

    public function tieneIngresos(int $examenId): bool
    {
        return DB::table('ingresos')->where('examen_id', $examenId)->exists();
    }

    public function tieneIncidencias(int $examenId): bool
    {
        return DB::table('incidencias')->where('examen_id', $examenId)->exists();
    }

    public function actual(int $examenId): ?ExamenActualData
    {
        $examen = Examen::find($examenId);

        if (! $examen instanceof Examen) {
            return null;
        }

        return new ExamenActualData(
            id: $examen->id,
            periodoId: $examen->periodo_id,
            asignaturaId: $examen->asignatura_id,
            tipo: $examen->tipo->value,
            fecha: $examen->fecha->format('Y-m-d'),
            horaInicio: substr($examen->hora_inicio, 0, 5),
            duracionMinutos: $examen->duracion_minutos,
            grupos: $this->ids(ExamenGrupo::where('examen_id', $examenId)->pluck('grupo_id')->all()),
            aulas: $this->ids(ExamenAula::where('examen_id', $examenId)->pluck('aula_id')->all()),
        );
    }

    public function registrar(GuardarExamenData $datos, int $usuarioId): int
    {
        $periodoId = $datos->periodoId;

        if ($periodoId === null) {
            throw new LogicException('El examen llega a guardarse sin periodo.');
        }

        return DB::transaction(function () use ($datos, $periodoId, $usuarioId): int {
            $examen = Examen::create([
                'periodo_id' => $periodoId,
                'asignatura_id' => $datos->asignaturaId,
                'tipo' => $datos->tipo,
                'fecha' => $datos->fecha,
                'hora_inicio' => $datos->horaInicio.':00',
                'duracion_minutos' => $datos->duracionMinutos,
                'normas' => $datos->normas,
                'creado_por' => $usuarioId,
            ]);

            foreach ($datos->grupos as $grupoId) {
                ExamenGrupo::create(['examen_id' => $examen->id, 'grupo_id' => $grupoId]);
            }

            $personalAjeno = false;

            foreach ($datos->aulas as $aula) {
                $this->agregarAula($examen->id, $aula, $usuarioId);

                $personalAjeno = $personalAjeno
                    || $aula->apoyo !== []
                    || ($aula->encargadoId !== null && $aula->encargadoId !== $usuarioId);
            }

            $this->sincronizarNormas($examen->id, $datos->normasMarcadas, null);

            $this->bitacora->registrar(
                $usuarioId,
                'examen.registrar',
                'examenes',
                $examen->id,
                $this->descripcion($examen, 'registrado').' '.$this->resumenDeGrupos($examen->id, $usuarioId),
            );

            if ($personalAjeno) {
                $this->asentarPersonal($examen, $usuarioId);
            }

            return $examen->id;
        }, 3);
    }

    public function actualizar(int $examenId, GuardarExamenData $datos, int $usuarioId): bool
    {
        $periodoId = $datos->periodoId;

        if ($periodoId === null) {
            throw new LogicException('El examen llega a guardarse sin periodo.');
        }

        return DB::transaction(function () use ($examenId, $datos, $periodoId, $usuarioId): bool {
            $examen = Examen::whereKey($examenId)->lockForUpdate()->first();

            if (! $examen instanceof Examen) {
                return false;
            }

            $examen->fill([
                'periodo_id' => $periodoId,
                'asignatura_id' => $datos->asignaturaId,
                'tipo' => $datos->tipo,
                'fecha' => $datos->fecha,
                'hora_inicio' => $datos->horaInicio.':00',
                'duracion_minutos' => $datos->duracionMinutos,
                'normas' => $datos->normas,
            ])->save();

            $gruposCambiaron = $this->sincronizarGrupos($examenId, $datos->grupos);
            $personalCambio = $this->sincronizarAulas($examenId, $datos->aulas, $usuarioId);
            $this->sincronizarNormas($examenId, $datos->normasMarcadas, $datos->normasConservadas);

            $this->bitacora->registrar(
                $usuarioId,
                'examen.modificar',
                'examenes',
                $examenId,
                $this->descripcion($examen, 'modificado')
                    .($gruposCambiaron ? ' '.$this->resumenDeGrupos($examenId, $usuarioId) : ''),
            );

            if ($personalCambio) {
                $this->asentarPersonal($examen, $usuarioId);
            }

            return true;
        }, 3);
    }

    public function eliminar(int $examenId, int $usuarioId): bool
    {
        return DB::transaction(function () use ($examenId, $usuarioId): bool {
            $examen = Examen::whereKey($examenId)->lockForUpdate()->first();

            if (! $examen instanceof Examen) {
                return false;
            }

            $descripcion = $this->descripcion($examen, 'eliminado');

            // Sus grupos, aulas, personal, normas marcadas, habilitaciones y
            // codigos QR se van con el por las claves en cascada.
            $examen->delete();

            $this->bitacora->registrar(
                $usuarioId,
                'examen.eliminar',
                'examenes',
                $examenId,
                $descripcion,
            );

            return true;
        }, 3);
    }

    public function finalizar(int $examenId, int $usuarioId): ?string
    {
        return DB::transaction(function () use ($examenId, $usuarioId): ?string {
            $ahora = now()->format('Y-m-d H:i:s');
            $antes = config('umss.minutos_apertura', 60);
            $antes = is_numeric($antes) ? (int) $antes : 60;

            $examen = Examen::whereKey($examenId)
                ->whereNull('finalizado_en')
                ->whereRaw("(fecha + hora_inicio) - (? * interval '1 minute') <= ?::timestamp", [$antes, $ahora])
                ->whereRaw("?::timestamp < fecha + interval '1 day'", [$ahora])
                ->lockForUpdate()
                ->first();

            if (! $examen instanceof Examen) {
                return null;
            }

            $examen->forceFill(['finalizado_en' => $ahora, 'finalizado_por' => $usuarioId])->save();

            $this->bitacora->registrar(
                $usuarioId,
                'examen.finalizar',
                'examenes',
                $examenId,
                $this->descripcion($examen, 'finalizado'),
            );

            return $ahora;
        }, 3);
    }

    /**
     * @param  list<int>  $grupoIds
     */
    private function sincronizarGrupos(int $examenId, array $grupoIds): bool
    {
        $actuales = $this->ids(ExamenGrupo::where('examen_id', $examenId)->pluck('grupo_id')->all());
        $quitados = array_values(array_diff($actuales, $grupoIds));
        $nuevos = array_values(array_diff($grupoIds, $actuales));

        foreach ($nuevos as $grupoId) {
            ExamenGrupo::create(['examen_id' => $examenId, 'grupo_id' => $grupoId]);
        }

        if ($quitados !== []) {
            ExamenGrupo::where('examen_id', $examenId)->whereIn('grupo_id', $quitados)->delete();

            // Quien ya no esta inscrito en ningun grupo del examen deja de
            // tener habilitacion y codigo QR en el.
            foreach (['habilitaciones', 'codigos_qr'] as $tabla) {
                DB::table($tabla)
                    ->where($tabla.'.examen_id', $examenId)
                    ->whereNotExists(function (Builder $inscrito) use ($examenId, $tabla): void {
                        $inscrito->selectRaw('1')
                            ->from('examen_grupo as incluido')
                            ->join('inscripciones as inscripcion', 'inscripcion.grupo_id', '=', 'incluido.grupo_id')
                            ->where('incluido.examen_id', $examenId)
                            ->whereColumn('inscripcion.estudiante_id', $tabla.'.estudiante_id')
                            ->whereNull('inscripcion.baja_en');
                    })
                    ->delete();
            }
        }

        return $quitados !== [] || $nuevos !== [];
    }

    /**
     * Devuelve si cambio quien atiende alguna de las aulas.
     *
     * @param  list<AulaDeExamenData>  $aulas
     */
    private function sincronizarAulas(int $examenId, array $aulas, int $usuarioId): bool
    {
        /** @var array<int, ExamenAula> $actuales */
        $actuales = [];

        foreach (ExamenAula::where('examen_id', $examenId)->get() as $asignada) {
            $actuales[$asignada->aula_id] = $asignada;
        }

        $personalCambio = false;
        $conservadas = [];

        foreach ($aulas as $aula) {
            $conservadas[] = $aula->aulaId;
            $asignada = $actuales[$aula->aulaId] ?? null;
            $encargadoId = $aula->encargadoId ?? $usuarioId;

            if ($asignada === null) {
                $this->agregarAula($examenId, $aula, $usuarioId);

                $personalCambio = $personalCambio || $aula->apoyo !== [] || $encargadoId !== $usuarioId;

                continue;
            }

            if ($asignada->encargado_id !== $encargadoId) {
                $asignada->encargado_id = $encargadoId;
                $asignada->save();
                $personalCambio = true;
            }

            $apoyoActual = $this->ids(
                ExamenAulaApoyo::where('examen_aula_id', $asignada->id)->pluck('user_id')->all()
            );
            $sobran = array_values(array_diff($apoyoActual, $aula->apoyo));
            $faltan = array_values(array_diff($aula->apoyo, $apoyoActual));

            if ($sobran !== []) {
                ExamenAulaApoyo::where('examen_aula_id', $asignada->id)->whereIn('user_id', $sobran)->delete();
            }

            foreach ($faltan as $apoyoId) {
                ExamenAulaApoyo::create(['examen_aula_id' => $asignada->id, 'user_id' => $apoyoId]);
            }

            $personalCambio = $personalCambio || $sobran !== [] || $faltan !== [];
        }

        $quitadas = array_values(array_diff(array_keys($actuales), $conservadas));

        if ($quitadas !== []) {
            // Sus habilitados siguen habilitados, pero sin aula: hay que
            // volver a repartir.
            DB::table('habilitaciones')
                ->where('examen_id', $examenId)
                ->whereIn('aula_id', $quitadas)
                ->update(['aula_id' => null, 'updated_at' => now()]);

            ExamenAula::where('examen_id', $examenId)->whereIn('aula_id', $quitadas)->delete();

            $personalCambio = true;
        }

        return $personalCambio;
    }

    /**
     * Deja marcadas esas plantillas, en ese orden. La que ya estaba
     * conserva el texto con que se guardo; la nueva copia el texto que la
     * plantilla tiene ahora. Las normas cuya plantilla ya no existe siguen
     * en el examen, al final, salvo que `$conservadas` las deje fuera.
     *
     * @param  list<int>  $plantillaIds
     * @param  list<int>|null  $conservadas
     */
    private function sincronizarNormas(int $examenId, array $plantillaIds, ?array $conservadas): void
    {
        /** @var array<int, ExamenNorma> $marcadas */
        $marcadas = [];
        /** @var list<ExamenNorma> $sueltas */
        $sueltas = [];

        foreach (ExamenNorma::where('examen_id', $examenId)->orderBy('orden')->orderBy('id')->get() as $norma) {
            if ($norma->plantilla_id === null) {
                $sueltas[] = $norma;
            } else {
                $marcadas[$norma->plantilla_id] = $norma;
            }
        }

        $textos = [];

        foreach (PlantillaNorma::whereIn('id', $plantillaIds)->get() as $plantilla) {
            $textos[$plantilla->id] = $plantilla->texto;
        }

        $orden = 0;

        foreach ($plantillaIds as $plantillaId) {
            $norma = $marcadas[$plantillaId] ?? null;

            if ($norma !== null) {
                unset($marcadas[$plantillaId]);

                $norma->orden = ++$orden;
                $norma->save();

                continue;
            }

            if (! isset($textos[$plantillaId])) {
                throw new LogicException('La plantilla marcada llega a guardarse sin existir.');
            }

            ExamenNorma::create([
                'examen_id' => $examenId,
                'plantilla_id' => $plantillaId,
                'texto' => $textos[$plantillaId],
                'orden' => ++$orden,
            ]);
        }

        foreach ($marcadas as $norma) {
            $norma->delete();
        }

        foreach ($sueltas as $norma) {
            if ($conservadas !== null && ! in_array($norma->id, $conservadas, true)) {
                $norma->delete();

                continue;
            }

            $norma->orden = ++$orden;
            $norma->save();
        }
    }

    private function agregarAula(int $examenId, AulaDeExamenData $aula, int $usuarioId): void
    {
        $asignada = ExamenAula::create([
            'examen_id' => $examenId,
            'aula_id' => $aula->aulaId,
            'encargado_id' => $aula->encargadoId ?? $usuarioId,
        ]);

        foreach ($aula->apoyo as $apoyoId) {
            ExamenAulaApoyo::create(['examen_aula_id' => $asignada->id, 'user_id' => $apoyoId]);
        }
    }

    private function asentarPersonal(Examen $examen, int $usuarioId): void
    {
        $partes = [];

        $filas = DB::table('examen_aula as asignada')
            ->join('aulas as aula', 'aula.id', '=', 'asignada.aula_id')
            ->join('users as encargado', 'encargado.id', '=', 'asignada.encargado_id')
            ->where('asignada.examen_id', $examen->id)
            ->orderBy('aula.nombre')
            ->select(['asignada.id', 'aula.nombre as aula', 'encargado.name as encargado'])
            ->get();

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);

            $apoyo = DB::table('examen_aula_apoyo as apoyo')
                ->where('apoyo.examen_aula_id', $this->entero($columnas['id'] ?? null))
                ->count();

            $partes[] = $this->cadena($columnas['aula'] ?? null)
                .': '.$this->cadena($columnas['encargado'] ?? null)
                .($apoyo > 0 ? " (+{$apoyo} de apoyo)" : '');
        }

        $this->bitacora->registrar(
            $usuarioId,
            'examen.asignar_auxiliar',
            'examenes',
            $examen->id,
            $partes === []
                ? 'El examen quedó sin aulas ni personal.'
                : 'Quién atiende cada aula: '.implode('; ', $partes).'.',
        );
    }

    private function descripcion(Examen $examen, string $verbo): string
    {
        $asignatura = DB::table('asignaturas')->where('id', $examen->asignatura_id)->value('nombre');

        return sprintf(
            '%s de %s del %s a las %s %s.',
            $examen->tipo->etiqueta(),
            $this->cadena($asignatura),
            $examen->fecha->format('Y-m-d'),
            substr($examen->hora_inicio, 0, 5),
            $verbo,
        );
    }

    /**
     * Los grupos del examen; los de otros docentes se nombran, porque
     * sumarlos no pide aprobacion.
     */
    private function resumenDeGrupos(int $examenId, int $usuarioId): string
    {
        $propios = [];
        $ajenos = [];

        $filas = DB::table('examen_grupo as incluido')
            ->join('grupos as grupo', 'grupo.id', '=', 'incluido.grupo_id')
            ->leftJoin('docentes as docente', 'docente.id', '=', 'grupo.docente_id')
            ->where('incluido.examen_id', $examenId)
            ->orderBy('grupo.codigo')
            ->select(['grupo.codigo', 'docente.user_id'])
            ->get();

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);
            $codigo = $this->cadena($columnas['codigo'] ?? null);

            if ($this->enteroONulo($columnas['user_id'] ?? null) === $usuarioId) {
                $propios[] = $codigo;
            } else {
                $ajenos[] = $codigo;
            }
        }

        $todos = array_merge($propios, $ajenos);

        return 'Grupos: '.implode(', ', $todos).'.'
            .($ajenos === [] ? '' : ' De otros docentes: '.implode(', ', $ajenos).'.');
    }

    /**
     * @param  array<mixed>  $valores
     * @return list<int>
     */
    private function ids(array $valores): array
    {
        $ids = [];

        foreach ($valores as $valor) {
            $ids[] = $this->entero($valor);
        }

        return $ids;
    }
}
