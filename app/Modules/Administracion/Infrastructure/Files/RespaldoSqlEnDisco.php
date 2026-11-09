<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Infrastructure\Files;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Administracion\Application\Contracts\RespaldoGateway;
use App\Modules\Administracion\Application\DTOs\PaginaRespaldosData;
use App\Modules\Administracion\Application\DTOs\RespaldoData;
use App\Modules\Administracion\Domain\ValueObjects\InstanteLocal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * El respaldo es un guion SQL de datos: el esquema lo crean las
 * migraciones. Se genera en PHP y no con pg_dump porque el alojamiento de
 * destino no tiene consola remota, y ese mismo guion es el que se importa
 * desde el panel del proveedor (ver despliegue.md).
 *
 * Cada generacion, correcta o fallida, deja ademas una fila en `respaldos`:
 * ese historial es lo que lista la pantalla.
 */
final class RespaldoSqlEnDisco implements RespaldoGateway
{
    private const CARPETA = 'respaldos';

    /**
     * Tabla que solo existe desde el replanteo: un guion que no la nombra
     * es de un esquema anterior.
     */
    private const TABLA_TESTIGO = 'periodos';

    /**
     * Fuera del volcado y de la restauracion. Las tablas de trabajo de
     * Laravel no son datos del sistema y restaurarlas solo serviria para
     * revivir sesiones viejas (o cerrar la de quien restaura); `respaldos`
     * es el historial, que una restauracion no puede borrar;
     * `presencias_puerta` solo dice quien estuvo en una puerta hace un
     * momento.
     *
     * @var list<string>
     */
    private const EXCLUIDAS = [
        'migrations',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'password_reset_tokens',
        'respaldos',
        'presencias_puerta',
    ];

    public function __construct(
        private readonly BitacoraGateway $bitacora,
    ) {}

    public function generar(string $tipo, ?int $usuarioId): RespaldoData
    {
        try {
            [$contenido, $tablas, $filas] = $this->guion();

            $archivo = $this->nombreLibre();

            if (! Storage::disk('local')->put($this->ruta($archivo), $contenido)) {
                throw new RuntimeException('No se pudo escribir el archivo del respaldo.');
            }
        } catch (Throwable $fallo) {
            return $this->asentarFallo($tipo, $usuarioId, $fallo->getMessage());
        }

        $id = DB::transaction(function () use (
            $tipo,
            $usuarioId,
            $archivo,
            $contenido,
            $tablas,
            $filas,
        ): int {
            $id = DB::table('respaldos')->insertGetId([
                'archivo' => $archivo,
                'tipo' => $tipo,
                'estado' => RespaldoData::ESTADO_CORRECTO,
                'tamano_bytes' => strlen($contenido),
                'tablas' => $tablas,
                'filas' => $filas,
                'generado_por' => $usuarioId,
                'created_at' => now(),
            ]);

            $this->bitacora->registrar(
                $usuarioId,
                'respaldo.generar',
                'respaldos',
                $id,
                sprintf(
                    'Respaldo %s · %s',
                    $tipo === RespaldoData::TIPO_PROGRAMADO ? 'programado' : 'puntual',
                    $archivo,
                ),
            );

            return $id;
        }, 3);

        return $this->buscar($id)
            ?? throw new LogicException('El respaldo recien generado no esta en el historial.');
    }

    public function listar(?string $filtro, int $pagina, int $porPagina): PaginaRespaldosData
    {
        $consulta = DB::table('respaldos');

        $this->filtrar($consulta, $filtro);

        $total = $consulta->count();

        $filas = $consulta
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->forPage($pagina, $porPagina)
            ->get();

        $ultimoRestaurado = $this->ultimoRestauradoId();
        $respaldos = [];

        foreach ($filas as $fila) {
            $respaldos[] = $this->respaldo(get_object_vars($fila), $ultimoRestaurado);
        }

        $ultimo = DB::table('respaldos')
            ->where('estado', RespaldoData::ESTADO_CORRECTO)
            ->max('created_at');

        return new PaginaRespaldosData(
            filas: $respaldos,
            total: $total,
            pagina: $pagina,
            porPagina: $porPagina,
            conteos: [
                'todos' => DB::table('respaldos')->count(),
                'programados' => $this->filtrar(DB::table('respaldos'), 'programados')->count(),
                'puntuales' => $this->filtrar(DB::table('respaldos'), 'puntuales')->count(),
                'fallidos' => $this->filtrar(DB::table('respaldos'), 'fallidos')->count(),
            ],
            ultimo: is_string($ultimo) ? InstanteLocal::de($ultimo)->iso() : null,
        );
    }

