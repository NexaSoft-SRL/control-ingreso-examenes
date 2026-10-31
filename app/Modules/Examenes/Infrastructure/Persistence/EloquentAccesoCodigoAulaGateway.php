<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Infrastructure\Persistence;

use App\Modules\Administracion\Application\Contracts\BitacoraGateway;
use App\Modules\Examenes\Application\Contracts\AccesoCodigoAulaGateway;
use App\Modules\Examenes\Domain\Models\AccesoCodigoAula;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Un acceso con codigo de aula nace PENDIENTE y solo abre la puerta cuando
 * el docente lo AUTORIZA en persona; puede RECHAZARLO o CORTARLO, y
 * moverlo a otra aula del examen. Las filas no se borran: los ingresos y
 * las incidencias las nombran.
 */
final readonly class EloquentAccesoCodigoAulaGateway implements AccesoCodigoAulaGateway
{
    // La puerta abre una hora antes del inicio.
    private const MINUTOS_ANTES = 60;

    public function __construct(
        private BitacoraGateway $bitacora,
    ) {}

    public function ingresar(string $codigo, string $nombreColaborador): ?array
    {
        $ahora = now();
        $elegido = null;

        // El mismo codigo puede servir otro dia en otra aula: vale el del
        // examen cuya ventana esta abierta ahora.
        foreach ($this->candidatos($codigo) as $candidato) {
            if ($ahora >= $candidato['abre'] && $ahora < $candidato['cierra']) {
                $elegido = $candidato;
                break;
            }
        }

        if ($elegido === null) {
            return null;
        }

        $aulaNombre = $this->nombreDeAula($elegido['aula_id']);
        $token = bin2hex(random_bytes(32));

        $acceso = AccesoCodigoAula::create([
            'examen_id' => $elegido['examen_id'],
            'aula_id' => $elegido['aula_id'],
            'codigo' => $codigo,
            'nombre_colaborador' => $nombreColaborador,
            'token' => $token,
            'ultimo_acceso_en' => $ahora,
            'estado' => 'PENDIENTE',
        ]);

        $this->bitacora->registrar(
            null,
            'acceso_codigo_aula.ingreso',
            'accesos_codigo_aula',
            $acceso->id,
            sprintf(
                '%s pidió controlar el aula %s con su código; espera la autorización del docente.',
                $nombreColaborador,
                $aulaNombre,
            ),
        );

        return [
            'token' => $token,
            'examen_id' => $elegido['examen_id'],
            'aula_id' => $elegido['aula_id'],
            'aula_nombre' => $aulaNombre,
            'nombre_colaborador' => $nombreColaborador,
            'expira_en' => $elegido['fin']->format('Y-m-d H:i:s'),
            'estado' => 'pendiente',
        ];
    }

    public function ventanaDelCodigo(string $codigo): ?array
    {
        $candidatos = $this->candidatos($codigo);

        if ($candidatos === []) {
            return null;
        }

        $ahora = now();
        $porVenir = array_values(array_filter($candidatos, static fn (array $c): bool => $c['cierra'] > $ahora));

        // Primero lo que viene, del mas cercano; despues lo ya cerrado, del
        // mas reciente.
        if ($porVenir !== []) {
            usort($porVenir, static fn (array $a, array $b): int => $a['abre'] <=> $b['abre']);
            $ventana = $porVenir[0];
        } else {
            usort($candidatos, static fn (array $a, array $b): int => $b['cierra'] <=> $a['cierra']);
            $ventana = $candidatos[0];
        }

        // `cierra` dice el termino: el real si se finalizo, si no el previsto.
        return [
            'abre' => $ventana['abre']->format('Y-m-d H:i'),
            'cierra' => $ventana['fin']->format('Y-m-d H:i'),
            'cerrada' => $ventana['cierra'] <= $ahora,
        ];
    }

    public function salir(string $token): void
    {
        // Quien sale (o cancela la espera) deja de estar en la puerta.
        AccesoCodigoAula::where('token', $token)
            ->whereIn('estado', ['PENDIENTE', 'AUTORIZADO'])
            ->update(['estado' => 'CORTADO', 'revocado_en' => now()]);
    }

    public function sesion(string $token): array
    {
        /** @var AccesoCodigoAula|null $acceso */
        $acceso = AccesoCodigoAula::where('token', $token)->first();

        if ($acceso === null) {
            return ['estado' => 'no_valido'];
        }

        $ventana = $this->ventanaDe($acceso->examen_id);

        if ($ventana === null) {
            return ['estado' => 'no_valido'];
        }

        if (now() >= $ventana['cierra']) {
            return ['estado' => 'finalizado'];
        }

        $estado = $acceso->estado;

        if ($estado === 'RECHAZADO' || $estado === 'CORTADO') {
            return ['estado' => mb_strtolower($estado)];
        }

        $acceso->ultimo_acceso_en = now();
        $acceso->save();

        return [
            'estado' => 'ok',
            'data' => [
                'token' => $acceso->token,
                'examen_id' => $acceso->examen_id,
                'aula_id' => $acceso->aula_id,
                'aula_nombre' => $this->nombreDeAula($acceso->aula_id),
                'nombre_colaborador' => $acceso->nombre_colaborador,
                'expira_en' => $ventana['fin']->format('Y-m-d H:i:s'),
                'estado' => mb_strtolower($estado),
            ],
        ];
    }

    public function delExamen(int $examenId): array
    {
        $filas = DB::table('accesos_codigo_aula as acceso')
            ->join('aulas as aula', 'aula.id', '=', 'acceso.aula_id')
            ->where('acceso.examen_id', $examenId)
            ->whereIn('acceso.estado', ['PENDIENTE', 'AUTORIZADO'])
            ->orderBy('acceso.created_at')
            ->orderBy('acceso.id')
            ->get([
                'acceso.id',
                'acceso.nombre_colaborador',
                'acceso.estado',
                'acceso.aula_id',
                'acceso.created_at',
                'acceso.ultimo_acceso_en',
                'aula.nombre as aula',
            ]);

        $accesos = [];

        foreach ($filas as $fila) {
            $columnas = get_object_vars($fila);
            $accesos[] = [
                'id' => self::entero($columnas['id'] ?? null),
                'nombre' => self::texto($columnas['nombre_colaborador'] ?? null),
                'estado' => mb_strtolower(self::texto($columnas['estado'] ?? null)),
                'aula_id' => self::entero($columnas['aula_id'] ?? null),
                'aula' => self::texto($columnas['aula'] ?? null),
                'pedido_en' => self::iso($columnas['created_at'] ?? null),
                'visto_en' => self::iso($columnas['ultimo_acceso_en'] ?? null),
            ];
        }

        return $accesos;
    }

    public function resolver(int $accesoId, int $examenId, string $estado, int $usuarioId, ?int $aulaId = null): ?string
    {
        return DB::transaction(function () use ($accesoId, $examenId, $estado, $usuarioId, $aulaId): ?string {
            $fila = DB::table('accesos_codigo_aula as acceso')
                ->join('aulas as aula', 'aula.id', '=', 'acceso.aula_id')
                ->where('acceso.id', $accesoId)
                ->where('acceso.examen_id', $examenId)
                ->lockForUpdate()
                ->first(['acceso.nombre_colaborador', 'acceso.estado', 'acceso.aula_id', 'aula.nombre as aula']);

            if ($fila === null) {
                return null;
            }

            $columnas = get_object_vars($fila);
            $anterior = self::texto($columnas['estado'] ?? null);
            $nombre = self::texto($columnas['nombre_colaborador'] ?? null);
            $aula = self::texto($columnas['aula'] ?? null);
            $destino = self::entero($columnas['aula_id'] ?? null);

            // Solo lo vigente cambia: lo rechazado o cortado queda asi.
            if (! in_array($anterior, ['PENDIENTE', 'AUTORIZADO'], true)) {
                return $anterior;
            }

            if ($aulaId !== null) {
                $otra = DB::table('examen_aula as asignada')
                    ->join('aulas as aula', 'aula.id', '=', 'asignada.aula_id')
                    ->where('asignada.examen_id', $examenId)
                    ->where('asignada.aula_id', $aulaId)
                    ->value('aula.nombre');

                if (! is_string($otra)) {
                    return 'AULA_AJENA';
                }

                $destino = $aulaId;
                $aula = $otra;
            }

            DB::table('accesos_codigo_aula')->where('id', $accesoId)->update([
                'estado' => $estado,
                'aula_id' => $destino,
                'resuelto_por' => $usuarioId,
                'resuelto_en' => now(),
                'revocado_en' => in_array($estado, ['RECHAZADO', 'CORTADO'], true) ? now() : null,
                'updated_at' => now(),
            ]);

            [$operacion, $texto] = match (true) {
                $estado === 'RECHAZADO' => ['acceso_aula.rechazar', '%s no fue autorizado a controlar el aula %s.'],
                $estado === 'CORTADO' => ['acceso_aula.cortar', 'Se cortó el acceso de %s al aula %s.'],
                $anterior === 'AUTORIZADO' => ['acceso_aula.trasladar', '%s pasó a controlar el aula %s.'],
                default => ['acceso_aula.autorizar', '%s fue autorizado a controlar el aula %s.'],
            };

            $this->bitacora->registrar(
                $usuarioId,
                $operacion,
                'accesos_codigo_aula',
                $accesoId,
                sprintf($texto, $nombre, $aula),
            );

            return $estado;
        }, 3);
    }

    /**
     * Las ventanas del codigo, de todos los examenes que lo usan. Abre una
     * hora antes del inicio y `cierra` cuando un docente finaliza el examen
     * o, si nadie lo hace, al terminar su dia; `fin` es el termino que se
     * muestra (el real o el previsto).
     *
     * @return list<array{examen_id: int, aula_id: int, abre: DateTimeImmutable, cierra: DateTimeImmutable, fin: DateTimeImmutable}>
     */
    private function candidatos(string $codigo): array
    {
        $filas = DB::table('examen_aula_codigos as c')
            ->join('examenes as e', 'e.id', '=', 'c.examen_id')
            ->where('c.codigo', $codigo)
            ->get(['c.examen_id', 'c.aula_id', 'e.fecha', 'e.hora_inicio', 'e.duracion_minutos', 'e.finalizado_en']);

        $candidatos = [];

        foreach ($filas as $fila) {
            $columnas = get_object_vars($fila);

            $candidatos[] = [
                'examen_id' => self::entero($columnas['examen_id'] ?? null),
                'aula_id' => self::entero($columnas['aula_id'] ?? null),
                ...self::ventana($columnas),
            ];
        }

        return $candidatos;
    }

    /**
     * @return array{abre: DateTimeImmutable, cierra: DateTimeImmutable, fin: DateTimeImmutable}|null
     */
    private function ventanaDe(int $examenId): ?array
    {
        $examen = DB::table('examenes')
            ->where('id', $examenId)
            ->first(['fecha', 'hora_inicio', 'duracion_minutos', 'finalizado_en']);

        return $examen === null ? null : self::ventana(get_object_vars($examen));
    }

    /**
     * @param  array<mixed>  $columnas
     * @return array{abre: DateTimeImmutable, cierra: DateTimeImmutable, fin: DateTimeImmutable}
     */
    private static function ventana(array $columnas): array
    {
        $fecha = self::texto($columnas['fecha'] ?? null);
        $inicio = self::inicio($fecha, self::texto($columnas['hora_inicio'] ?? null));
        $finalizado = self::texto($columnas['finalizado_en'] ?? null);
        $finalizadoEn = $finalizado === ''
            ? null
            : new DateTimeImmutable(substr($finalizado, 0, 19), now()->getTimezone());

        return [
            'abre' => $inicio->modify(sprintf('-%d minutes', self::MINUTOS_ANTES)),
            'cierra' => $finalizadoEn ?? self::inicio($fecha, '00:00')->modify('+1 day'),
            'fin' => $finalizadoEn
                ?? $inicio->modify(sprintf('+%d minutes', self::entero($columnas['duracion_minutos'] ?? null))),
        ];
    }

    private static function inicio(string $fecha, string $hora): DateTimeImmutable
    {
        return new DateTimeImmutable(substr($fecha, 0, 10).' '.substr($hora, 0, 5).':00', now()->getTimezone());
    }

    private function nombreDeAula(int $aulaId): string
    {
        $nombre = DB::table('aulas')->where('id', $aulaId)->value('nombre');

        return is_string($nombre) ? $nombre : '';
    }

    private static function iso(mixed $instante): ?string
    {
        if (! is_string($instante) || $instante === '') {
            return null;
        }

        return (new DateTimeImmutable(substr($instante, 0, 19), now()->getTimezone()))->format(DATE_ATOM);
    }

    private static function texto(mixed $valor): string
    {
        return is_scalar($valor) ? (string) $valor : '';
    }

    private static function entero(mixed $valor): int
    {
        return is_numeric($valor) ? (int) $valor : 0;
    }
}
