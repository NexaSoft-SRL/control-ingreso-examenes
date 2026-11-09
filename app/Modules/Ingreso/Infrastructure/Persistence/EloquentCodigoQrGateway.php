<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Ingreso\Application\Contracts\CodigoQrGateway;
use App\Modules\Ingreso\Application\DTOs\AulaData;
use App\Modules\Ingreso\Application\DTOs\CredencialData;
use App\Modules\Ingreso\Application\DTOs\FiltroCodigosData;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Los codigos son de este modulo; habilitaciones, estudiantes, grupos y
 * aulas se leen por nombre de tabla.
 */
final class EloquentCodigoQrGateway implements CodigoQrGateway
{
    use LeeFilas;

    /**
     * El grupo del estudiante en el examen; si esta en dos, el de menor
     * codigo.
     */
    private const GRUPO = '(select min(grupo.codigo) from inscripciones as inscripcion
        join examen_grupo as incluido on incluido.grupo_id = inscripcion.grupo_id
        join grupos as grupo on grupo.id = inscripcion.grupo_id
        where inscripcion.estudiante_id = habilitacion.estudiante_id
          and inscripcion.baja_en is null
          and incluido.examen_id = habilitacion.examen_id)';

    /**
     * El estado y el error del ultimo envio del codigo a ese estudiante.
     */
    private const ULTIMO_ENVIO = "(select envio.%s from envios_correo as envio
        where envio.examen_id = habilitacion.examen_id
          and envio.estudiante_id = habilitacion.estudiante_id
          and envio.tipo = 'CODIGO_QR'
        order by envio.id desc limit 1)";

    public function __construct(
        private readonly BitacoraGateway $bitacora,
    ) {}

    /**
     * Con codigo, sin haberlo recibido y con el ultimo envio fallido.
     */
    private function fallido(): string
    {
        return 'codigo.id is not null and codigo.enviado_en is null and '
            .sprintf(self::ULTIMO_ENVIO, 'estado')." = 'FALLIDO'";
    }

    /**
     * @return array{habilitados: int, emitidos: int, enviados: int, fallidos: int, sin_aula: int}
     */
    public function resumen(int $examenId): array
    {
        $fila = $this->habilitados($examenId)
            ->selectRaw('count(*) as habilitados')
            ->selectRaw('count(codigo.id) as emitidos')
            ->selectRaw('count(codigo.enviado_en) as enviados')
            ->selectRaw('count(*) filter (where '.$this->fallido().') as fallidos')
            ->selectRaw('count(*) filter (where habilitacion.aula_id is null) as sin_aula')
            ->first();

        $columnas = $fila === null ? [] : $this->columnas($fila);

        return [
            'habilitados' => $this->entero($columnas['habilitados'] ?? 0),
            'emitidos' => $this->entero($columnas['emitidos'] ?? 0),
            'enviados' => $this->entero($columnas['enviados'] ?? 0),
            'fallidos' => $this->entero($columnas['fallidos'] ?? 0),
            'sin_aula' => $this->entero($columnas['sin_aula'] ?? 0),
        ];
    }

    public function emitirLote(int $examenId, ?int $usuarioId): int
    {
        /** @var int $emitidos */
        $emitidos = DB::transaction(function () use ($examenId, $usuarioId): int {
            // Solo los habilitados: quien no rinde no necesita codigo.
            $pendientes = $this->habilitados($examenId)
                ->whereNull('codigo.id')
                ->pluck('habilitacion.estudiante_id')
                ->all();

            $ahora = now();
            $cuantos = 0;

            foreach (array_chunk($pendientes, 500) as $lote) {
                $filas = [];

                foreach ($lote as $estudianteId) {
                    $filas[] = [
                        'examen_id' => $examenId,
                        'estudiante_id' => $this->entero($estudianteId),
                        // Opaco: no codifica ningun dato del estudiante
                        // ni del examen.
                        'token' => Str::random(48),
                        'emitido_en' => $ahora,
                        'emitido_por' => $usuarioId,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                }

                // Si dos docentes emiten a la vez, el unico descarta al
                // segundo sin romper la peticion.
                $cuantos += DB::table('codigos_qr')->insertOrIgnore($filas);
            }

            if ($cuantos > 0) {
                $this->bitacora->registrar(
                    $usuarioId,
                    'qr.emitir',
                    'codigos_qr',
                    $examenId,
                    sprintf('%d código(s) QR emitido(s) para el examen %d.', $cuantos, $examenId),
                );
            }

            return $cuantos;
        }, 3);

        return $emitidos;
    }

    /**
     * @return array{examen_id: int, estudiante_id: int}|null
     */
    public function resolver(string $token): ?array
    {
        $fila = DB::table('codigos_qr')->where('token', $token)->first();

        if ($fila === null) {
            return null;
        }

        $columnas = $this->columnas($fila);

        return [
            'examen_id' => $this->entero($columnas['examen_id'] ?? null),
            'estudiante_id' => $this->entero($columnas['estudiante_id'] ?? null),
        ];
    }

    /**
     * @return array{data: list<array<string, mixed>>, total: int}
     */
    public function listar(int $examenId, FiltroCodigosData $filtro): array
    {
        $base = $this->filtrados($examenId, $filtro);
        $total = (clone $base)->count();

        $filas = $this->ordenados($this->conDatos($base))
            ->selectRaw(sprintf(self::ULTIMO_ENVIO, 'estado').' as ultimo_estado')
            ->selectRaw(sprintf(self::ULTIMO_ENVIO, 'error').' as ultimo_error')
            ->forPage($filtro->pagina, $filtro->porPagina)
            ->get();

        $data = [];

        foreach ($filas as $fila) {
            $columnas = $this->columnas($fila);
            $codigo = $this->texto($columnas['codigo_universitario'] ?? null);
            $emitido = ($columnas['token'] ?? null) !== null;
            $enviadoEn = $this->texto($columnas['enviado_en'] ?? null);

            $envio = match (true) {
                ! $emitido => 'sin_emitir',
                $enviadoEn !== null => 'enviado',
                ($columnas['ultimo_estado'] ?? null) === 'FALLIDO' => 'fallido',
                default => 'sin_enviar',
            };

            $data[] = [
                'estudiante_id' => $this->entero($columnas['estudiante_id'] ?? null),
                'codigo' => $codigo,
                'nombre' => $this->nombreDe($columnas['apellidos'] ?? null, $columnas['nombres'] ?? null),
                'grupo' => $this->texto($columnas['grupo'] ?? null),
                'aula' => $this->texto($columnas['aula'] ?? null),
                'emitido' => $emitido,
                'enviado_en' => $this->iso($enviadoEn),
                'correo' => $codigo === null ? null : $this->correoInstitucional($codigo),
                'envio' => $envio,
                'error' => $envio === 'fallido' ? $this->texto($columnas['ultimo_error'] ?? null) : null,
            ];
        }

        return ['data' => $data, 'total' => $total];
    }

    /**
     * @return array{grupos: list<array{id: int, codigo: string}>, aulas: list<array{aula_id: int, nombre: string}>}
     */
    public function opciones(int $examenId): array
    {
        $grupos = [];

        $filasDeGrupos = DB::table('examen_grupo as incluido')
            ->join('grupos as grupo', 'grupo.id', '=', 'incluido.grupo_id')
            ->where('incluido.examen_id', $examenId)
            ->orderBy('grupo.codigo')
            ->get(['grupo.id', 'grupo.codigo']);

        foreach ($filasDeGrupos as $fila) {
            $columnas = $this->columnas($fila);

            $grupos[] = [
                'id' => $this->entero($columnas['id'] ?? null),
                'codigo' => $this->texto($columnas['codigo'] ?? null) ?? '',
            ];
        }

        $aulas = [];

        $filasDeAulas = DB::table('examen_aula as asignada')
            ->join('aulas as aula', 'aula.id', '=', 'asignada.aula_id')
            ->where('asignada.examen_id', $examenId)
            ->orderBy('asignada.id')
            ->get(['aula.id', 'aula.nombre']);

        foreach ($filasDeAulas as $fila) {
            $columnas = $this->columnas($fila);

            $aulas[] = [
                'aula_id' => $this->entero($columnas['id'] ?? null),
                'nombre' => $this->texto($columnas['nombre'] ?? null) ?? '',
            ];
        }

        return ['grupos' => $grupos, 'aulas' => $aulas];
    }

    public function credencial(int $examenId, int $estudianteId): ?CredencialData
    {
        $fila = $this->conDatos($this->habilitados($examenId))
            ->where('habilitacion.estudiante_id', $estudianteId)
            ->first();

        if ($fila === null) {
            return null;
        }

        return $this->credencialDe($this->columnas($fila), $this->examen($examenId));
    }

    /**
     * @return list<CredencialData>
     */
    public function emitidas(
        int $examenId,
        FiltroCodigosData $filtro,
        ?int $limite = null,
        bool $soloSinEnviar = false,
        ?string $desde = null,
    ): array {
        $consulta = $this->conDatos($this->filtrados($examenId, $filtro))
            ->whereNotNull('codigo.id');

        if ($soloSinEnviar) {
            $consulta->whereNull('codigo.enviado_en');
        }

        if ($desde !== null) {
            $this->sinEnvioDesde($consulta, 'CODIGO_QR', $desde);
        }

        if ($limite !== null) {
            $consulta->limit($limite);
        }

        return $this->credenciales($examenId, $consulta);
    }

    public function porReenviar(int $examenId, FiltroCodigosData $filtro, string $desde): int
    {
        return $this->sinEnvioDesde(
            $this->filtrados($examenId, $filtro)->whereNotNull('codigo.id'),
            'CODIGO_QR',
            $desde,
        )->count();
    }

    public function paraAvisar(
        int $examenId,
        FiltroCodigosData $filtro,
        ?int $limite = null,
        ?string $desde = null,
    ): array {
        $consulta = $this->conDatos($this->filtrados($examenId, $filtro));

        if ($desde !== null) {
            $this->sinEnvioDesde($consulta, 'AVISO', $desde);
        }

        if ($limite !== null) {
            $consulta->limit($limite);
        }

        return $this->credenciales($examenId, $consulta);
    }

    public function porAvisar(int $examenId, FiltroCodigosData $filtro, string $desde): int
    {
        return $this->sinEnvioDesde($this->filtrados($examenId, $filtro), 'AVISO', $desde)->count();
    }

    /**
     * @return list<CredencialData>
     */
    private function credenciales(int $examenId, Builder $consulta): array
    {
        $examen = $this->examen($examenId);
        $credenciales = [];

        foreach ($this->ordenados($consulta)->get() as $fila) {
            $credenciales[] = $this->credencialDe($this->columnas($fila), $examen);
        }

        return $credenciales;
    }

    /**
     * Deja fuera a quien ya tiene un correo de ese tipo registrado desde
     * que empezo la tanda: asi una tanda no repite destinatarios.
     */
    private function sinEnvioDesde(Builder $consulta, string $tipo, string $desde): Builder
    {
        $instante = $this->deIso($desde);

        return $consulta->whereNotExists(function (Builder $envio) use ($tipo, $instante): void {
            $envio->selectRaw('1')
                ->from('envios_correo as previo')
                ->whereColumn('previo.examen_id', 'habilitacion.examen_id')
                ->whereColumn('previo.estudiante_id', 'habilitacion.estudiante_id')
                ->where('previo.tipo', $tipo)
                ->where('previo.created_at', '>=', $instante);
        });
    }

    public function sinEnviar(int $examenId, FiltroCodigosData $filtro): int
    {
        return $this->filtrados($examenId, $filtro)
            ->whereNotNull('codigo.id')
            ->whereNull('codigo.enviado_en')
            ->count();
    }

    /**
     * Los habilitados del examen, con su codigo si ya lo tienen.
     */
    private function habilitados(int $examenId): Builder
    {
        return DB::table('habilitaciones as habilitacion')
            ->join('estudiantes as estudiante', 'estudiante.id', '=', 'habilitacion.estudiante_id')
            ->leftJoin('codigos_qr as codigo', function (JoinClause $union): void {
                $union->on('codigo.estudiante_id', '=', 'habilitacion.estudiante_id')
                    ->on('codigo.examen_id', '=', 'habilitacion.examen_id');
            })
            ->where('habilitacion.examen_id', $examenId)
            ->where('habilitacion.habilitado', true);
    }

    private function filtrados(int $examenId, FiltroCodigosData $filtro): Builder
    {
        $consulta = $this->habilitados($examenId);

        if ($filtro->grupo !== null) {
            $grupo = $filtro->grupo;

            $consulta->whereExists(function (Builder $inscrito) use ($grupo): void {
                $inscrito->selectRaw('1')
                    ->from('inscripciones as inscripcion')
                    ->join('examen_grupo as incluido', 'incluido.grupo_id', '=', 'inscripcion.grupo_id')
                    ->whereColumn('inscripcion.estudiante_id', 'habilitacion.estudiante_id')
                    ->whereColumn('incluido.examen_id', 'habilitacion.examen_id')
                    ->where('inscripcion.grupo_id', $grupo)
                    ->whereNull('inscripcion.baja_en');
            });
        }

        if ($filtro->aula !== null) {
            $consulta->where('habilitacion.aula_id', $filtro->aula);
        }

        $patron = $this->patron($filtro->buscar);

        if ($patron !== null) {
            $consulta->where(function (Builder $texto) use ($patron): void {
                $texto
                    ->whereRaw(
                        $this->sinTildes("estudiante.apellidos || ' ' || estudiante.nombres").' like ?',
                        [$patron],
                    )
                    ->orWhere('estudiante.codigo_universitario', 'like', $patron);
            });
        }

        match ($filtro->envio) {
            'sin_emitir' => $consulta->whereNull('codigo.id'),
            'enviado' => $consulta->whereNotNull('codigo.enviado_en'),
            'fallido' => $consulta->whereRaw($this->fallido()),
            'sin_enviar' => $consulta
                ->whereNotNull('codigo.id')
                ->whereNull('codigo.enviado_en')
                ->whereRaw('coalesce('.sprintf(self::ULTIMO_ENVIO, 'estado').", '') <> 'FALLIDO'"),
            default => null,
        };

        return $consulta;
    }

    private function conDatos(Builder $consulta): Builder
    {
        return $consulta
            ->leftJoin('aulas as aula', 'aula.id', '=', 'habilitacion.aula_id')
            ->leftJoin('edificios as edificio', 'edificio.id', '=', 'aula.edificio_id')
            ->select([
                'habilitacion.estudiante_id',
                'estudiante.codigo_universitario',
                'estudiante.nombres',
                'estudiante.apellidos',
                'codigo.token',
                'codigo.enviado_en',
                'aula.id as aula_id',
                'aula.nombre as aula',
                'aula.piso',
                'aula.edificio_id',
                'edificio.nombre as edificio',
            ])
            ->selectRaw(self::GRUPO.' as grupo');
    }

    private function ordenados(Builder $consulta): Builder
    {
        return $consulta
            ->orderByRaw("translate(lower(estudiante.apellidos), 'áéíóúüñ', 'aeiouun')")
            ->orderByRaw("translate(lower(estudiante.nombres), 'áéíóúüñ', 'aeiouun')")
            ->orderBy('estudiante.id');
    }

    /**
     * Lo que la credencial dice del examen, igual para todos.
     *
     * @return array{id: int, texto: string, fecha: string, hora: string, duracion: int, facultad: string|null}
     */
    private function examen(int $examenId): array
    {
        $fila = DB::table('examenes as examen')
            ->join('asignaturas as asignatura', 'asignatura.id', '=', 'examen.asignatura_id')
            ->where('examen.id', $examenId)
            ->select([
                'examen.tipo',
                'examen.fecha',
                'examen.hora_inicio',
                'examen.duracion_minutos',
                'asignatura.nombre as asignatura',
            ])
            ->selectRaw(
                '(select facultad.sigla from examen_grupo as incluido
                    join grupos as grupo on grupo.id = incluido.grupo_id
                    join facultades as facultad on facultad.id = grupo.facultad_id
                    where incluido.examen_id = examen.id
                    order by incluido.id limit 1) as facultad',
            )
            ->first();

        $columnas = $fila === null ? [] : $this->columnas($fila);
        $tipo = $this->texto($columnas['tipo'] ?? null) ?? '';

        return [
            'id' => $examenId,
            'texto' => (EloquentPuertaGateway::TIPOS[$tipo] ?? $tipo).' · '.($this->texto($columnas['asignatura'] ?? null) ?? ''),
            'fecha' => substr($this->texto($columnas['fecha'] ?? null) ?? '', 0, 10),
            'hora' => substr($this->texto($columnas['hora_inicio'] ?? null) ?? '', 0, 5),
            'duracion' => $this->entero($columnas['duracion_minutos'] ?? 0),
            'facultad' => $this->texto($columnas['facultad'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $columnas
     * @param  array{id: int, texto: string, fecha: string, hora: string, duracion: int, facultad: string|null}  $examen
     */
    private function credencialDe(array $columnas, array $examen): CredencialData
    {
        $aulaId = $this->enteroONulo($columnas['aula_id'] ?? null);

        return new CredencialData(
            examenId: $examen['id'],
            estudianteId: $this->entero($columnas['estudiante_id'] ?? null),
            facultad: $examen['facultad'],
            examen: $examen['texto'],
            fecha: $examen['fecha'],
            hora: $examen['hora'],
            duracion: $examen['duracion'],
            estudiante: $this->nombreDe($columnas['apellidos'] ?? null, $columnas['nombres'] ?? null),
            codigo: $this->texto($columnas['codigo_universitario'] ?? null) ?? '',
            aula: $aulaId === null ? null : new AulaData(
                id: $aulaId,
                nombre: $this->texto($columnas['aula'] ?? null) ?? '',
                edificio: $this->texto($columnas['edificio'] ?? null),
                piso: $this->texto($columnas['piso'] ?? null),
                edificioId: $this->enteroONulo($columnas['edificio_id'] ?? null),
            ),
            token: $this->texto($columnas['token'] ?? null),
            correo: $this->correoInstitucional($this->texto($columnas['codigo_universitario'] ?? null) ?? ''),
        );
    }
}