    public function buscar(int $respaldoId): ?RespaldoData
    {
        $fila = DB::table('respaldos')->where('id', $respaldoId)->first();

        return $fila === null
            ? null
            : $this->respaldo(get_object_vars($fila), $this->ultimoRestauradoId());
    }

    public function contenido(int $respaldoId): ?string
    {
        $archivo = DB::table('respaldos')->where('id', $respaldoId)->value('archivo');

        if (! is_string($archivo) || ! $this->nombreValido($archivo)) {
            return null;
        }

        $disco = Storage::disk('local');
        $ruta = $this->ruta($archivo);

        if (! $disco->exists($ruta)) {
            return null;
        }

        $contenido = $disco->get($ruta);

        return is_string($contenido) ? $contenido : null;
    }

    public function esCompatible(string $contenido): bool
    {
        return preg_match(
            '/^TRUNCATE [^\r\n]*"'.self::TABLA_TESTIGO.'"/m',
            $contenido,
        ) === 1;
    }

    public function restaurar(int $respaldoId, ?int $usuarioId): int
    {
        $contenido = $this->contenido($respaldoId);

        if ($contenido === null) {
            throw new LogicException('Ese respaldo no está en disco.');
        }

        $archivo = DB::table('respaldos')->where('id', $respaldoId)->value('archivo');

        // El guion trae su propio BEGIN y COMMIT para importarlo a mano.
        // Aqui se quitan: la reposicion, el historial y el asiento van en
        // una sola transaccion de la aplicacion.
        $guion = (string) preg_replace('/^BEGIN;\R/m', '', $contenido, 1);
        $guion = (string) preg_replace('/\RCOMMIT;\s*\z/', PHP_EOL, $guion, 1);

        DB::transaction(function () use ($guion, $respaldoId, $usuarioId, $archivo): void {
            // `respaldos` apunta a `users`: el TRUNCATE ... CASCADE del
            // guion se la lleva por delante aunque no este en el volcado.
            // Se guarda el historial y se repone despues.
            $historial = [];

            foreach (DB::table('respaldos')->orderBy('id')->get() as $fila) {
                $historial[] = get_object_vars($fila);
            }

            DB::unprepared($guion);

            $cuentas = [];

            foreach (DB::table('users')->pluck('id') as $id) {
                if (is_int($id)) {
                    $cuentas[$id] = true;
                }
            }

            foreach ($historial as $fila) {
                // Una cuenta creada despues del respaldo ya no existe: la
                // fila se conserva sin su autor.
                foreach (['generado_por', 'restaurado_por'] as $columna) {
                    $autor = $fila[$columna] ?? null;

                    if (! is_int($autor) || ! isset($cuentas[$autor])) {
                        $fila[$columna] = null;
                    }
                }

                DB::table('respaldos')->insert($fila);
            }

            $autor = $usuarioId !== null && isset($cuentas[$usuarioId]) ? $usuarioId : null;

            DB::table('respaldos')->where('id', $respaldoId)->update([
                'restaurado_en' => now(),
                'restaurado_por' => $autor,
            ]);

            DB::statement(
                "SELECT setval(pg_get_serial_sequence('respaldos', 'id'), "
                .'COALESCE((SELECT MAX(id) FROM "respaldos"), 1), true)'
            );

            // El asiento va despues de reponer los datos: la restauracion
            // vacia la bitacora y se llevaria el asiento de lo que se esta
            // haciendo. La bitacora queda como estaba en el respaldo, mas
            // la constancia de esta operacion.
            $this->bitacora->registrar(
                $autor,
                'respaldo.restaurar',
                'respaldos',
                $respaldoId,
                'Restauración desde '.(is_string($archivo) ? $archivo : 'el respaldo #'.$respaldoId).'.'
                .($usuarioId !== null && $autor === null
                    ? ' La cuenta que la ordenó no figura en ese respaldo.'
                    : ''),
            );
        }, 1);

        return $this->tablasDelGuion($contenido);
    }

    /**
     * El guion completo: vacia las tablas del sistema y repone sus filas.
     *
     * @return array{0: string, 1: int, 2: int} contenido, tablas y filas
     */
    private function guion(): array
    {
        $tablas = $this->tablas();
        $lineas = [
            '-- Respaldo de datos del sistema de control de exámenes.',
            '-- Generado el '.InstanteLocal::de(now())->iso().'.',
            '-- El esquema lo crean las migraciones: este guion repone los datos.',
            'BEGIN;',
            sprintf(
                'TRUNCATE %s RESTART IDENTITY CASCADE;',
                implode(', ', array_map(
                    static fn (string $tabla): string => '"'.$tabla.'"',
                    $tablas,
                )),
            ),
        ];

        $filas = 0;

        foreach ($tablas as $tabla) {
            $columnas = $this->columnas($tabla);

            if ($columnas === []) {
                continue;
            }

            foreach (DB::table($tabla)->get() as $fila) {
                /** @var array<string, mixed> $valores */
                $valores = get_object_vars($fila);

                $lineas[] = sprintf(
                    'INSERT INTO "%s" (%s) VALUES (%s);',
                    $tabla,
                    implode(', ', array_map(
                        static fn (string $columna): string => '"'.$columna.'"',
                        $columnas,
                    )),
                    implode(', ', array_map(
                        fn (string $columna): string => $this->valor($valores[$columna] ?? null),
                        $columnas,
                    )),
                );

                $filas++;
            }

            // Sin reponer la secuencia, el proximo registro chocaria con
            // un identificador ya usado.
            if (in_array('id', $columnas, true)) {
                $lineas[] = sprintf(
                    'SELECT setval(pg_get_serial_sequence(\'%s\', \'id\'), '
                    .'COALESCE((SELECT MAX(id) FROM "%s"), 1), true);',
                    $tabla,
                    $tabla,
                );
            }
        }

        $lineas[] = 'COMMIT;';

        return [implode(PHP_EOL, $lineas).PHP_EOL, count($tablas), $filas];
    }

    /**
     * La generacion fallida tambien queda en el historial, con su error y
     * sin archivo.
     */
    private function asentarFallo(string $tipo, ?int $usuarioId, string $error): RespaldoData
    {
        $id = DB::transaction(function () use ($tipo, $usuarioId, $error): int {
            $id = DB::table('respaldos')->insertGetId([
                'archivo' => null,
                'tipo' => $tipo,
                'estado' => RespaldoData::ESTADO_FALLIDO,
                'error' => $error,
                'generado_por' => $usuarioId,
                'created_at' => now(),
            ]);

            $this->bitacora->registrar(
                $usuarioId,
                'respaldo.fallar',
                'respaldos',
                $id,
                sprintf(
                    'Respaldo %s fallido',
                    $tipo === RespaldoData::TIPO_PROGRAMADO ? 'programado' : 'puntual',
                ),
            );

            return $id;
        }, 3);

        return $this->buscar($id)
            ?? throw new LogicException('El respaldo fallido no quedo en el historial.');
    }

    /**
     * `respaldo-AAAAMMDD-HHMMSS.sql` con la hora local. El nombre es unico
     * en el historial: dos respaldos en el mismo segundo no comparten
     * archivo.
     */
    private function nombreLibre(): string
    {
        $ahora = InstanteLocal::de(now());
        $segundos = 0;

        do {
            $archivo = 'respaldo-'.$ahora->sello($segundos++).'.sql';
        } while (
            DB::table('respaldos')->where('archivo', $archivo)->exists()
            || Storage::disk('local')->exists($this->ruta($archivo))
        );

        return $archivo;
    }

    private function filtrar(Builder $consulta, ?string $filtro): Builder
    {
        return match ($filtro) {
            'programados' => $consulta->where('tipo', RespaldoData::TIPO_PROGRAMADO),
            'puntuales' => $consulta->where('tipo', RespaldoData::TIPO_PUNTUAL),
            'fallidos' => $consulta->where('estado', RespaldoData::ESTADO_FALLIDO),
            default => $consulta,
        };
    }

    /**
     * Solo el ultimo respaldo desde el que se restauro se muestra como
     * «Restaurado».
     */
    private function ultimoRestauradoId(): ?int
    {
        $id = DB::table('respaldos')
            ->whereNotNull('restaurado_en')
            ->orderByDesc('restaurado_en')
            ->orderByDesc('id')
            ->value('id');

        return is_int($id) ? $id : null;
    }

    /**
     * @param  array<mixed>  $fila
     */
    private function respaldo(array $fila, ?int $ultimoRestaurado): RespaldoData
    {
        $id = $this->entero($fila['id'] ?? null) ?? 0;
        $creado = $fila['created_at'] ?? null;

        return new RespaldoData(
            id: $id,
            archivo: is_string($fila['archivo'] ?? null) ? $fila['archivo'] : null,
            tipo: is_string($fila['tipo'] ?? null) ? $fila['tipo'] : RespaldoData::TIPO_PUNTUAL,
            estado: is_string($fila['estado'] ?? null) ? $fila['estado'] : RespaldoData::ESTADO_FALLIDO,
            tamanoBytes: $this->entero($fila['tamano_bytes'] ?? null),
            tablas: $this->entero($fila['tablas'] ?? null) ?? 0,
            filas: $this->entero($fila['filas'] ?? null) ?? 0,
            error: is_string($fila['error'] ?? null) ? $fila['error'] : null,
            generadoEn: InstanteLocal::de(is_string($creado) ? $creado : 'now')->iso(),
            restaurado: $ultimoRestaurado !== null && $id === $ultimoRestaurado,
        );
    }

    /**
     * Cuantas tablas repone el guion: las que nombra su TRUNCATE.
     */
    private function tablasDelGuion(string $contenido): int
    {
        if (preg_match('/^TRUNCATE ([^\r\n]*) RESTART IDENTITY/m', $contenido, $partes) !== 1) {
            return 0;
        }

        return substr_count($partes[1], ',') + 1;
    }

    private function entero(mixed $valor): ?int
    {
        return is_numeric($valor) ? (int) $valor : null;
    }

    /**
     * Columnas de la tabla, ya como textos: el esquema las devuelve sin
     * tipar y el guion las necesita una por una.
     *
     * @return list<string>
     */
    private function columnas(string $tabla): array
    {
        $columnas = [];

        foreach (Schema::getColumnListing($tabla) as $columna) {
            if (is_string($columna)) {
                $columnas[] = $columna;
            }
        }

        return $columnas;
    }

    /**
     * @return list<string>
     */
    private function tablas(): array
    {
        $tablas = [];

        foreach (Schema::getTableListing() as $tabla) {
            if (! is_string($tabla)) {
                continue;
            }

            // Postgres devuelve el esquema por delante: "public.students".
            $punto = strrpos($tabla, '.');
            $nombre = $punto === false ? $tabla : substr($tabla, $punto + 1);

            if (! in_array($nombre, self::EXCLUIDAS, true)) {
                $tablas[] = $nombre;
            }
        }

        sort($tablas);

        return $this->ordenarPorDependencias($tablas);
    }

    /**
     * Las filas se reponen en orden: una tabla que apunta a otra no puede
     * escribirse antes que ella. El orden alfabetico no sirve ---
     * permission_role iria antes que roles--- y las claves foraneas de
     * Laravel no son diferibles, asi que se ordena por dependencias.
     *
     * @param  list<string>  $tablas
     * @return list<string>
     */
    private function ordenarPorDependencias(array $tablas): array
    {
        $pendientes = [];

        foreach ($tablas as $tabla) {
            $apunta = [];

            foreach (Schema::getForeignKeys($tabla) as $clave) {
                $destino = is_array($clave) ? ($clave['foreign_table'] ?? null) : null;

                // La autorreferencia no impone orden entre tablas.
                if (is_string($destino) && $destino !== $tabla && in_array($destino, $tablas, true)) {
                    $apunta[$destino] = true;
                }
            }

            $pendientes[$tabla] = $apunta;
        }

        $ordenadas = [];

        while ($pendientes !== []) {
            $libres = [];

            foreach ($pendientes as $tabla => $apunta) {
                if (array_diff_key($apunta, array_flip($ordenadas)) === []) {
                    $libres[] = (string) $tabla;
                }
            }

            // Un ciclo de claves foraneas dejaria esto vacio: en ese caso
            // se sigue en orden alfabetico y la restauracion lo dira.
            if ($libres === []) {
                foreach (array_keys($pendientes) as $tabla) {
                    $ordenadas[] = (string) $tabla;
                }

                break;
            }

            foreach ($libres as $tabla) {
                $ordenadas[] = $tabla;
                unset($pendientes[$tabla]);
            }
        }

        return $ordenadas;
    }

    private function ruta(string $archivo): string
    {
        if (! $this->nombreValido($archivo)) {
            throw new LogicException('El nombre del respaldo no es válido.');
        }

        return self::CARPETA.'/'.$archivo;
    }

    /**
     * Nada que no sea un respaldo de este sistema puede convertirse en una
     * ruta de disco.
     */
    private function nombreValido(string $archivo): bool
    {
        return preg_match('/^respaldo-\d{8}-\d{6}\.sql$/', $archivo) === 1;
    }

    private function valor(mixed $valor): string
    {
        if ($valor === null) {
            return 'NULL';
        }

        if (is_bool($valor)) {
            return $valor ? 'true' : 'false';
        }

        if (is_int($valor) || is_float($valor)) {
            return (string) $valor;
        }

        if (is_string($valor)) {
            return "'".str_replace("'", "''", $valor)."'";
        }

        throw new LogicException('La columna tiene un tipo que el respaldo no sabe escribir.');
    }
}
